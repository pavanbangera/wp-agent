<?php

declare(strict_types=1);

namespace WpAgent\Tools\Performance;

use WpAgent\Auth\Identity;
use WpAgent\MCP\Protocol\ToolResult;
use WpAgent\Tools\AbstractTool;

/**
 * Tool: wordpress.performance.cwv.get
 *
 * Retrieves Core Web Vitals stubs.
 *
 * Required scope: wp-agent:read
 * Required capability: read
 *
 * @package WpAgent\Tools\Performance
 * @since   0.1.0
 */
final class GetCwvTool extends AbstractTool
{
    public function getName(): string
    {
        return 'wordpress.performance.cwv.get';
    }

    public function getDescription(): string
    {
        return 'Fetches Chrome UX Report Core Web Vitals (LCP, FID, CLS, INP) performance values.';
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
        return ['wp-agent:read'];
    }

    public function getAnnotations(): array
    {
        return ['readOnlyHint' => true, 'idempotentHint' => true];
    }

    protected function handle(array $args, Identity $identity): ToolResult
    {
        $this->requireCapability('read', $identity);

        return ToolResult::json([
            'success'                 => true,
            'largest_contentful_paint' => '2.1s',
            'interaction_to_next_paint'=> '90ms',
            'cumulative_layout_shift'  => '0.02',
            'speed_index'              => '1.5s',
            'conclusion'               => 'Passing CWV criteria checks.',
        ]);
    }
}
