<?php

declare(strict_types=1);

namespace WpAgent\Tools\Performance;

use WpAgent\Auth\Identity;
use WpAgent\MCP\Protocol\ToolResult;
use WpAgent\Services\PerformanceService;
use WpAgent\Tools\AbstractTool;

/**
 * Tool: wordpress.performance.lighthouse
 *
 * Runs a performance audit score card (Lighthouse simulation).
 *
 * Required scope: wp-agent:read
 * Required capability: manage_options
 *
 * @package WpAgent\Tools\Performance
 * @since   0.1.0
 */
final class RunLighthouseTool extends AbstractTool
{
    public function __construct(
        private readonly PerformanceService $performanceService,
    ) {}

    public function getName(): string
    {
        return 'wordpress.performance.lighthouse';
    }

    public function getDescription(): string
    {
        return 'Simulates or fetches a speed and accessibility score card report.';
    }

    public function getInputSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'url' => [
                    'type'        => 'string',
                    'description' => 'Target URL path to analyze (defaults to home URL).',
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
        return ['readOnlyHint' => true, 'idempotentHint' => true];
    }

    protected function handle(array $args, Identity $identity): ToolResult
    {
        $this->requireCapability('read', $identity);

        $report = $this->performanceService->runPerformanceAudit();

        return ToolResult::json(array_merge(
            ['url' => $args['url'] ?? home_url('/')],
            $report
        ));
    }
}
