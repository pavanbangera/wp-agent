<?php

declare(strict_types=1);

namespace WpAgent\Services;

use WpAgent\Exceptions\ToolException;
use WpAgent\Repositories\MediaRepository;

/**
 * Media business logic service.
 *
 * Implements base64 decoding, image conversion to WebP, compression,
 * file renaming on disk, and duplicate detection using MD5 checksums.
 *
 * @package WpAgent\Services
 * @since   0.1.0
 */
final class MediaService
{
    private const TOOL_NAME = 'media_service';

    public function __construct(
        private readonly MediaRepository $repository,
    ) {}

    /**
     * Uploads a media file from a base64 encoded string or raw bytes.
     *
     * @param string $filename     Target file name (e.g. "image.png").
     * @param string $base64Data   Base64 encoded file contents.
     * @param int    $parentPostId Optional post ID to attach the media to.
     *
     * @return \WP_Post
     *
     * @throws ToolException
     */
    public function uploadFromBase64(string $filename, string $base64Data, int $parentPostId = 0): \WP_Post
    {
        $decoded = base64_decode($base64Data, true);

        if ( false === $decoded ) {
            throw new ToolException('Invalid base64 payload.', self::TOOL_NAME, ToolException::INVALID_PARAMS);
        }

        // Determine upload dir.
        $uploadDir = wp_upload_dir();
        if ( ! empty($uploadDir['error']) ) {
            throw new ToolException('WP Upload directory error: ' . $uploadDir['error'], self::TOOL_NAME);
        }

        // Sanitize filename.
        $filename = sanitize_file_name($filename);
        $filepath = $uploadDir['path'] . '/' . wp_unique_filename($uploadDir['path'], $filename);

        // Write to disk.
        if ( false === file_put_contents($filepath, $decoded) ) {
            throw new ToolException("Failed to write file to path: {$filepath}", self::TOOL_NAME);
        }

        // Get file type.
        $filetype = wp_check_filetype($filepath, null);

        $attachmentData = [
            'guid'           => $uploadDir['url'] . '/' . basename($filepath),
            'post_mime_type' => $filetype['type'],
            'post_title'     => preg_replace('/\.[^.]+$/', '', basename($filepath)),
            'post_content'   => '',
            'post_status'    => 'inherit',
            'post_parent'    => $parentPostId,
        ];

        try {
            $attachment = $this->repository->insert($filepath, $attachmentData);

            // Store MD5 checksum for duplicate detection.
            $checksum = md5($decoded);
            update_post_meta($attachment->ID, '_wpa_checksum', $checksum);

            do_action('wpa_media_uploaded', $attachment);

            return $attachment;
        } catch (\Throwable $e) {
            if ( file_exists($filepath) ) {
                @unlink($filepath);
            }
            throw $e;
        }
    }

    /**
     * Replaces the physical file of an attachment without changing its ID.
     *
     * @throws ToolException
     */
    public function replaceFile(int $attachmentId, string $base64Data): \WP_Post
    {
        $attachment = $this->repository->findOrFail($attachmentId);
        $oldFile    = get_attached_file($attachmentId);

        if ( ! $oldFile || ! file_exists($oldFile) ) {
            throw new ToolException("Physical file not found for attachment {$attachmentId}.", self::TOOL_NAME);
        }

        $decoded = base64_decode($base64Data, true);
        if ( false === $decoded ) {
            throw new ToolException('Invalid base64 payload.', self::TOOL_NAME, ToolException::INVALID_PARAMS);
        }

        // Backup old file just in case.
        $backupFile = $oldFile . '.bak';
        @copy($oldFile, $backupFile);

        // Delete old sub-sizes to clean up disk.
        $this->deleteSubSizes($attachmentId);

        // Overwrite main file.
        if ( false === file_put_contents($oldFile, $decoded) ) {
            @copy($backupFile, $oldFile);
            @unlink($backupFile);
            throw new ToolException("Failed to write replacement file content to {$oldFile}.", self::TOOL_NAME);
        }

        @unlink($backupFile);

        // Regenerate metadata and sub-sizes.
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $metadata = wp_generate_attachment_metadata($attachmentId, $oldFile);

        if ( ! is_wp_error($metadata) ) {
            wp_update_attachment_metadata($attachmentId, $metadata);
        }

        // Update checksum.
        update_post_meta($attachmentId, '_wpa_checksum', md5($decoded));

        do_action('wpa_media_replaced', $attachment);

        return $attachment;
    }

    /**
     * Renames an attachment post and its physical file on disk.
     *
     * @throws ToolException
     */
    public function renameFile(int $attachmentId, string $newFilename): \WP_Post
    {
        $attachment = $this->repository->findOrFail($attachmentId);
        $oldFile    = get_attached_file($attachmentId);

        if ( ! $oldFile || ! file_exists($oldFile) ) {
            throw new ToolException("Physical file not found for attachment {$attachmentId}.", self::TOOL_NAME);
        }

        $newFilename = sanitize_file_name($newFilename);

        // Keep the extension from the original file.
        $ext = pathinfo($oldFile, PATHINFO_EXTENSION);
        if ( ! str_ends_with(strtolower($newFilename), '.' . strtolower($ext)) ) {
            $newFilename .= '.' . $ext;
        }

        $dir     = dirname($oldFile);
        $newFile = $dir . '/' . wp_unique_filename($dir, $newFilename);

        // Clean up old sizes first (they will be regenerated with the new filename).
        $this->deleteSubSizes($attachmentId);

        if ( ! @rename($oldFile, $newFile) ) {
            throw new ToolException("Failed to rename physical file from '{$oldFile}' to '{$newFile}'.", self::TOOL_NAME);
        }

        // Update database references.
        update_attached_file($attachmentId, $newFile);

        // Update GUID and Title.
        $uploadDir = wp_upload_dir();
        $newGuid   = $uploadDir['url'] . '/' . basename($newFile);
        $newTitle  = preg_replace('/\.[^.]+$/', '', basename($newFile));

        global $wpdb;
        $wpdb->update(
            $wpdb->posts,
            [
                'guid'       => $newGuid,
                'post_title' => $newTitle,
                'post_name'  => sanitize_title($newTitle),
            ],
            ['ID' => $attachmentId],
            ['%s', '%s', '%s'],
            ['%d']
        );

        // Regenerate meta/sub-sizes for the renamed file.
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $metadata = wp_generate_attachment_metadata($attachmentId, $newFile);

        if ( ! is_wp_error($metadata) ) {
            wp_update_attachment_metadata($attachmentId, $metadata);
        }

        do_action('wpa_media_renamed', $attachmentId, $newFile);

        return $this->repository->findOrFail($attachmentId);
    }

