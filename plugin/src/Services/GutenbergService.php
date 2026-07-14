<?php

declare(strict_types=1);

namespace WpAgent\Services;

use WpAgent\Exceptions\ToolException;

/**
 * Gutenberg Block Editor integration service.
 *
 * Implements block tag generators, structural validation, and content injection.
 *
 * @package WpAgent\Services
 * @since   0.1.0
 */
final class GutenbergService
{
    private const TOOL_NAME = 'gutenberg_service';

    /**
     * Generates standard Gutenberg block markup.
     *
     * @param string               $blockName Block namespace (e.g. "core/paragraph").
     * @param array<string, mixed> $attrs     JSON parameters.
     * @param string               $innerHtml The inner HTML block text.
     */
    public function generateBlockMarkup(string $blockName, array $attrs = [], string $innerHtml = ''): string
    {
        $jsonAttrs = '';
        if ( ! empty($attrs) ) {
            $jsonAttrs = ' ' . wp_json_encode($attrs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        if ( empty($innerHtml) ) {
            // Self-closing block shorthand.
            return "<!-- wp:{$blockName}{$jsonAttrs} /-->\n";
        }

        return "<!-- wp:{$blockName}{$jsonAttrs} -->\n{$innerHtml}\n<!-- /wp:{$blockName} -->\n";
    }

    /**
     * Inserts block markup into an existing page content at a specific offset or position.
     *
     * @param int    $postId   The ID of the target page/post.
     * @param string $block    The block markup code.
     * @param string $position 'append' | 'prepend' | 'after_index'
     * @param int    $index    Position index when after_index is chosen.
     *
     * @throws ToolException
     */
    public function insertBlock(int $postId, string $block, string $position = 'append', int $index = 0): string
    {
        $post = get_post($postId);
        if ( ! ($post instanceof \WP_Post) ) {
            throw ToolException::notFound(self::TOOL_NAME, 'Post', $postId);
        }

        $content = $post->post_content;

        switch ( $position ) {
            case 'prepend':
                $newContent = $block . "\n" . $content;
                break;

            case 'after_index':
                // Parse existing blocks.
                if ( ! function_exists('parse_blocks') ) {
                    require_once ABSPATH . 'wp-includes/blocks.php';
                }
                $blocks = parse_blocks($content);

                $inserted = [];
                $found    = false;

                foreach ( $blocks as $i => $b ) {
                    $inserted[] = serialize_block($b);
                    if ( $i === $index ) {
                        $inserted[] = $block;
                        $found = true;
                    }
                }

                if ( ! $found ) {
                    $inserted[] = $block;
                }

                $newContent = implode("\n", array_filter($inserted));
                break;

            case 'append':
            default:
                $newContent = $content . "\n" . $block;
                break;
        }

        // Save updated content in DB.
        global $wpdb;
        $wpdb->update(
            $wpdb->posts,
            ['post_content' => $newContent],
            ['ID' => $postId]
        );

        // Clean post cache.
        clean_post_cache($postId);

        return $newContent;
    }

    /**
     * Creates and registers a new Block Pattern in the system.
     *
     * @throws ToolException
     */
    public function createPattern(string $title, string $slug, string $content, array $categories = ['general']): bool
    {
        if ( ! function_exists('register_block_pattern') ) {
            throw new ToolException('Block Patterns are not supported in this version of WordPress.', self::TOOL_NAME);
        }

        $slug = sanitize_key($slug);

        $result = register_block_pattern(
            $slug,
            [
                'title'      => sanitize_text_field($title),
                'content'    => $content,
                'categories' => array_map('sanitize_key', $categories),
            ]
        );

        if ( false === $result ) {
            throw new ToolException("Failed to register block pattern '{$slug}'.", self::TOOL_NAME);
        }

        return true;
    }

    /**
     * Creates a Gutenberg template or template part (stored in Custom CPTs).
     *
     * @param string $slug    Template slug (e.g. "single-custom").
     * @param string $type    'wp_template' | 'wp_template_part'
     * @param string $content Block editor content markup.
     * @param string $title   User friendly name.
     *
     * @return int Created Template Post ID.
     *
     * @throws ToolException
     */
    public function createTemplate(string $slug, string $type, string $content, string $title): int
    {
        $allowedTypes = ['wp_template', 'wp_template_part'];
        if ( ! in_array($type, $allowedTypes, true) ) {
            throw new ToolException("Invalid template CPT type '{$type}'.", self::TOOL_NAME, ToolException::INVALID_PARAMS);
        }

        $slug = sanitize_key($slug);

        // Check if template exists.
        $existing = get_posts([
            'post_type'   => $type,
            'name'        => $slug,
            'post_status' => 'any',
            'numberposts' => 1,
            'fields'      => 'ids',
        ]);

        $postData = [
            'post_type'    => $type,
            'post_name'    => $slug,
            'post_title'   => sanitize_text_field($title),
            'post_content' => $content,
            'post_status'  => 'publish',
        ];

        if ( ! empty($existing) ) {
            $postData['ID'] = (int) $existing[0];
            $postId         = wp_update_post($postData, true);
        } else {
            $postId = wp_insert_post($postData, true);
        }

        if ( is_wp_error($postId) ) {
            throw ToolException::fromWpError(self::TOOL_NAME, $postId);
        }

        return (int) $postId;
    }
}
