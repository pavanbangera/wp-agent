<?php

declare(strict_types=1);

namespace WpAgent\Services\Contracts;

/**
 * Contract for the Post service.
 *
 * @package WpAgent\Services\Contracts
 * @since   0.1.0
 */
interface PostServiceInterface
{
    /**
     * Creates a new post.
     *
     * @param array<string, mixed> $data Post data.
     *
     * @return \WP_Post
     */
    public function create(array $data): \WP_Post;

    /**
     * Updates an existing post.
     *
     * @param int                  $postId Post ID.
     * @param array<string, mixed> $data   Fields to update.
     *
     * @return \WP_Post
     */
    public function update(int $postId, array $data): \WP_Post;

    /**
     * Deletes a post (trash by default).
     *
     * @param int  $postId      Post ID.
     * @param bool $forceDelete Permanently delete if true.
     */
    public function delete(int $postId, bool $forceDelete = false): bool;

    /**
     * Gets a single post by ID.
     *
     * @param int $postId Post ID.
     *
     * @return \WP_Post
     */
    public function get(int $postId): \WP_Post;

    /**
     * Lists posts with optional filters.
     *
     * @param array<string, mixed> $filters
     *
     * @return array{posts: \WP_Post[], total: int, pages: int}
     */
    public function list(array $filters = []): array;

    /**
     * Sets the featured image (thumbnail) for a post.
     *
     * @param int $postId      Post ID.
     * @param int $attachmentId Attachment ID. 0 to remove.
     *
     * @return bool
     */
    public function setFeaturedImage(int $postId, int $attachmentId): bool;

    /**
     * Manages categories for a post (set, add, or remove).
     *
     * @param int      $postId     Post ID.
     * @param int[]    $categoryIds Category IDs to assign.
     * @param string   $mode       'set' | 'add' | 'remove'
     *
     * @return int[] Resulting category IDs.
     */
    public function manageCategories(int $postId, array $categoryIds, string $mode = 'set'): array;

    /**
     * Manages tags for a post (set, add, or remove).
     *
     * @param int      $postId Post ID.
     * @param string[] $tags   Tag names or slugs.
     * @param string   $mode   'set' | 'add' | 'remove'
     *
     * @return string[] Resulting tag names.
     */
    public function manageTags(int $postId, array $tags, string $mode = 'set'): array;
}
