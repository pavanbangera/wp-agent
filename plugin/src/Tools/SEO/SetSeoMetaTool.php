<?php

declare(strict_types=1);

namespace WpAgent\Tools\SEO;

use WpAgent\Auth\Identity;
use WpAgent\Exceptions\ToolException;
use WpAgent\MCP\Protocol\ToolResult;
use WpAgent\Tools\AbstractTool;

/**
 * Tool: wordpress.seo.meta.set
 *
 * Configures the SEO meta title and description for a post/page.
 *
 * Required scope: wp-agent:write
 * Required capability: edit_posts
 *
 * @package WpAgent\Tools\SEO
 * @since   0.1.0
 */
final class SetSeoMetaTool extends AbstractTool
{
    public function getName(): string
    {
        return 'wordpress.seo.meta.set';
    }

    public function getDescription(): string
    {
        return 'Configures SEO titles and meta descriptions for posts/pages (compatible with Yoast, RankMath, and fallbacks).';
    }

    public function getInputSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'post_id'     => [
                    'type'        => 'integer',
                    'description' => 'The post or page ID.',
                    'minimum'     => 1,
                ],
                'title'       => [
                    'type'        => 'string',
                    'description' => 'The custom SEO meta title.',
                ],
                'description' => [
                    'type'        => 'string',
                    'description' => 'The custom SEO meta description text.',
                ],
            ],
            'required'             => ['post_id'],
            'additionalProperties' => false,
        ];
    }

    public function getRequiredScopes(): array
    {
        return ['wp-agent:write'];
    }

    protected function handle(array $args, Identity $identity): ToolResult
    {
        $this->requireCapability('edit_posts', $identity);

        $postId = (int) $args['post_id'];
        $post   = get_post($postId);

        if ( ! ($post instanceof \WP_Post) ) {
            throw ToolException::notFound(self::TOOL_NAME, 'Post', $postId);
        }

        // Yoast hooks.
        if ( defined('WPSEO_VERSION') ) {
            if ( isset($args['title']) ) {
                update_post_meta($postId, '_yoast_wpseo_title', sanitize_text_field($args['title']));
            }
            if ( isset($args['description']) ) {
                update_post_meta($postId, '_yoast_wpseo_metadesc', sanitize_text_field($args['description']));
            }
        }

        // RankMath.
        if ( class_exists('RankMath') ) {
            if ( isset($args['title']) ) {
                update_post_meta($postId, 'rank_math_title', sanitize_text_field($args['title']));
            }
            if ( isset($args['description']) ) {
                update_post_meta($postId, 'rank_math_description', sanitize_text_field($args['description']));
            }
        }

        // Fallback meta.
        if ( isset($args['title']) ) {
            update_post_meta($postId, '_wpa_seo_title', sanitize_text_field($args['title']));
        }
        if ( isset($args['description']) ) {
            update_post_meta($postId, '_wpa_seo_description', sanitize_text_field($args['description']));
        }

        return ToolResult::json([
            'success' => true,
            'post_id' => $postId,
            'title'   => $args['title'] ?? null,
            'desc'    => $args['description'] ?? null,
            'message' => 'SEO meta tags configured successfully.',
        ]);
    }
}
