<?php

declare(strict_types=1);

namespace WpAgent\Tools\Plugins;

use WpAgent\Auth\Identity;
use WpAgent\MCP\Protocol\ToolResult;
use WpAgent\Services\PluginService;
use WpAgent\Tools\AbstractTool;

/**
 * Tool: wordpress.plugins.rollback
 *
 * Rolls back a plugin to an older version.
 *
 * Required scope: wp-agent:admin
 * Required capability: install_plugins
 *
 * @package WpAgent\Tools\Plugins
 * @since   0.1.0
 */
final class RollbackPluginTool extends AbstractTool
{
    public function __construct(
        private readonly PluginService $pluginService,
    ) {}

    public function getName(): string
    {
        return 'wordpress.plugins.rollback';
    }

    public function getDescription(): string
    {
        return 'Rolls back or forces a plugin installation to a specific target version. '
            . 'Downloads the archived ZIP from WordPress.org, overrides the existing directory, '
            . 'and preserves the previous activation state.';
    }

    public function getInputSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'slug'    => [
                    'type'        => 'string',
                    'description' => 'The WordPress.org plugin directory slug (e.g. "classic-editor").',
                    'minLength'   => 1,
                ],
                'version' => [
                    'type'        => 'string',
                    'description' => 'The target version to install (e.g. "1.5").',
                    'minLength'   => 1,
                ],
            ],
            'required'             => ['slug', 'version'],
            'additionalProperties' => false,
        ];
    }

    public function getRequiredScopes(): array
    {
        return ['wp-agent:admin'];
    }

    protected function handle(array $args, Identity $identity): ToolResult
    {
        $this->requireCapability('install_plugins', $identity);

        $slug    = $args['slug'];
        $version = $args['version'];

        $pluginFile = $this->pluginService->rollback($slug, $version);

        return ToolResult::json([
            'success'     => true,
            'slug'        => $slug,
            'version'     => $version,
            'plugin_file' => $pluginFile,
            'message'     => "Plugin '{$slug}' successfully rolled back to version {$version}.",
        ]);
    }
}
