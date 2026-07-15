<?php

declare(strict_types=1);

namespace WpAgent\Services;

/**
 * Performance optimization service.
 *
 * Flushes caching engines, structures Core Web Vitals stubs, and bulk optimizes assets.
 *
 * @package WpAgent\Services
 * @since   0.1.0
 */
final class PerformanceService
{
    /**
     * Clears all detected WordPress and server cache layers.
     *
     * Covers: WP Super Cache, W3 Total Cache, WP Rocket, LiteSpeed Cache (object + page + CDN),
     * Autoptimize, Elementor CSS cache, WordPress rewrite rules, native object cache & transients.
     *
     * @return array<string, bool|string> Log of caching layers cleared.
     */
    public function clearCache(): array
    {
        $status = [];

        // 1. WP Super Cache.
        if ( function_exists('wp_cache_clear_cache') ) {
            wp_cache_clear_cache();
            $status['wp_super_cache'] = true;
        }

        // 2. W3 Total Cache.
        if ( function_exists('w3tc_flush_all') ) {
            w3tc_flush_all();
            $status['w3_total_cache'] = true;
        }

        // 3. WP Rocket — full page cache + CDN.
        if ( function_exists('rocket_clean_domain') ) {
            rocket_clean_domain();
            $status['wp_rocket'] = true;
        }

        // 4. LiteSpeed Cache — object cache.
        if ( has_action('litespeed_control_clean_all') ) {
            do_action('litespeed_control_clean_all');
            $status['litespeed_cache'] = true;
        }

        // 5. LiteSpeed Cache — full-page / HTML cache purge.
        if ( has_action('litespeed_purge_all') ) {
            do_action('litespeed_purge_all');
            $status['litespeed_page_cache'] = true;
        }

        // 6. LiteSpeed CDN / ESI edge cache (HCDN/Hostinger CDN).
        if ( has_action('litespeed_cdn_purge_all') ) {
            do_action('litespeed_cdn_purge_all');
            $status['litespeed_cdn'] = true;
        }

        // 7. Autoptimize.
        if ( class_exists('autoptimizeCache') && method_exists('autoptimizeCache', 'clearall') ) {
            \autoptimizeCache::clearall();
            $status['autoptimize'] = true;
        }

        // 8. Elementor CSS cache — delete per-page CSS transients and regenerate global CSS.
        if ( class_exists('\Elementor\Plugin') ) {
            $elementorCleared = false;

            if ( isset(\Elementor\Plugin::$instance->files_manager) ) {
                \Elementor\Plugin::$instance->files_manager->clear_cache();
                $elementorCleared = true;
            }

            // Also delete all elementor_css_file_* transients individually.
            global $wpdb;
            // phpcs:disable WordPress.DB.DirectDatabaseQuery
            $deleted = $wpdb->query(
                "DELETE FROM {$wpdb->options}
                 WHERE option_name LIKE '_transient_elementor_css_file_%'
                    OR option_name LIKE '_transient_timeout_elementor_css_file_%'"
            );
            // phpcs:enable

            if ( $deleted !== false ) {
                $elementorCleared = true;
            }

            $status['elementor_css_cache'] = $elementorCleared;
        }

        // 9. WordPress rewrite rules (flush .htaccess and rules cache).
        flush_rewrite_rules(true);
        $status['rewrite_rules'] = true;

        // 10. Native Object Cache & Transients.
        wp_cache_flush();
        $status['object_cache'] = true;

        return $status;
    }


    /**
     * Returns a stub performance audit score card.
     *
     * @return array<string, mixed>
     */
    public function runPerformanceAudit(): array
    {
        return [
            'performance' => 92,
            'accessibility' => 95,
            'best_practices' => 90,
            'seo' => 88,
            'metrics' => [
                'first_contentful_paint' => '1.2s',
                'largest_contentful_paint' => '2.4s',
                'cumulative_layout_shift' => '0.04',
                'total_blocking_time' => '150ms',
            ],
        ];
    }
}
