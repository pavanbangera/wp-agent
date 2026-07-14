<?php

declare(strict_types=1);

namespace WpAgent\Repositories\Contracts;

/**
 * Contract for the post/page repository.
 *
 * Provides a WordPress-aware abstraction over WP_Query and direct
 * post manipulation functions. Keeps services decoupled from WP globals.
 *
 * @package WpAgent\Repositories\Contracts
 * @since   0.1.0
 */
interface PostRepositoryInterface
{
    /**
     * Finds a single post by ID.
     *
     * @param int    $id       Post ID.
     * @param string $postType Expected post type ('any' to skip type check).
     *
     * @return \WP_Post|null Null if not found or wrong type.
     */
    public function find(int $id, string $postType = 'any'): ?\WP_Post;

    /**
     * Finds a post and throws if not found.
     *
     * @param int    $id       Post ID.
     * @param string $postType Expected post type.
     *
     * @return \WP_Post
     *
     * @throws \WpAgent\Exceptions\ToolException If post not found.
     */
    public function findOrFail(int $id, string $postType = 'any'): \WP_Post;

    /**
     * Returns a paginated list of posts.
     *
     * @param array<string, mixed> $args WP_Query-compatible arguments.
     *
     * @return array{posts: \WP_Post[], total: int, pages: int}
     */
    public function findAll(array $args): array;

    /**
     * Inserts a new post.
     *
     * @param array<string, mixed> $data wp_insert_post-compatible data.
     *
     * @return \WP_Post Newly created post.
     *
     * @throws \WpAgent\Exceptions\ToolException On failure.
     */
    public function insert(array $data): \WP_Post;

    /**
     * Updates an existing post.
     *
     * @param int                  $id   Post ID.
     * @param array<string, mixed> $data Fields to update.
     *
     * @return \WP_Post Updated post.
     *
     * @throws \WpAgent\Exceptions\ToolException On failure.
     */
    public function update(int $id, array $data): \WP_Post;

    /**
     * Deletes a post (moves to trash by default).
     *
     * @param int  $id          Post ID.
     * @param bool $forceDelete Bypass trash and permanently delete.
     *
     * @return bool True on success.
     *
     * @throws \WpAgent\Exceptions\ToolException On failure.
     */
    public function delete(int $id, bool $forceDelete = false): bool;

    /**
     * Returns all revisions for a post.
     *
     * @param int $postId Post ID.
     *
     * @return \WP_Post[] Revision posts, newest first.
     */
    public function getRevisions(int $postId): array;

    /**
     * Returns a single revision by ID.
     *
     * @param int $revisionId Revision post ID.
     *
     * @return \WP_Post|null
     */
    public function findRevision(int $revisionId): ?\WP_Post;
}
