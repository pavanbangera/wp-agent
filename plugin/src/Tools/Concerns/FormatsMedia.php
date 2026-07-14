<?php

declare(strict_types=1);

namespace WpAgent\Tools\Concerns;

/**
 * Normalizes WP_Post media objects to consistent MCP outputs.
 *
 * @package WpAgent\Tools\Concerns
 * @since   0.1.0
 */
final class FormatsMedia
{
    /**
     * Normalizes a media attachment post.
     *
     * @return array<string, mixed>
     */
    public static function format(\WP_Post $attachment): array
    {
        $metadata    = wp_get_attachment_metadata($attachment->ID);
        $src         = wp_get_attachment_image_src($attachment->ID, 'full');
        $altText     = get_post_meta($attachment->ID, '_wp_attachment_image_alt', true) ?: '';
        $checksum    = get_post_meta($attachment->ID, '_wpa_checksum', true) ?: '';
        $attachedFile = get_attached_file($attachment->ID);

        return [
            'id'             => $attachment->ID,
            'title'          => $attachment->post_title,
            'filename'       => basename($attachedFile ?: ''),
            'mime_type'      => $attachment->post_mime_type,
            'url'            => $src ? $src[0] : ( $attachment->guid ?: '' ),
            'alt_text'       => $altText,
            'dimensions'     => $src ? [
                'width'  => (int) $src[1],
                'height' => (int) $src[2],
            ] : null,
            'file_size'      => $attachedFile && file_exists($attachedFile) ? filesize($attachedFile) : 0,
            'checksum'       => $checksum,
            'parent_post_id' => (int) $attachment->post_parent,
            'date_uploaded'  => $attachment->post_date,
            'metadata'       => is_array($metadata) ? $metadata : null,
        ];
    }

    /**
     * Normalizes media items as a lightweight list item.
     *
     * @return array<string, mixed>
     */
    public static function formatListItem(\WP_Post $attachment): array
    {
        $src         = wp_get_attachment_image_src($attachment->ID, 'medium');
        $attachedFile = get_attached_file($attachment->ID);

        return [
            'id'        => $attachment->ID,
            'title'     => $attachment->post_title,
            'filename'  => basename($attachedFile ?: ''),
            'mime_type' => $attachment->post_mime_type,
            'url'       => $src ? $src[0] : ( $attachment->guid ?: '' ),
            'date'      => $attachment->post_date,
        ];
    }
}
