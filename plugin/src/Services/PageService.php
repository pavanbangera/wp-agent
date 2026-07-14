<?php

declare(strict_types=1);

namespace WpAgent\Services;

use WpAgent\Exceptions\ToolException;
use WpAgent\Repositories\Contracts\PostRepositoryInterface;
use WpAgent\Services\Contracts\PageServiceInterface;

/**
 * Page business logic service.
 *
 * Handles all page-level operations using the official WordPress API.
 * Keeps tools thin by centralizing all page logic here.
 *
 * @package WpAgent\Services
 * @since   0.1.0
 */
final class PageService implements PageServiceInterface
{
    private const POST_TYPE = 'page';
    private const TOOL_NAME = 'page_service';

    public function __construct(
        private readonly PostRepositoryInterface $repository,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function create(array $data): \WP_Post
    {
        $insertData = array_merge(
            [
                'post_type'    => self::POST_TYPE,
                'post_status'  => 'draft',
                'post_author'  => get_current_user_id(),
            ],
            $this->mapInputToWpPost($data)
        );

        $page = $this->repository->insert($insertData);

        // Handle SEO meta if provided (Yoast/RankMath/AIOSEO).
        if ( ! empty($data['seo_title']) || ! empty($data['seo_description']) ) {
            $this->setSeoMeta($page->ID, $data);
        }

        // Set template if provided.
        if ( ! empty($data['template']) ) {
            update_post_meta($page->ID, '_wp_page_template', sanitize_text_field($data['template']));
        }

        /**
         * Fires after WP Agent creates a page.
         *
         * @param \WP_Post             $page Page that was created.
         * @param array<string, mixed> $data Original creation data.
         *
         * @since 0.1.0
         */
        do_action('wpa_page_created', $page, $data);

        return $page;
    }

    /**
     * {@inheritDoc}
     */
    public function update(int $pageId, array $data): \WP_Post
    {
        // Verify page exists.
        $this->repository->findOrFail($pageId, self::POST_TYPE);

        $updateData = $this->mapInputToWpPost($data);

        $page = $this->repository->update($pageId, $updateData);

        if ( ! empty($data['seo_title']) || ! empty($data['seo_description']) ) {
            $this->setSeoMeta($pageId, $data);
        }

        if ( array_key_exists('template', $data) ) {
            update_post_meta($pageId, '_wp_page_template', sanitize_text_field($data['template'] ?? ''));
        }

        do_action('wpa_page_updated', $page, $data);

        return $page;
    }

    /**
     * {@inheritDoc}
     */
    public function delete(int $pageId, bool $forceDelete = false): bool
    {
        $this->repository->findOrFail($pageId, self::POST_TYPE);

        $result = $this->repository->delete($pageId, $forceDelete);

        do_action('wpa_page_deleted', $pageId, $forceDelete);

        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function duplicate(int $pageId, string $titleSuffix = ' (Copy)'): \WP_Post
    {
        $original = $this->repository->findOrFail($pageId, self::POST_TYPE);

        // Copy core post data.
        $newData = [
            'post_type'    => self::POST_TYPE,
            'post_title'   => $original->post_title . $titleSuffix,
            'post_content' => $original->post_content,
            'post_excerpt' => $original->post_excerpt,
            'post_status'  => 'draft',
            'post_author'  => get_current_user_id(),
            'post_parent'  => $original->post_parent,
            'menu_order'   => $original->menu_order,
            'comment_status' => $original->comment_status,
        ];

        $newPage = $this->repository->insert($newData);

        // Copy all post meta.
        $this->copyPostMeta($pageId, $newPage->ID);

        // Copy taxonomies (page categories, tags, custom).
        $this->copyTaxonomies($pageId, $newPage->ID);

        do_action('wpa_page_duplicated', $newPage, $original);

        return $newPage;
    }

    /**
     * {@inheritDoc}
     */
    public function publish(int $pageId): \WP_Post
    {
        $this->repository->findOrFail($pageId, self::POST_TYPE);

        $page = $this->repository->update($pageId, [
            'post_status' => 'publish',
            'post_date'   => current_time('mysql'),
            'post_date_gmt' => current_time('mysql', true),
        ]);

        do_action('wpa_page_published', $page);

        return $page;
    }

    /**
     * {@inheritDoc}
     */
    public function schedule(int $pageId, string $publishDate): \WP_Post
    {
        $this->repository->findOrFail($pageId, self::POST_TYPE);

        // Parse and validate the date.
        $timestamp = strtotime($publishDate);
        if ( false === $timestamp ) {
            throw new ToolException(
                "Invalid publish date format: '{$publishDate}'. Use ISO 8601 (e.g. 2025-12-31T09:00:00).",
                self::TOOL_NAME,
                ToolException::OPERATION_FAILED,
            );
        }

        if ( $timestamp <= time() ) {
            throw new ToolException(
                'Scheduled date must be in the future.',
                self::TOOL_NAME,
                ToolException::OPERATION_FAILED,
            );
        }

        $page = $this->repository->update($pageId, [
            'post_status'   => 'future',
            'post_date'     => gmdate('Y-m-d H:i:s', $timestamp),
            'post_date_gmt' => gmdate('Y-m-d H:i:s', $timestamp),
        ]);

        do_action('wpa_page_scheduled', $page, $publishDate);

        return $page;
    }

    /**
     * {@inheritDoc}
     */
    public function list(array $filters = []): array
    {
        $args = array_merge(
            ['post_type' => self::POST_TYPE],
            $this->mapFiltersToQuery($filters)
        );

        return $this->repository->findAll($args);
    }

    /**
     * {@inheritDoc}
     */
    public function get(int $pageId): \WP_Post
    {
        return $this->repository->findOrFail($pageId, self::POST_TYPE);
    }

    /**
     * {@inheritDoc}
     */
    public function getRevisions(int $pageId): array
    {
        $this->repository->findOrFail($pageId, self::POST_TYPE);

        return $this->repository->getRevisions($pageId);
    }

    /**
     * {@inheritDoc}
     */
    public function restoreRevision(int $pageId, int $revisionId): \WP_Post
    {
        $this->repository->findOrFail($pageId, self::POST_TYPE);

        $revision = $this->repository->findRevision($revisionId);

        if ( null === $revision ) {
            throw ToolException::notFound(self::TOOL_NAME, 'Revision', $revisionId);
        }

        // Verify revision belongs to this page.
        if ( (int) $revision->post_parent !== $pageId ) {
            throw new ToolException(
                "Revision {$revisionId} does not belong to page {$pageId}.",
                self::TOOL_NAME,
                ToolException::OPERATION_FAILED,
            );
        }

        // WordPress built-in restore function.
        $restored = wp_restore_post_revision($revisionId);

        if ( ! $restored ) {
            throw new ToolException(
                "Failed to restore revision {$revisionId}.",
                self::TOOL_NAME,
                ToolException::OPERATION_FAILED,
            );
        }

        $page = $this->repository->findOrFail($pageId, self::POST_TYPE);

        do_action('wpa_page_revision_restored', $page, $revision);

        return $page;
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Maps tool input keys to wp_insert_post keys.
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function mapInputToWpPost(array $data): array
    {
        $mapped = [];

        $fieldMap = [
            'title'          => 'post_title',
            'content'        => 'post_content',
            'excerpt'        => 'post_excerpt',
            'status'         => 'post_status',
            'slug'           => 'post_name',
            'parent_id'      => 'post_parent',
            'menu_order'     => 'menu_order',
            'comment_status' => 'comment_status',
            'ping_status'    => 'ping_status',
            'password'       => 'post_password',
        ];

        foreach ( $fieldMap as $inputKey => $wpKey ) {
            if ( array_key_exists($inputKey, $data) ) {
                $mapped[$wpKey] = $data[$inputKey];
            }
        }

        return $mapped;
    }

    /**
     * Maps filter input to WP_Query arguments.
     *
     * @param array<string, mixed> $filters
     *
     * @return array<string, mixed>
     */
    private function mapFiltersToQuery(array $filters): array
    {
        $args = [];

        if ( ! empty($filters['status']) ) {
            $args['post_status'] = sanitize_text_field($filters['status']);
        } else {
            $args['post_status'] = ['publish', 'draft', 'private', 'future', 'pending'];
        }

        if ( ! empty($filters['search']) ) {
            $args['s'] = sanitize_text_field($filters['search']);
        }

        if ( ! empty($filters['parent_id']) ) {
            $args['post_parent'] = (int) $filters['parent_id'];
        }

        if ( ! empty($filters['per_page']) ) {
            $args['posts_per_page'] = min((int) $filters['per_page'], 100);
        } else {
            $args['posts_per_page'] = 20;
        }

        if ( ! empty($filters['page']) ) {
            $args['paged'] = max(1, (int) $filters['page']);
        }

        if ( ! empty($filters['orderby']) ) {
            $allowed = ['date', 'title', 'menu_order', 'modified', 'ID'];
            $args['orderby'] = in_array($filters['orderby'], $allowed, true)
                ? $filters['orderby']
                : 'date';
        }

        if ( ! empty($filters['order']) ) {
            $args['order'] = strtoupper($filters['order']) === 'ASC' ? 'ASC' : 'DESC';
        }

        return $args;
    }

    /**
     * Copies all custom meta from source to destination post.
     */
    private function copyPostMeta(int $sourceId, int $destId): void
    {
        $meta = get_post_meta($sourceId);

        if ( empty($meta) ) {
            return;
        }

        // Skip internal WordPress meta that should not be copied.
        $skipKeys = ['_edit_lock', '_edit_last', '_wp_old_slug'];

        foreach ( $meta as $key => $values ) {
            if ( in_array($key, $skipKeys, true) ) {
                continue;
            }

            foreach ( $values as $value ) {
                // Values are stored serialized in get_post_meta with single=false.
                add_post_meta($destId, $key, maybe_unserialize($value));
            }
        }
    }

    /**
     * Copies taxonomy assignments from source to destination post.
     */
    private function copyTaxonomies(int $sourceId, int $destId): void
    {
        $taxonomies = get_object_taxonomies(self::POST_TYPE);

        foreach ( $taxonomies as $taxonomy ) {
            $terms = wp_get_object_terms($sourceId, $taxonomy, ['fields' => 'ids']);

            if ( ! is_wp_error($terms) && ! empty($terms) ) {
                wp_set_object_terms($destId, $terms, $taxonomy);
            }
        }
    }

    /**
     * Sets SEO meta fields based on which plugin is active.
     *
     * @param array<string, mixed> $data
     */
    private function setSeoMeta(int $postId, array $data): void
    {
        $seoTitle       = sanitize_text_field($data['seo_title'] ?? '');
        $seoDescription = sanitize_text_field($data['seo_description'] ?? '');

        // Yoast SEO.
        if ( defined('WPSEO_VERSION') ) {
            if ( $seoTitle ) {
                update_post_meta($postId, '_yoast_wpseo_title', $seoTitle);
            }
            if ( $seoDescription ) {
                update_post_meta($postId, '_yoast_wpseo_metadesc', $seoDescription);
            }
            return;
        }

        // RankMath.
        if ( defined('RANK_MATH_VERSION') ) {
            if ( $seoTitle ) {
                update_post_meta($postId, 'rank_math_title', $seoTitle);
            }
            if ( $seoDescription ) {
                update_post_meta($postId, 'rank_math_description', $seoDescription);
            }
            return;
        }

        // AIOSEO.
        if ( class_exists('\AIOSEO\Plugin\AIOSEO') ) {
            if ( $seoTitle ) {
                update_post_meta($postId, '_aioseo_title', $seoTitle);
            }
            if ( $seoDescription ) {
                update_post_meta($postId, '_aioseo_description', $seoDescription);
            }
            return;
        }

        // Fallback: generic meta fields.
        if ( $seoTitle ) {
            update_post_meta($postId, '_wpa_seo_title', $seoTitle);
        }
        if ( $seoDescription ) {
            update_post_meta($postId, '_wpa_seo_description', $seoDescription);
        }
    }
}
