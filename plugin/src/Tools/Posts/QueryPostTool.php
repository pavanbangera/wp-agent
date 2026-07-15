<?php

declare(strict_types=1);

namespace WpAgent\Tools\Posts;

use WpAgent\Auth\Identity;
use WpAgent\MCP\Protocol\ToolResult;
use WpAgent\Tools\AbstractTool;
use WpAgent\Tools\Concerns\FormatsPost;

/**
 * Tool: wordpress.post.query
 *
 * Flexible post finder with support for querying any post type by title, slug,
 * status, meta key/value, and pagination. Eliminates the need to write raw PHP
 * for common "does this post/template exist?" verification tasks.
 *
 * Required scope: wp-agent:read
 * Required capability: read
 *
 * @package WpAgent\Tools\Posts
 * @since   0.1.0
 */
final class QueryPostTool extends AbstractTool
{
    public function getName(): string
    {
        return 'wordpress.post.query';
    }

    public function getDescription(): string
    {
        return 'Queries posts of any type using flexible filters: post_type, title, slug, status, '
            . 'meta_key/meta_value, author_id, and pagination. Useful for verifying whether a CPT post, '
            . 'Elementor template, or page exists before creating it. '
            . 'Returns a list of matching posts with their ID, title, slug, status, URL, and date.';
    }

    public function getInputSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'post_type'  => [
                    'type'        => 'string',
                    'description' => 'Post type slug (e.g. "post", "page", "elementor_library", "my-cpt"). Default: "any".',
                    'default'     => 'any',
                ],
                'title'      => [
                    'type'        => 'string',
                    'description' => 'Exact post title to search for.',
                ],
                'slug'       => [
                    'type'        => 'string',
                    'description' => 'Exact post slug (post_name) to search for.',
                ],
                'status'     => [
                    'type'        => 'string',
                    'description' => 'Post status filter. Default: "any".',
                    'enum'        => ['any', 'publish', 'draft', 'private', 'future', 'pending', 'trash', 'inherit'],
                    'default'     => 'any',
                ],
                'meta_key'   => [
                    'type'        => 'string',
                    'description' => 'Filter by meta key (requires meta_value).',
                ],
                'meta_value' => [
                    'type'        => 'string',
                    'description' => 'Filter by meta value (used together with meta_key).',
                ],
                'author_id'  => [
                    'type'        => 'integer',
                    'description' => 'Filter by author user ID.',
                    'minimum'     => 1,
                ],
                'per_page'   => [
                    'type'        => 'integer',
                    'description' => 'Number of results per page. Default: 20, max: 100.',
                    'minimum'     => 1,
                    'maximum'     => 100,
                    'default'     => 20,
                ],
                'page'       => [
                    'type'        => 'integer',
                    'description' => 'Page number for pagination. Default: 1.',
                    'minimum'     => 1,
                    'default'     => 1,
                ],
                'orderby'    => [
                    'type'        => 'string',
                    'enum'        => ['date', 'title', 'modified', 'ID', 'rand'],
                    'default'     => 'date',
                ],
                'order'      => [
                    'type'        => 'string',
                    'enum'        => ['ASC', 'DESC'],
                    'default'     => 'DESC',
                ],
            ],
            'additionalProperties' => false,
        ];
    }

    public function getRequiredScopes(): array
    {
        return ['wp-agent:read'];
    }

    public function getAnnotations(): array
    {
        return [
            'readOnlyHint'   => true,
            'idempotentHint' => true,
        ];
    }

    protected function handle(array $args, Identity $identity): ToolResult
    {
        $this->requireCapability('read', $identity);

        $postType = $args['post_type'] ?? 'any';
        $status   = $args['status']    ?? 'any';
        $perPage  = (int) ($args['per_page'] ?? 20);
        $page     = (int) ($args['page']     ?? 1);
        $orderby  = $args['orderby']   ?? 'date';
        $order    = $args['order']     ?? 'DESC';

        $queryArgs = [
            'post_type'      => $postType,
            'post_status'    => $status,
            'posts_per_page' => $perPage,
            'paged'          => $page,
            'orderby'        => $orderby,
            'order'          => $order,
            'no_found_rows'  => false,
        ];

        // Title filter — WP_Query's 'title' performs an exact match on post_title.
        if ( ! empty($args['title']) ) {
            $queryArgs['title'] = $args['title'];
        }

        // Slug filter.
        if ( ! empty($args['slug']) ) {
            $queryArgs['name'] = sanitize_title($args['slug']);
        }

        // Meta query.
        if ( ! empty($args['meta_key']) ) {
            $queryArgs['meta_key']   = sanitize_key($args['meta_key']);
            $queryArgs['meta_value'] = $args['meta_value'] ?? '';
        }

        // Author filter.
        if ( ! empty($args['author_id']) ) {
            $queryArgs['author'] = (int) $args['author_id'];
        }

        $query = new \WP_Query($queryArgs);

        $posts = array_map(
            static fn (\WP_Post $p): array => [
                'id'       => $p->ID,
                'title'    => $p->post_title,
                'slug'     => $p->post_name,
                'status'   => $p->post_status,
                'type'     => $p->post_type,
                'url'      => get_permalink($p->ID) ?: '',
                'date'     => $p->post_date,
                'modified' => $p->post_modified,
            ],
            $query->posts
        );

        return ToolResult::json([
            'posts'        => $posts,
            'total'        => (int) $query->found_posts,
            'total_pages'  => (int) $query->max_num_pages,
            'per_page'     => $perPage,
            'current_page' => $page,
        ]);
    }
}
