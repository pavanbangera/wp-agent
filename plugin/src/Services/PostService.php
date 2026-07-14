<?php

declare(strict_types=1);

namespace WpAgent\Services;

use WpAgent\Exceptions\ToolException;
use WpAgent\Repositories\Contracts\PostRepositoryInterface;
use WpAgent\Services\Contracts\PostServiceInterface;

/**
 * Post business logic service.
 *
 * Handles all post-type operations. By default operates on the 'post'
 * post type but can be used for any non-page CPT when injected with
 * an appropriate repository.
 *
 * @package WpAgent\Services
 * @since   0.1.0
 */
final class PostService implements PostServiceInterface
{
    private const POST_TYPE = 'post';
    private const TOOL_NAME = 'post_service';

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
                'post_type'   => self::POST_TYPE,
                'post_status' => 'draft',
                'post_author' => get_current_user_id(),
            ],
            $this->mapInputToWpPost($data)
        );

        $post = $this->repository->insert($insertData);

        // Featured image.
        if ( ! empty($data['featured_image_id']) ) {
            set_post_thumbnail($post->ID, (int) $data['featured_image_id']);
        }

        // Categories.
        if ( ! empty($data['categories']) ) {
            wp_set_post_categories($post->ID, array_map('intval', (array) $data['categories']));
        }

        // Tags.
        if ( ! empty($data['tags']) ) {
            wp_set_post_tags($post->ID, (array) $data['tags']);
        }

        // SEO meta.
        if ( ! empty($data['seo_title']) || ! empty($data['seo_description']) ) {
            $this->setSeoMeta($post->ID, $data);
        }

        /**
         * Fires after WP Agent creates a post.
         *
         * @param \WP_Post             $post Created post.
         * @param array<string, mixed> $data Original data.
         *
         * @since 0.1.0
         */
        do_action('wpa_post_created', $post, $data);

        return $post;
    }

    /**
     * {@inheritDoc}
     */
    public function update(int $postId, array $data): \WP_Post
    {
        $this->repository->findOrFail($postId, self::POST_TYPE);

        $updateData = $this->mapInputToWpPost($data);
        $post       = $this->repository->update($postId, $updateData);

        if ( array_key_exists('featured_image_id', $data) ) {
            $attachmentId = (int) $data['featured_image_id'];
            if ( $attachmentId > 0 ) {
                set_post_thumbnail($postId, $attachmentId);
            } else {
                delete_post_thumbnail($postId);
            }
        }

        if ( array_key_exists('categories', $data) ) {
            wp_set_post_categories($postId, array_map('intval', (array) $data['categories']));
        }

        if ( array_key_exists('tags', $data) ) {
            wp_set_post_tags($postId, (array) $data['tags']);
        }

        if ( ! empty($data['seo_title']) || ! empty($data['seo_description']) ) {
            $this->setSeoMeta($postId, $data);
        }

        do_action('wpa_post_updated', $post, $data);

        return $post;
    }

    /**
     * {@inheritDoc}
     */
    public function delete(int $postId, bool $forceDelete = false): bool
    {
        $this->repository->findOrFail($postId, self::POST_TYPE);

        $result = $this->repository->delete($postId, $forceDelete);

        do_action('wpa_post_deleted', $postId, $forceDelete);

        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function get(int $postId): \WP_Post
    {
        return $this->repository->findOrFail($postId, self::POST_TYPE);
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
    public function setFeaturedImage(int $postId, int $attachmentId): bool
    {
        $this->repository->findOrFail($postId, self::POST_TYPE);

        if ( $attachmentId === 0 ) {
            return (bool) delete_post_thumbnail($postId);
        }

        // Verify attachment exists.
        if ( ! wp_attachment_is_image($attachmentId) ) {
            throw new ToolException(
                "Attachment ID {$attachmentId} is not a valid image.",
                self::TOOL_NAME,
                ToolException::RESOURCE_NOT_FOUND,
            );
        }

        $result = set_post_thumbnail($postId, $attachmentId);

        if ( false === $result ) {
            throw new ToolException(
                "Failed to set featured image {$attachmentId} on post {$postId}.",
                self::TOOL_NAME,
                ToolException::OPERATION_FAILED,
            );
        }

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function manageCategories(int $postId, array $categoryIds, string $mode = 'set'): array
    {
        $this->repository->findOrFail($postId, self::POST_TYPE);

        $categoryIds = array_map('intval', $categoryIds);

        switch ( $mode ) {
            case 'add':
                $current = wp_get_post_categories($postId, ['fields' => 'ids']);
                $merged  = array_unique(array_merge($current, $categoryIds));
                wp_set_post_categories($postId, $merged);
                break;

            case 'remove':
                $current = wp_get_post_categories($postId, ['fields' => 'ids']);
                $updated = array_diff($current, $categoryIds);
                wp_set_post_categories($postId, array_values($updated));
                break;

            case 'set':
            default:
                wp_set_post_categories($postId, $categoryIds);
                break;
        }

        return wp_get_post_categories($postId, ['fields' => 'ids']);
    }

    /**
     * {@inheritDoc}
     */
    public function manageTags(int $postId, array $tags, string $mode = 'set'): array
    {
        $this->repository->findOrFail($postId, self::POST_TYPE);

        switch ( $mode ) {
            case 'add':
                wp_set_post_tags($postId, $tags, append: true);
                break;

            case 'remove':
                $current   = wp_get_post_tags($postId, ['fields' => 'names']);
                $remaining = array_diff($current, $tags);
                wp_set_post_tags($postId, $remaining);
                break;

            case 'set':
            default:
                wp_set_post_tags($postId, $tags);
                break;
        }

        $resultTags = wp_get_post_tags($postId, ['fields' => 'names']);

        return is_wp_error($resultTags) ? [] : $resultTags;
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
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
            'format'         => 'post_format',
        ];

        foreach ( $fieldMap as $inputKey => $wpKey ) {
            if ( array_key_exists($inputKey, $data) ) {
                $mapped[$wpKey] = $data[$inputKey];
            }
        }

        return $mapped;
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return array<string, mixed>
     */
    private function mapFiltersToQuery(array $filters): array
    {
        $args = [];

        $args['post_status'] = ! empty($filters['status'])
            ? sanitize_text_field($filters['status'])
            : ['publish', 'draft', 'private', 'future', 'pending'];

        if ( ! empty($filters['search']) ) {
            $args['s'] = sanitize_text_field($filters['search']);
        }

        if ( ! empty($filters['category_id']) ) {
            $args['cat'] = (int) $filters['category_id'];
        }

        if ( ! empty($filters['tag']) ) {
            $args['tag'] = sanitize_text_field($filters['tag']);
        }

        if ( ! empty($filters['author_id']) ) {
            $args['author'] = (int) $filters['author_id'];
        }

        $args['posts_per_page'] = ! empty($filters['per_page'])
            ? min((int) $filters['per_page'], 100)
            : 20;

        $args['paged'] = ! empty($filters['page'])
            ? max(1, (int) $filters['page'])
            : 1;

        $allowedOrderby = ['date', 'title', 'modified', 'ID', 'comment_count', 'rand'];
        $args['orderby'] = ! empty($filters['orderby']) && in_array($filters['orderby'], $allowedOrderby, true)
            ? $filters['orderby']
            : 'date';

        $args['order'] = ! empty($filters['order']) && strtoupper($filters['order']) === 'ASC'
            ? 'ASC'
            : 'DESC';

        return $args;
    }

    /**
     * Sets SEO meta for the post. Supports Yoast, RankMath, AIOSEO.
     *
     * @param array<string, mixed> $data
     */
    private function setSeoMeta(int $postId, array $data): void
    {
        $seoTitle       = sanitize_text_field($data['seo_title'] ?? '');
        $seoDescription = sanitize_text_field($data['seo_description'] ?? '');

        if ( defined('WPSEO_VERSION') ) {
            if ( $seoTitle ) {
                update_post_meta($postId, '_yoast_wpseo_title', $seoTitle);
            }
            if ( $seoDescription ) {
                update_post_meta($postId, '_yoast_wpseo_metadesc', $seoDescription);
            }
        } elseif ( defined('RANK_MATH_VERSION') ) {
            if ( $seoTitle ) {
                update_post_meta($postId, 'rank_math_title', $seoTitle);
            }
            if ( $seoDescription ) {
                update_post_meta($postId, 'rank_math_description', $seoDescription);
            }
        } else {
            if ( $seoTitle ) {
                update_post_meta($postId, '_wpa_seo_title', $seoTitle);
            }
            if ( $seoDescription ) {
                update_post_meta($postId, '_wpa_seo_description', $seoDescription);
            }
        }
    }
}
