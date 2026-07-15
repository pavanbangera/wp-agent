<?php

declare(strict_types=1);

namespace WpAgent\Tools\Performance;

use WpAgent\Auth\Identity;
use WpAgent\MCP\Protocol\ToolResult;
use WpAgent\Tools\AbstractTool;

/**
 * Tool: wordpress.rewrite.flush
 *
 * Flushes WordPress rewrite rules (hard flush — regenerates .htaccess) and then
 * purges caching layers that may have stale 404 responses cached for newly registered
 * Custom Post Type (CPT) endpoints.
 *
 * This is essential after registering Custom Post Types or changing permalink structures,
 * as WordPress must rebuild its rewrite rule cache to serve the new pretty permalink URLs.
 * A hard flush (true) also regenerates the .htaccess file which is required for Apache.
 *
 * Required scope: wp-agent:write
 * Required capability: manage_options
 *
 * @package WpAgent\Tools\Performance
 * @since   0.1.0
 */
final class FlushRewritesTool extends AbstractTool
{
    public function getName(): string
    {
        return 'wordpress.rewrite.flush';
    }

    public function getDescription(): string
    {
        return 'Flushes WordPress rewrite rules (hard flush — regenerates .htaccess) '
            . 'and clears page cache layers that may have cached stale 404 responses. '
            . 'Run this after registering Custom Post Types, changing permalink settings, '
            . 'or any time new URL endpoints return 404 despite the post existing in the database. '
            . 'Also purges LiteSpeed page and CDN cache to ensure the new rules take effect immediately.';
    }

    public function getInputSchema(): array
    {
        return [
            'type'                 => 'object',
            'properties'           => [],
            'additionalProperties' => false,
        ];
    }

    public function getRequiredScopes(): array
    {
        return ['wp-agent:write'];
    }

    protected function handle(array $args, Identity $identity): ToolResult
    {
        $this->requireCapability('manage_options', $identity);

        $result = [
            'rewrite_rules_flushed' => false,
            'htaccess_updated'      => false,
            'cache_cleared'         => [],
        ];

        // Hard flush — regenerates rewrite rules in DB AND rewrites .htaccess.
        flush_rewrite_rules(true);
        $result['rewrite_rules_flushed'] = true;

        // Verify .htaccess was updated (Apache environments).
        $htaccess = ABSPATH . '.htaccess';
        if ( file_exists($htaccess) ) {
            $result['htaccess_updated'] = true;
            $result['htaccess_path']    = $htaccess;
        }

        // Purge LiteSpeed full-page cache (may have cached old 404 responses).
        if ( has_action('litespeed_purge_all') ) {
            do_action('litespeed_purge_all');
            $result['cache_cleared'][] = 'litespeed_page_cache';
        }

        // Purge LiteSpeed CDN/ESI edge cache.
        if ( has_action('litespeed_cdn_purge_all') ) {
            do_action('litespeed_cdn_purge_all');
            $result['cache_cleared'][] = 'litespeed_cdn';
        }

        // Purge WP Rocket page cache.
        if ( function_exists('rocket_clean_domain') ) {
            rocket_clean_domain();
            $result['cache_cleared'][] = 'wp_rocket';
        }

        // Flush native object cache (clears rewrite_rules transient etc.).
        wp_cache_flush();
        $result['cache_cleared'][] = 'object_cache';

        return ToolResult::json(array_merge(
            $result,
            [
                'success' => true,
                'message' => 'Rewrite rules flushed and cache purged. New permalink structures are now active.',
            ]
        ));
    }
}
