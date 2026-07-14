<?php

declare(strict_types=1);

namespace WpAgent\Tools\Plugins;

use WpAgent\Auth\Identity;
use WpAgent\MCP\Protocol\ToolResult;
use WpAgent\Services\PluginService;
use WpAgent\Tools\AbstractTool;

/**
 * Tool: wordpress.plugins.list
 *
 * Lists all installed plugins.
 *
 * Required scope: wp-agent:read
 * Required capability: read
 *
 * @package WpAgent\Tools\Plugins
 * @since   0.1.0
 */
final class ListPluginsTool extends AbstractTool
{
    public function __construct(
        private readonly PluginService $pluginService,
    ) {}

    public function getName(): string
    {
        return 'wordpress.plugins.list';
    }

    public function getDescription(): string
    {
        return 'Lists all installed plugins on the WordPress site. '
            . 'Returns activation status, version, author details, and whether updates are available.';
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

        $plugins = $this->pluginService->list();

        return ToolResult::json([
            'plugins' => array_values($plugins),
            'count'   => count($plugins),
        ]);
    }
}
