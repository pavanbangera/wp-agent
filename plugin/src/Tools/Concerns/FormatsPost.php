<?php

declare(strict_types=1);

namespace WpAgent\Tools\Concerns;

/**
 * Formats a WP_Post object into a normalized MCP-friendly array.
 *
 * Used by all post/page tools to ensure consistent output structure
 * regardless of the operation performed.
 *
 * @package WpAgent\Tools\Concerns
 * @since   0.1.0
 */
final class FormatsPost
{
    /**
     * Normalizes a WP_Post to a structured array for MCP tool output.
     *
     * @return array<string, mixed>
     */
    public static function format(\WP_Post $post): array
    {
        $author = get_userdata((int) $post->post_author);

        $result = [
            'id'              => $post->ID,
            'title'           => $post->post_title,
            'content'         => $post->post_content,
            'excerpt'         => $post->post_excerpt,
            'status'          => $post->post_status,
            'type'            => $post->post_type,
            'slug'            => $post->post_name,
            'permalink'       => get_permalink($post->ID) ?: '',
            'parent_id'       => (int) $post->post_parent,
            'menu_order'      => (int) $post->menu_order,
            'comment_status'  => $post->comment_status,
            'date'            => $post->post_date,
            'date_gmt'        => $post->post_date_gmt,
            'modified'        => $post->post_modified,
            'modified_gmt'    => $post->post_modified_gmt,
            'author'          => $author ? [
                'id'    => (int) $author->ID,
                'login' => $author->user_login,
                'name'  => $author->display_name,
            ] : null,
            'template'        => get_post_meta($post->ID, '_wp_page_template', true) ?: '',
            'featured_image'  => self::formatFeaturedImage($post->ID),
        ];

        // For pages: include hierarchical data.
        if ( $post->post_type === 'page' ) {
            $result['edit_url'] = get_edit_post_link($post->ID, 'raw') ?: '';
        }

        // For posts: include taxonomies.
        if ( $post->post_type === 'post' ) {
            $result['categories'] = self::formatCategories($post->ID);
            $result['tags']       = self::formatTags($post->ID);
            $result['format']     = get_post_format($post->ID) ?: 'standard';
        }

        return $result;
    }

    /**
     * Formats a post as a lightweight list item (for list operations).
     *
     * @return array<string, mixed>
     */
    public static function formatListItem(\WP_Post $post): array
    {
        return [
            'id'        => $post->ID,
            'title'     => $post->post_title,
            'status'    => $post->post_status,
            'slug'      => $post->post_name,
            'permalink' => get_permalink($post->ID) ?: '',
            'date'      => $post->post_date,
            'modified'  => $post->post_modified,
            'type'      => $post->post_type,
            'parent_id' => (int) $post->post_parent,
        ];
    }

    /**
     * Formats a revision post.
     *
     * @return array<string, mixed>
     */
    public static function formatRevision(\WP_Post $revision): array
    {
        $author = get_userdata((int) $revision->post_author);

        return [
            'id'        => $revision->ID,
            'parent_id' => (int) $revision->post_parent,
            'slug'      => $revision->post_name,
            'date'      => $revision->post_date,
            'author'    => $author ? [
                'id'   => (int) $author->ID,
                'name' => $author->display_name,
            ] : null,
        ];
    }

    // -------------------------------------------------------------------------
    // Private
    // -------------------------------------------------------------------------

    /**
     * @return array<string, mixed>|null
     */
    private static function formatFeaturedImage(int $postId): ?array
    {
        $thumbnailId = get_post_thumbnail_id($postId);

        if ( ! $thumbnailId ) {
            return null;
        }

        $imageData = wp_get_attachment_image_src($thumbnailId, 'full');

        if ( false === $imageData ) {
            return null;
        }

        return [
            'id'     => (int) $thumbnailId,
            'url'    => $imageData[0],
            'width'  => (int) $imageData[1],
            'height' => (int) $imageData[2],
            'alt'    => get_post_meta($thumbnailId, '_wp_attachment_image_alt', true) ?: '',
        ];
    }

    /**
     * @return array<int, array{id: int, name: string, slug: string}>
     */
    private static function formatCategories(int $postId): array
    {
        $categories = wp_get_post_categories($postId, ['fields' => 'all']);

        if ( is_wp_error($categories) || empty($categories) ) {
            return [];
        }

        return array_map(static fn (\WP_Term $term): array => [
            'id'   => $term->term_id,
            'name' => $term->name,
            'slug' => $term->slug,
        ], $categories);
    }

    /**
     * @return array<int, array{id: int, name: string, slug: string}>
     */
    private static function formatTags(int $postId): array
    {
        $tags = wp_get_post_tags($postId, ['fields' => 'all']);

        if ( is_wp_error($tags) || empty($tags) ) {
            return [];
        }

        return array_map(static fn (\WP_Term $term): array => [
            'id'   => $term->term_id,
            'name' => $term->name,
            'slug' => $term->slug,
        ], $tags);
    }
}
