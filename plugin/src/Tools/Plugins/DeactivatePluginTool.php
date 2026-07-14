<?php

declare(strict_types=1);

namespace WpAgent\Tools\Plugins;

use WpAgent\Auth\Identity;
use WpAgent\MCP\Protocol\ToolResult;
use WpAgent\Services\PluginService;
use WpAgent\Tools\AbstractTool;

/**
 * Tool: wordpress.plugins.deactivate
 *
 * Deactivates an active plugin.
 *
 * Required scope: wp-agent:admin
 * Required capability: deactivate_plugins
 *
 * @package WpAgent\Tools\Plugins
 * @since   0.1.0
 */
final class DeactivatePluginTool extends AbstractTool
{
    public function __construct(
        private readonly PluginService $pluginService,
    ) {}

    public function getName(): string
    {
        return 'wordpress.plugins.deactivate';
    }

    public function getDescription(): string
    {
        return 'Deactivates an active plugin on the site by its plugin file path (e.g. "classic-editor/classic-editor.php").';
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
        $this->requireCapability('deactivate_plugins', $identity);

        $pluginFile = $args['plugin_file'];
        $this->pluginService->deactivate($pluginFile);

        return ToolResult::json([
            'success'     => true,
            'plugin_file' => $pluginFile,
            'message'     => "Plugin '{$pluginFile}' successfully deactivated.",
        ]);
    }
}
