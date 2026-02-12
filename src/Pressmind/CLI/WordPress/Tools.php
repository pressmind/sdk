<?php

namespace Pressmind\CLI\WordPress;

use RuntimeException;

/**
 * Utility class for WordPress integration in CLI scripts.
 *
 * Provides centralized WordPress bootstrapping and common helper methods.
 * This class does NOT introduce a WordPress dependency to the SDK --
 * it only loads WordPress files when explicitly called from a Travelshop context.
 */
class Tools
{
    private static bool $booted = false;
    private static ?string $basePath = null;

    /**
     * Finds the WordPress base path by traversing up from a given directory.
     *
     * @param string|null $startDir Directory to start searching from (default: caller's directory)
     * @return string|null WordPress base path or null if not found
     */
    public static function findBasePath(?string $startDir = null): ?string
    {
        if (self::$basePath !== null) {
            return self::$basePath;
        }

        $dir = $startDir ?? getcwd();
        do {
            if (file_exists($dir . '/wp-config.php')) {
                self::$basePath = $dir;
                return $dir;
            }
        } while (($parent = realpath($dir . '/..')) !== false && $parent !== $dir && ($dir = $parent));

        return null;
    }

    /**
     * Boots WordPress in headless mode (no theme rendering).
     *
     * @param bool $loadAdmin Also load wp-admin includes (needed for $wpdb, update_option, etc.)
     * @throws RuntimeException If WordPress base path cannot be found
     */
    public static function boot(bool $loadAdmin = false): void
    {
        if (self::$booted) {
            return;
        }

        $basePath = self::findBasePath();
        if ($basePath === null) {
            throw new RuntimeException('WordPress base path not found (no wp-config.php in parent directories).');
        }

        if (!defined('WP_USE_THEMES')) {
            define('WP_USE_THEMES', false);
        }

        require_once $basePath . '/wp-load.php';

        if ($loadAdmin) {
            require_once $basePath . '/wp-admin/includes/admin.php';
        }

        self::$booted = true;
    }

    /**
     * Returns the WordPress site URL.
     * Requires boot() to have been called first.
     *
     * @return string
     * @throws RuntimeException If WordPress is not booted
     */
    public static function getSiteUrl(): string
    {
        self::ensureBooted();
        return site_url();
    }

    /**
     * Checks if WordPress has been booted.
     */
    public static function isBooted(): bool
    {
        return self::$booted;
    }

    /**
     * Deletes all WordPress transients from the options table.
     * Automatically boots WordPress with admin if not already booted.
     *
     * @return array{transients: int, site_transients: int} Number of deleted transients
     */
    public static function deleteTransients(): array
    {
        self::boot(true);

        global $wpdb;
        $transientCount = $wpdb->query("DELETE FROM `wp_options` WHERE `option_name` LIKE ('_transient_%');");
        $siteTransientCount = $wpdb->query("DELETE FROM `wp_options` WHERE `option_name` LIKE ('_site_transient_%')");

        return [
            'transients' => (int) $transientCount,
            'site_transients' => (int) $siteTransientCount,
        ];
    }

    /**
     * Sends a test e-mail via wp_mail() to verify the mail configuration.
     * Automatically boots WordPress if not already booted.
     *
     * @param string $to Recipient email address
     * @param bool $smtpDebug Enable PHPMailer SMTP debug output (level 3)
     * @return bool True if wp_mail() returned success
     * @throws RuntimeException If the email address is invalid
     */
    public static function sendTestEmail(string $to, bool $smtpDebug = false): bool
    {
        if (filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
            throw new RuntimeException('Invalid email address: ' . $to);
        }

        self::boot();

        add_action('wp_mail_failed', function ($error) {
            echo $error->get_error_message();
        });

        if ($smtpDebug) {
            add_action('phpmailer_init', function ($phpmailer) {
                $phpmailer->SMTPDebug = 3;
            });
        }

        return wp_mail($to, 'Test E-Mail ' . site_url(), 'Test E-Mail from ' . site_url()) === true;
    }

    /**
     * Ensures WordPress is booted, throws if not.
     *
     * @throws RuntimeException
     */
    private static function ensureBooted(): void
    {
        if (!self::$booted) {
            throw new RuntimeException('WordPress is not booted. Call Tools::boot() first.');
        }
    }
}
