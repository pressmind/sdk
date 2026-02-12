<?php

namespace Pressmind\CLI\WordPress;

use RuntimeException;
use stdClass;

/**
 * WordPress Site Migration
 *
 * Migrates a WordPress site from one domain to another.
 * Changes all database records (options, postmeta, posts),
 * handles multisite blog tables, and regenerates .htaccess.
 *
 * Requires WordPress to be booted via Tools::boot(true).
 *
 * Usage:
 *   Migrate::toSite('https://new-domain.de');
 *   Migrate::toSite('https://new-domain.de', 'https://old-domain.de');
 *   Migrate::toSite('https://new-domain.de', 'https://old-domain.de', 1); // multisite
 */
class Migrate
{
    private static string $newSite;
    private static string $oldSite;
    private static string $oldHome;
    private static ?int $idBlog;

    /**
     * Migrates the WordPress site to a new domain.
     *
     * @param string $newSite The new site URL
     * @param string|null $oldSite The old site URL (auto-detected from DB if null)
     * @param int|null $idBlog Blog ID for multisite installations
     * @throws RuntimeException On multisite errors
     */
    public static function toSite(string $newSite, ?string $oldSite = null, ?int $idBlog = null): void
    {
        Tools::boot(true);

        global $wpdb;

        self::$newSite = trim($newSite, '/');
        self::$oldSite = trim(empty($oldSite) ? get_option('siteurl') : $oldSite, '/');
        self::$oldHome = trim(get_option('home'), '/');
        self::$idBlog = empty($idBlog) ? (int) get_option('id_blog') : $idBlog;

        $isMultisite = is_multisite();

        if ($isMultisite && empty($idBlog)) {
            throw new RuntimeException('This is a multisite installation. Please set id_blog to continue.');
        }

        if ($isMultisite) {
            echo "(multisite) migrate blog id: " . $idBlog . "\n";
            if (!self::migrateMultisiteBlogTable()) {
                throw new RuntimeException('Cannot update wp_blogs table.');
            }
            if (switch_to_blog($idBlog) === false) {
                throw new RuntimeException('Cannot switch to blog id: ' . $idBlog);
            }
        }

        echo "starting migration from: \r\n";
        echo self::$oldSite . " > " . self::$newSite . "\r\n";

        self::migratePostmeta();
        self::migratePosts();
        self::migrateOptions();
        self::generateModRewrite();
        self::flushCaches();

        if ($isMultisite) {
            echo "please check wp-config.php constant 'DOMAIN_CURRENT_SITE' (this value must match to one of the pages)\n";
        }
    }

    /**
     * Migrates option values containing the old site URL.
     */
    private static function migrateOptions(): void
    {
        global $wpdb;
        $r = $wpdb->get_results("SELECT * FROM {$wpdb->options} p where option_value like '%" . self::$oldSite . "%'");
        foreach ($r as $option) {
            $newValue = self::replacer($option->option_value);
            if ($newValue != $option->option_value) {
                $wpdb->update($wpdb->options, ['option_value' => $newValue], ['option_id' => $option->option_id]);
                echo "option " . $option->option_name . " updated \r\n";
            }
        }
    }

    /**
     * Migrates postmeta values containing the old site URL.
     */
    private static function migratePostmeta(): void
    {
        global $wpdb;
        echo "migrate table: " . $wpdb->postmeta . "\n";
        $r = $wpdb->get_results("SELECT * FROM {$wpdb->postmeta} p where meta_value like '%" . self::$oldSite . "%'");
        foreach ($r as $meta) {
            $newValue = self::replacer($meta->meta_value);
            if ($newValue != $meta->meta_value) {
                $wpdb->update($wpdb->postmeta, ['meta_value' => $newValue], ['meta_id' => $meta->meta_id]);
                echo "postmeta " . $meta->meta_key . " updated \r\n";
            }
        }
    }

