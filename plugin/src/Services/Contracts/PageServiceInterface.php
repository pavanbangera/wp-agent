<?php

declare(strict_types=1);

namespace WpAgent\Services\Contracts;

/**
 * Contract for the Page service.
 *
 * Encapsulates all WordPress page business logic above the repository layer.
 * Methods here enforce capability checks, fire appropriate hooks, and return
 * normalized data structures for MCP tool consumption.
 *
 * @package WpAgent\Services\Contracts
 * @since   0.1.0
 */
interface PageServiceInterface
{
    /**
     * Creates a new page.
     *
     * @param array<string, mixed> $data Page creation data.
     *
     * @return \WP_Post Newly created page.
     *
     * @throws \WpAgent\Exceptions\ToolException On failure.
     */
    public function create(array $data): \WP_Post;

    /**
     * Updates an existing page.
     *
     * @param int                  $pageId Page ID.
     * @param array<string, mixed> $data   Fields to update.
     *
     * @return \WP_Post Updated page.
     */
    public function update(int $pageId, array $data): \WP_Post;

    /**
     * Deletes a page (trash by default).
     *
     * @param int  $pageId      Page ID.
     * @param bool $forceDelete Permanently delete if true.
     */
    public function delete(int $pageId, bool $forceDelete = false): bool;

    /**
     * Duplicates a page, creating a draft copy.
     *
     * @param int    $pageId      Source page ID.
     * @param string $titleSuffix Suffix appended to title (default: " (Copy)").
     *
     * @return \WP_Post Duplicated page.
     */
    public function duplicate(int $pageId, string $titleSuffix = ' (Copy)'): \WP_Post;

    /**
     * Publishes a page (sets status to 'publish').
     *
     * @param int $pageId Page ID.
     *
     * @return \WP_Post Updated page.
     */
    public function publish(int $pageId): \WP_Post;

    /**
     * Schedules a page for future publication.
     *
     * @param int    $pageId      Page ID.
     * @param string $publishDate ISO 8601 date string (e.g. '2025-12-31T09:00:00').
     *
     * @return \WP_Post Updated page.
     */
    public function schedule(int $pageId, string $publishDate): \WP_Post;

    /**
     * Lists pages with optional filters.
     *
     * @param array<string, mixed> $filters Query filters.
     *
     * @return array{posts: \WP_Post[], total: int, pages: int}
     */
    public function list(array $filters = []): array;

    /**
     * Gets a single page by ID.
     *
     * @param int $pageId Page ID.
     *
     * @return \WP_Post
     *
     * @throws \WpAgent\Exceptions\ToolException If not found.
     */
    public function get(int $pageId): \WP_Post;

    /**
     * Returns revisions for a page.
     *
     * @param int $pageId Page ID.
     *
     * @return \WP_Post[] Revisions.
     */
    public function getRevisions(int $pageId): array;

    /**
     * Restores a page to a specific revision.
     *
     * @param int $pageId     Page ID.
     * @param int $revisionId Revision post ID.
     *
     * @return \WP_Post Restored page.
     */
    public function restoreRevision(int $pageId, int $revisionId): \WP_Post;
}
