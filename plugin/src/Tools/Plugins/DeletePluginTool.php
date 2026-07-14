<?php

declare(strict_types=1);

namespace WpAgent\Tools\Plugins;

use WpAgent\Auth\Identity;
use WpAgent\MCP\Protocol\ToolResult;
use WpAgent\Services\PluginService;
use WpAgent\Tools\AbstractTool;

/**
 * Tool: wordpress.plugins.delete
 *
 * Deletes a deactivated plugin.
 *
 * Required scope: wp-agent:admin
 * Required capability: delete_plugins
 *
 * @package WpAgent\Tools\Plugins
 * @since   0.1.0
 */
final class DeletePluginTool extends AbstractTool
{
    public function __construct(
        private readonly PluginService $pluginService,
    ) {}

    public function getName(): string
    {
        return 'wordpress.plugins.delete';
    }

    public function getDescription(): string
    {
        return 'Deletes a deactivated plugin from the server filesystem. '
            . 'CAUTION: Active plugins cannot be deleted. Deactivate them first. '
            . 'Deletion is irreversible.';
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

    public function getAnnotations(): array
    {
        return ['destructiveHint' => true, 'readOnlyHint' => false];
    }

    protected function handle(array $args, Identity $identity): ToolResult
    {
        $this->requireCapability('delete_plugins', $identity);

        $pluginFile = $args['plugin_file'];
        $this->pluginService->delete($pluginFile);

        return ToolResult::json([
            'success'     => true,
            'plugin_file' => $pluginFile,
            'message'     => "Plugin '{$pluginFile}' successfully deleted from filesystem.",
        ]);
    }
}
