<?php

declare(strict_types=1);

namespace WpAgent\Tools\SEO;

use WpAgent\Auth\Identity;
use WpAgent\MCP\Protocol\ToolResult;
use WpAgent\Services\SeoService;
use WpAgent\Tools\AbstractTool;

/**
 * Tool: wordpress.seo.sitemap.generate
 *
 * Flushes/regenerates sitemaps.
 *
 * Required scope: wp-agent:write
 * Required capability: manage_options
 *
 * @package WpAgent\Tools\SEO
 * @since   0.1.0
 */
final class GenerateSitemapTool extends AbstractTool
{
    public function __construct(
        private readonly SeoService $seoService,
    ) {}

    public function getName(): string
    {
        return 'wordpress.seo.sitemap.generate';
    }

    public function getDescription(): string
    {
        return 'Triggers sitemap updates or clears transient sitemap indexes caches.';
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

        $this->seoService->generateSitemap();

        return ToolResult::json([
            'success' => true,
            'message' => 'SEO XML sitemaps cache cleared successfully.',
        ]);
    }
}