    /**
     * Migrates the multisite blogs table domain entry.
     */
    private static function migrateMultisiteBlogTable(): bool
    {
        global $wpdb;
        echo "update table: " . $wpdb->blogs . " (blog id: " . self::$idBlog . ") new site: " . self::$newSite . "\n";
        $url = trim(str_replace(['https://www.', 'https://'], ['', ''], self::$newSite), '/');
        $r = $wpdb->update($wpdb->blogs, ['domain' => $url], ['blog_id' => self::$idBlog]);
        return !empty($r);
    }

    /**
     * Migrates post content, title, excerpt, and guid fields.
     */
    private static function migratePosts(): void
    {
        global $wpdb;
        echo "migrate table: " . $wpdb->posts . "\n";
        $fields = ['post_content', 'post_title', 'post_excerpt', 'guid'];
        $query = [];
        $query[] = "SELECT * FROM {$wpdb->posts} p where 1=1 ";
        foreach ($fields as $field) {
            $query[] = $field . " like '%" . self::$oldSite . "%'";
        }

        $SQL = implode(' OR ', $query);
        $r = $wpdb->get_results($SQL);

        foreach ($r as $post) {
            foreach ($fields as $field) {
                $newValue = self::replacer($post->{$field});
                if ($newValue != $post->{$field}) {
                    $wpdb->update($wpdb->posts, [$field => $newValue], ['ID' => $post->ID]);
                    echo "post " . $field . " updated \r\n";
                }
            }
        }
    }

    /**
     * Replaces old site URL in a value, supports serialized data.
     */
    private static function replacer(string $value): string
    {
        if (is_serialized($value)) {
            $object = unserialize($value);
            $object = self::walkRecursive($object, [self::class, 'replace']);
            return serialize($object);
        } else {
            return self::replace($value);
        }
    }

    /**
     * Simple string replacement of old site URL with new site URL.
     */
    private static function replace(string $value): string
    {
        return str_replace(self::$oldSite, self::$newSite, $value);
    }

    /**
     * Recursively walks objects/arrays and applies a callback to all string values.
     *
     * @param mixed $obj
     * @param callable $closure
     * @return mixed
     */
    private static function walkRecursive($obj, callable $closure)
    {
        if (is_object($obj)) {
            $newObj = new stdClass();
            foreach ($obj as $property => $value) {
                $newProperty = $closure($property);
                $newValue = self::walkRecursive($value, $closure);
                $newObj->$newProperty = $newValue;
            }
            return $newObj;
        } else if (is_array($obj)) {
            $newArray = [];
            foreach ($obj as $key => $value) {
                $key = $closure($key);
                $newArray[$key] = self::walkRecursive($value, $closure);
            }
            return $newArray;
        } else {
            return $closure($obj);
        }
    }

    /**
     * Migrates theme config files (replaces SITEURL constant).
     */
    public static function migrateConfigFiles(): void
    {
        $file = get_theme_file_path() . '/config-theme.php';
        if (file_exists($file)) {
            $config = file_get_contents($file);
            $config = self::setConstant('SITEURL', self::$newSite, $config);
            file_put_contents($file, $config);
            echo "$file changed (SITE_URL)\r\n";
        }
    }

    /**
     * Sets a PHP constant value in a config file string.
     */
    private static function setConstant(string $constant, string $value, string $str): string
    {
        return preg_replace('/(define\(\'' . $constant . '\',\s*\')(.*)(\'\);)/', '$1' . $value . '$3', $str);
    }

    /**
     * Flushes caches after migration.
     */
    private static function flushCaches(): void
    {
        // Placeholder for cache flush implementations
    }

    /**
     * Regenerates WordPress .htaccess mod_rewrite rules.
     */
    private static function generateModRewrite(): void
    {
        global $wp_rewrite;

        // On CLI we cannot check if mod_rewrite is enabled, so we assume it is
        add_filter('got_rewrite', function () {
            return true;
        });

        save_mod_rewrite_rules();
        echo "new htaccess generated\r\n";
    }
}
