<?php

declare(strict_types=1);

namespace WpAgent\Services;

use WpAgent\Exceptions\ToolException;

/**
 * SEO management service.
 *
 * Integrates with Yoast SEO, RankMath, and AIOSEO.
 *
 * @package WpAgent\Services
 * @since   0.1.0
 */
final class SeoService
{
    private const TOOL_NAME = 'seo_service';

    /**
     * Sets Open Graph tags for a post/page.
     *
     * @param int                  $postId The post or page ID.
     * @param array<string, mixed> $data   OG meta values.
     *
     * @throws ToolException
     */
    public function setOgMeta(int $postId, array $data): bool
    {
        $post = get_post($postId);
        if ( ! ($post instanceof \WP_Post) ) {
            throw ToolException::notFound(self::TOOL_NAME, 'Post', $postId);
        }

        // Set Yoast OG metas if active.
        if ( defined('WPSEO_VERSION') ) {
            if ( isset($data['title']) ) {
                update_post_meta($postId, '_yoast_wpseo_opengraph-title', sanitize_text_field($data['title']));
            }
            if ( isset($data['description']) ) {
                update_post_meta($postId, '_yoast_wpseo_opengraph-description', sanitize_text_field($data['description']));
            }
            if ( isset($data['image_url']) ) {
                update_post_meta($postId, '_yoast_wpseo_opengraph-image', esc_url_raw($data['image_url']));
            }
        }

        // Set RankMath OG metas if active.
        if ( class_exists('RankMath') ) {
            if ( isset($data['title']) ) {
                update_post_meta($postId, 'rank_math_facebook_title', sanitize_text_field($data['title']));
            }
            if ( isset($data['description']) ) {
                update_post_meta($postId, 'rank_math_facebook_description', sanitize_text_field($data['description']));
            }
            if ( isset($data['image_url']) ) {
                update_post_meta($postId, 'rank_math_facebook_image', esc_url_raw($data['image_url']));
            }
        }

        // Always save to fallback meta tags.
        if ( isset($data['title']) ) {
            update_post_meta($postId, '_wpa_og_title', sanitize_text_field($data['title']));
        }
        if ( isset($data['description']) ) {
            update_post_meta($postId, '_wpa_og_description', sanitize_text_field($data['description']));
        }
        if ( isset($data['image_url']) ) {
            update_post_meta($postId, '_wpa_og_image', esc_url_raw($data['image_url']));
        }

        return true;
    }

    /**
     * Configures custom schema markup payload.
     *
     * @throws ToolException
     */
    public function setSchemaMarkup(int $postId, array $schema): bool
    {
        $post = get_post($postId);
        if ( ! ($post instanceof \WP_Post) ) {
            throw ToolException::notFound(self::TOOL_NAME, 'Post', $postId);
        }

        update_post_meta($postId, '_wpa_schema_markup', wp_json_encode($schema));

        return true;
    }

    /**
     * Clears and invalidates SEO sitemaps.
     */
    public function generateSitemap(): bool
    {
        // Flush Yoast sitemaps.
        if ( class_exists('WPSEO_Sitemaps') ) {
            do_action('wpseo_invalidate_sitemaps_list');
        }

        // Flush RankMath sitemaps.
        if ( class_exists('RankMath\Sitemap\Sitemap') ) {
            do_action('rank_math/sitemap/invalidate');
        }

        return true;
    }

    /**
     * Performs a basic local SEO audit of a post content.
     *
     * @return array<string, mixed>
     */
    public function runSeoAudit(int $postId): array
    {
        $post = get_post($postId);
        if ( ! ($post instanceof \WP_Post) ) {
            return ['error' => "Post ID {$postId} not found."];
        }

        $content = $post->post_content;
        $title   = $post->post_title;

        $warnings = [];
        $passes   = [];

        // Check H1 tag.
        $h1Count = preg_match_all('/<h1[^>]*>/i', $content, $matches);
        if ( $h1Count > 0 ) {
            $warnings[] = 'H1 tags found in content. H1 is typically reserved for the post title only.';
        } else {
            $passes[] = 'No nested H1 tags found in content.';
        }

        // Check Title Length.
        $titleLen = strlen($title);
        if ( $titleLen < 30 || $titleLen > 60 ) {
            $warnings[] = "Title length ({$titleLen} chars) is outside the recommended 30-60 characters range.";
        } else {
            $passes[] = 'Title length is optimal.';
        }

        // Check Alt texts in images.
        if ( preg_match_all('/<img[^>]+>/i', $content, $images) ) {
            $missingAlt = 0;
            foreach ( $images[0] as $img ) {
                if ( ! preg_match('/alt\s*=\s*["\'][^"\']+["\']/i', $img) ) {
                    $missingAlt++;
                }
            }

            if ( $missingAlt > 0 ) {
                $warnings[] = "{$missingAlt} image(s) in content are missing descriptive alt attributes.";
            } else {
                $passes[] = 'All images in content have alt attributes.';
            }
        }

        return [
            'post_id'  => $postId,
            'score'    => count($passes) * 25,
            'passed'   => $passes,
            'warnings' => $warnings,
        ];
    }
}
