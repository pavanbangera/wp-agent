<?php

declare(strict_types=1);

namespace WpAgent\Tools\Plugins;

use WpAgent\Auth\Identity;
use WpAgent\MCP\Protocol\ToolResult;
use WpAgent\Services\PluginService;
use WpAgent\Tools\AbstractTool;

/**
 * Tool: wordpress.plugins.install
 *
 * Downloads and installs a plugin by its WP.org directory slug.
 *
 * Required scope: wp-agent:admin
 * Required capability: install_plugins
 *
 * @package WpAgent\Tools\Plugins
 * @since   0.1.0
 */
final class InstallPluginTool extends AbstractTool
{
    public function __construct(
        private readonly PluginService $pluginService,
    ) {}

    public function getName(): string
    {
        return 'wordpress.plugins.install';
    }

    public function getDescription(): string
    {
        return 'Downloads and installs a plugin from WordPress.org by its slug (e.g. "classic-editor"). '
            . 'Does not activate the plugin automatically. Use wordpress.plugins.activate afterwards.';
    }

    public function getInputSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'slug' => [
                    'type'        => 'string',
                    'description' => 'The slug of the plugin on WordPress.org (e.g. "classic-editor").',
                    'minLength'   => 1,
                ],
            ],
            'required'             => ['slug'],
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

        $slug       = $args['slug'];
        $pluginFile = $this->pluginService->install($slug);

        return ToolResult::json([
            'success'     => true,
            'slug'        => $slug,
            'plugin_file' => $pluginFile,
            'message'     => "Plugin '{$slug}' successfully installed. Use wordpress.plugins.activate to run it.",
        ]);
    }
}
