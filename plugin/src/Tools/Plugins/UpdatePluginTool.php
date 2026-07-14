<?php

declare(strict_types=1);

namespace WpAgent\Tools\Plugins;

use WpAgent\Auth\Identity;
use WpAgent\MCP\Protocol\ToolResult;
use WpAgent\Services\PluginService;
use WpAgent\Tools\AbstractTool;

/**
 * Tool: wordpress.plugins.update
 *
 * Updates an installed plugin.
 *
 * Required scope: wp-agent:admin
 * Required capability: update_plugins
 *
 * @package WpAgent\Tools\Plugins
 * @since   0.1.0
 */
final class UpdatePluginTool extends AbstractTool
{
    public function __construct(
        private readonly PluginService $pluginService,
    ) {}

    public function getName(): string
    {
        return 'wordpress.plugins.update';
    }

    public function getDescription(): string
    {
        return 'Updates an installed plugin to its latest available version on WordPress.org.';
    }

    public function getInputSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'plugin_file' => [
                    'type'        => 'string',
                    'description' => 'The main plugin file path (e.g. "classic-editor/classic-editor.php").',
                    'minLength'   => 1,
                ],
            ],
            'required'             => ['plugin_file'],
            'additionalProperties' => false,
        ];
    }

    public function getRequiredScopes(): array
    {
        return ['wp-agent:admin'];
    }

    protected function handle(array $args, Identity $identity): ToolResult
    {
        $this->requireCapability('update_plugins', $identity);

        $pluginFile = $args['plugin_file'];
        $this->pluginService->update($pluginFile);

        return ToolResult::json([
            'success'     => true,
            'plugin_file' => $pluginFile,
            'message'     => "Plugin '{$pluginFile}' successfully updated to the latest version.",
        ]);
    }
}
