<?php

declare(strict_types=1);

namespace WpAgent\Repositories;

use WpAgent\Exceptions\ToolException;

/**
 * Repository for WordPress media attachments.
 *
 * Interacts directly with WP Core media APIs:
 * wp_insert_attachment(), wp_generate_attachment_metadata(), wp_update_attachment_metadata(),
 * and wp_delete_attachment().
 *
 * @package WpAgent\Repositories
 * @since   0.1.0
 */
final class MediaRepository
{
    private const TOOL_NAME = 'media_repository';

    /**
     * Finds an attachment by ID.
     */
    public function find(int $id): ?\WP_Post
    {
        $post = get_post($id);

        if ( ! ($post instanceof \WP_Post) || $post->post_type !== 'attachment' ) {
            return null;
        }

        return $post;
    }

    /**
     * Finds an attachment or throws.
     *
     * @throws ToolException
     */
    public function findOrFail(int $id): \WP_Post
    {
        $post = $this->find($id);

        if ( null === $post ) {
            throw ToolException::notFound(self::TOOL_NAME, 'Attachment', $id);
        }

        return $post;
    }

    /**
     * Inserts an attachment into the database and generates its metadata.
     *
     * @param string               $file     Absolute path to the file in uploads directory.
     * @param array<string, mixed> $postData Standard attachment post data.
     *
     * @return \WP_Post
     *
     * @throws ToolException
     */
    public function insert(string $file, array $postData): \WP_Post
    {
        $attachmentId = wp_insert_attachment($postData, $file);

        if ( is_wp_error($attachmentId) ) {
            throw ToolException::fromWpError(self::TOOL_NAME, $attachmentId);
        }

        if ( 0 === $attachmentId ) {
            throw new ToolException('Attachment insertion failed.', self::TOOL_NAME);
        }

        // Generate attachment metadata (resizes, image metadata like EXIF/IPTC).
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $metadata = wp_generate_attachment_metadata($attachmentId, $file);

        if ( ! is_wp_error($metadata) ) {
            wp_update_attachment_metadata($attachmentId, $metadata);
        }

        return $this->findOrFail($attachmentId);
    }

    /**
     * Deletes an attachment and all its physical file sizes.
     *
     * @param int  $id          Attachment ID.
     * @param bool $forceDelete Set to true to bypass trash (attachments bypass trash anyway in WP).
     *
     * @throws ToolException
     */
    public function delete(int $id, bool $forceDelete = true): bool
    {
        $this->findOrFail($id);

        // wp_delete_attachment deletes metadata, database entry, and physical files.
        $result = wp_delete_attachment($id, $forceDelete);

        if ( false === $result || null === $result ) {
            throw new ToolException(
                "Failed to delete attachment ID {$id}.",
                self::TOOL_NAME,
                ToolException::OPERATION_FAILED
            );
        }

        return true;
    }

    /**
     * Lists attachments with optional queries.
     *
     * @param array<string, mixed> $args
     *
     * @return array{posts: \WP_Post[], total: int, pages: int}
     */
    public function findAll(array $args): array
    {
        $defaults = [
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'posts_per_page' => 20,
            'paged'          => 1,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ];

        $query = new \WP_Query(array_merge($defaults, $args));

        return [
            'posts' => $query->posts ?? [],
            'total' => (int) $query->found_posts,
            'pages' => (int) $query->max_num_pages,
        ];
    }
}
