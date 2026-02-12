<?php

namespace Pressmind\CLI\WordPress;

/**
 * WordPress Image Regeneration
 *
 * Regenerates WordPress attachment image derivatives (thumbnails).
 * Supports the pressmind Stateless S3 plugin for remote images.
 *
 * Requires WordPress to be booted via Tools::boot().
 *
 * Usage:
 *   RegenerateImages::run();           // all attachments
 *   RegenerateImages::run(123);        // single attachment
 *   RegenerateImages::getAllImageSizes(); // list registered sizes
 */
class RegenerateImages
{
    /**
     * Returns all registered WordPress image sizes (default + additional).
     *
     * @return array<string, array{width: int, height: int, crop: bool|array}>
     */
    public static function getAllImageSizes(): array
    {
        Tools::boot();

        global $_wp_additional_image_sizes;

        $imageSizes = [];
        $defaultImageSizes = get_intermediate_image_sizes();

        foreach ($defaultImageSizes as $size) {
            $imageSizes[$size]['width'] = (int) get_option("{$size}_size_w");
            $imageSizes[$size]['height'] = (int) get_option("{$size}_size_h");
            $imageSizes[$size]['crop'] = get_option("{$size}_crop") ? get_option("{$size}_crop") : false;
        }

        if (isset($_wp_additional_image_sizes) && count($_wp_additional_image_sizes)) {
            $imageSizes = array_merge($imageSizes, $_wp_additional_image_sizes);
        }

        return $imageSizes;
    }

    /**
     * Returns all attachment IDs of type image.
     *
     * @return int[]
     */
    public static function getAllUploads(): array
    {
        Tools::boot();

        global $wpdb;
        $attachments = $wpdb->get_results("SELECT ID FROM {$wpdb->posts} p WHERE post_type = 'attachment' and post_mime_type like 'image/%'");
        return array_map(function ($o) {
            return $o->ID;
        }, $attachments);
    }

    /**
     * Regenerates image derivatives for one or all attachments.
     *
     * @param int|null $postId Single attachment ID, or null for all
     * @param callable|null $onThemeActivation Optional callback to set thumbnail sizes before processing.
     *                                         Receives no arguments. Use e.g.: function() { (new ThemeActivation())->setThumbnailsizes(); }
     */
    public static function run(?int $postId = null, ?callable $onThemeActivation = null): void
    {
        Tools::boot();

        // Load wp_crop_image if not available
        if (!function_exists('wp_crop_image')) {
            include(ABSPATH . 'wp-admin/includes/image.php');
        }

        // Allow caller to set up thumbnail sizes (e.g. ThemeActivation)
        if ($onThemeActivation !== null) {
            $onThemeActivation();
        }

        if ($postId !== null) {
            $attachments = [$postId];
        } else {
            $attachments = self::getAllUploads();
        }

        global $PM_Stateless;

        foreach ($attachments as $attachmentId) {
            $file = get_attached_file($attachmentId);

            // Support pressmind Stateless S3 Plugin
            if (!empty($PM_Stateless)) {
                $file = self::downloadFromS3IfNeeded($attachmentId, $file);
            }

            if (empty($file) || !file_exists($file)) {
                echo "> error: file not found: " . $file . "\r\n";
                continue;
            }

            echo "> regenerate image for post/attachment id: " . $attachmentId . ' (' . basename($file) . ")\r\n";

            $meta = wp_generate_attachment_metadata($attachmentId, $file);
            wp_update_attachment_metadata($attachmentId, $meta);
        }
    }

    /**
     * Downloads an attachment from S3 if it is stored remotely.
     *
     * @return string Local file path
     */
    private static function downloadFromS3IfNeeded(int $postId, string $file): string
    {
        $isOnS3 = (bool) get_post_meta($postId, 'is_on_s3', true);
        if ($isOnS3 === true) {
            $url = wp_get_attachment_image_url($postId);
            $uploadDir = wp_upload_dir();
            set_time_limit(0);
            $file = $uploadDir['basedir'] . '/' . basename($file);
            $fp = fopen($file, 'w+');
            $ch = curl_init(str_replace(" ", "%20", $url));
            curl_setopt($ch, CURLOPT_TIMEOUT, 50);
            curl_setopt($ch, CURLOPT_FILE, $fp);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_exec($ch);
            curl_close($ch);
            fclose($fp);
        }
        return $file;
    }
}