    /**
     * Converts a PNG or JPG attachment to WebP format.
     *
     * @throws ToolException
     */
    public function convertToWebp(int $attachmentId): \WP_Post
    {
        $attachment = $this->repository->findOrFail($attachmentId);
        $file       = get_attached_file($attachmentId);

        if ( ! $file || ! file_exists($file) ) {
            throw new ToolException("File not found.", self::TOOL_NAME);
        }

        $image = wp_get_image_editor($file);

        if ( is_wp_error($image) ) {
            throw ToolException::fromWpError(self::TOOL_NAME, $image);
        }

        $ext      = pathinfo($file, PATHINFO_EXTENSION);
        $webpFile = preg_replace('/\.' . preg_quote($ext, '/') . '$/i', '.webp', $file);

        // Delete old sub-sizes.
        $this->deleteSubSizes($attachmentId);

        // Save as WebP.
        $saveResult = $image->save($webpFile, 'image/webp');

        if ( is_wp_error($saveResult) ) {
            throw ToolException::fromWpError(self::TOOL_NAME, $saveResult);
        }

        // Delete old main file.
        @unlink($file);

        // Update database records.
        update_attached_file($attachmentId, $webpFile);

        global $wpdb;
        $uploadDir = wp_upload_dir();
        $newGuid   = $uploadDir['url'] . '/' . basename($webpFile);

        $wpdb->update(
            $wpdb->posts,
            [
                'guid'           => $newGuid,
                'post_mime_type' => 'image/webp',
            ],
            ['ID' => $attachmentId]
        );

        // Regenerate metadata.
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $metadata = wp_generate_attachment_metadata($attachmentId, $webpFile);

        if ( ! is_wp_error($metadata) ) {
            wp_update_attachment_metadata($attachmentId, $metadata);
        }

        return $this->repository->findOrFail($attachmentId);
    }

    /**
     * Compresses an image by adjusting quality settings.
     *
     * @throws ToolException
     */
    public function compressImage(int $attachmentId, int $quality = 82): \WP_Post
    {
        $attachment = $this->repository->findOrFail($attachmentId);
        $file       = get_attached_file($attachmentId);

        if ( ! $file || ! file_exists($file) ) {
            throw new ToolException("Image file not found.", self::TOOL_NAME);
        }

        $image = wp_get_image_editor($file);

        if ( is_wp_error($image) ) {
            throw ToolException::fromWpError(self::TOOL_NAME, $image);
        }

        $image->set_quality($quality);

        // Save overwriting the main file.
        $saveResult = $image->save($file);

        if ( is_wp_error($saveResult) ) {
            throw ToolException::fromWpError(self::TOOL_NAME, $saveResult);
        }

        // Regenerate sizes.
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $metadata = wp_generate_attachment_metadata($attachmentId, $file);

        if ( ! is_wp_error($metadata) ) {
            wp_update_attachment_metadata($attachmentId, $metadata);
        }

        return $attachment;
    }

    /**
     * Scans and returns duplicate attachments based on MD5 checksums.
     *
     * @return array<string, int[]> Map of checksum -> list of attachment IDs.
     */
    public function detectDuplicates(): array
    {
        global $wpdb;

        // Query all attachments with checksum metadata.
        // phpcs:disable WordPress.DB.DirectDatabaseQuery
        $results = $wpdb->get_results(
            "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_wpa_checksum'"
        );
        // phpcs:enable

        $checksumMap = [];
        foreach ( $results as $row ) {
            $checksumMap[$row->meta_value][] = (int) $row->post_id;
        }

        // Filter out groups with only 1 attachment (non-duplicates).
        return array_filter($checksumMap, static fn (array $ids): bool => count($ids) > 1);
    }

    // -------------------------------------------------------------------------
    // Private Helpers
    // -------------------------------------------------------------------------

    /**
     * Deletes all cropped thumbnail/intermediate sub-sizes from disk.
     */
    private function deleteSubSizes(int $attachmentId): void
    {
        $meta = wp_get_attachment_metadata($attachmentId);

        if ( empty($meta['sizes']) ) {
            return;
        }

        $file      = get_attached_file($attachmentId);
        $baseDir   = dirname($file) . '/';

        foreach ( $meta['sizes'] as $sizeInfo ) {
            if ( ! empty($sizeInfo['file']) ) {
                $subFile = $baseDir . $sizeInfo['file'];
                if ( file_exists($subFile) ) {
                    @unlink($subFile);
                }
            }
        }
    }
}
