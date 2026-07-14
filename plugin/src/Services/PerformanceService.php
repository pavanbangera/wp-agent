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
     * @return array<string, bool> Log of caching plugins cleared.
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

        // 3. WP Rocket.
        if ( function_exists('rocket_clean_domain') ) {
            rocket_clean_domain();
            $status['wp_rocket'] = true;
        }

        // 4. LiteSpeed Cache.
        if ( has_action('litespeed_control_clean_all') ) {
            do_action('litespeed_control_clean_all');
            $status['litespeed_cache'] = true;
        }

        // 5. Autoptimize.
        if ( class_exists('autoptimizeCache') && method_exists('autoptimizeCache', 'clearall') ) {
            \autoptimizeCache::clearall();
            $status['autoptimize'] = true;
        }

        // 6. Native Object Cache & Transients.
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
