<?php

declare(strict_types=1);

namespace WpAgent\Tools\Performance;

use WpAgent\Auth\Identity;
use WpAgent\MCP\Protocol\ToolResult;
use WpAgent\Services\PerformanceService;
use WpAgent\Tools\AbstractTool;

/**
 * Tool: wordpress.performance.cache.clear
 *
 * Flushes W3TC, WP Rocket, Super Cache, LiteSpeed, and Transients object caches.
 *
 * Required scope: wp-agent:write
 * Required capability: manage_options
 *
 * @package WpAgent\Tools\Performance
 * @since   0.1.0
 */
final class ClearCacheTool extends AbstractTool
{
    public function __construct(
        private readonly PerformanceService $performanceService,
    ) {}

    public function getName(): string
    {
        return 'wordpress.performance.cache.clear';
    }

    public function getDescription(): string
    {
        return 'Purges all detected site-wide caches (caching plugins, transients, object caches).';
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

        $cleared = $this->performanceService->clearCache();

        return ToolResult::json([
            'success' => true,
            'cleared_engines' => $cleared,
            'message' => 'Caching layers flushed successfully.',
        ]);
    }
}
