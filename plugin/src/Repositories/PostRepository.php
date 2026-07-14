<?php

declare(strict_types=1);

namespace WpAgent\Repositories;

use WpAgent\Exceptions\ToolException;
use WpAgent\Repositories\Contracts\PostRepositoryInterface;

/**
 * WordPress post repository.
 *
 * Concrete implementation over WordPress core post functions.
 * Handles both posts and pages — post type is passed as a parameter.
 *
 * All data access is through WordPress official APIs:
 * get_post(), wp_insert_post(), wp_update_post(), wp_delete_post(), WP_Query.
 *
 * @package WpAgent\Repositories
 * @since   0.1.0
 */
final class PostRepository implements PostRepositoryInterface
{
    private const TOOL_NAME = 'repository';

    /**
     * {@inheritDoc}
     */
    public function find(int $id, string $postType = 'any'): ?\WP_Post
    {
        $post = get_post($id);

        if ( ! ($post instanceof \WP_Post) ) {
            return null;
        }

        if ( $postType !== 'any' && $post->post_type !== $postType ) {
            return null;
        }

        return $post;
    }

    /**
     * {@inheritDoc}
     */
    public function findOrFail(int $id, string $postType = 'any'): \WP_Post
    {
        $post = $this->find($id, $postType);

        if ( null === $post ) {
            $label = $postType !== 'any' ? ucfirst($postType) : 'Post';
            throw ToolException::notFound(self::TOOL_NAME, $label, $id);
        }

        return $post;
    }

    /**
     * {@inheritDoc}
     */
    public function findAll(array $args): array
    {
        $defaults = [
            'post_status'    => 'any',
            'posts_per_page' => 20,
            'paged'          => 1,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ];

        $queryArgs = array_merge($defaults, $args);

        $query = new \WP_Query($queryArgs);

        return [
            'posts' => $query->posts ?? [],
            'total' => (int) $query->found_posts,
            'pages' => (int) $query->max_num_pages,
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function insert(array $data): \WP_Post
    {
        $data['meta_input'] = $data['meta_input'] ?? [];

        $postId = wp_insert_post($data, true);

        if ( is_wp_error($postId) ) {
            throw ToolException::fromWpError(self::TOOL_NAME, $postId);
        }

        if ( 0 === $postId ) {
            throw new ToolException('Post insertion returned ID 0.', self::TOOL_NAME);
        }

        return $this->findOrFail($postId);
    }

    /**
     * {@inheritDoc}
     */
    public function update(int $id, array $data): \WP_Post
    {
        $data['ID'] = $id;

        $postId = wp_update_post($data, true);

        if ( is_wp_error($postId) ) {
            throw ToolException::fromWpError(self::TOOL_NAME, $postId);
        }

        return $this->findOrFail($id);
    }

    /**
     * {@inheritDoc}
     */
    public function delete(int $id, bool $forceDelete = false): bool
    {
        // Verify post exists first.
        $this->findOrFail($id);

        $result = wp_delete_post($id, $forceDelete);

        if ( false === $result || null === $result ) {
            throw new ToolException(
                "Failed to delete post ID {$id}.",
                self::TOOL_NAME,
                ToolException::OPERATION_FAILED,
            );
        }

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function getRevisions(int $postId): array
    {
        $revisions = wp_get_post_revisions($postId, [
            'order'   => 'DESC',
            'orderby' => 'date',
        ]);

        return array_values($revisions);
    }

    /**
     * {@inheritDoc}
     */
    public function findRevision(int $revisionId): ?\WP_Post
    {
        $post = get_post($revisionId);

        if ( ! ($post instanceof \WP_Post) || $post->post_type !== 'revision' ) {
            return null;
        }

        return $post;
    }
}
