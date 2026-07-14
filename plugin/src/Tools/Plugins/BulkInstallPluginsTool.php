<?php

declare(strict_types=1);

namespace WpAgent\Tools\Plugins;

use WpAgent\Auth\Identity;
use WpAgent\MCP\Protocol\ToolResult;
use WpAgent\Services\PluginService;
use WpAgent\Tools\AbstractTool;

/**
 * Tool: wordpress.plugins.bulk_install
 *
 * Installs multiple plugins sequentially.
 *
 * Required scope: wp-agent:admin
 * Required capability: install_plugins
 *
 * @package WpAgent\Tools\Plugins
 * @since   0.1.0
 */
final class BulkInstallPluginsTool extends AbstractTool
{
    public function __construct(
        private readonly PluginService $pluginService,
    ) {}

    public function getName(): string
    {
        return 'wordpress.plugins.bulk_install';
    }

    public function getDescription(): string
    {
        return 'Performs bulk plugin installations. '
            . 'Provide a list of slugs. Returns individual installation results for each plugin.';
    }

    public function getInputSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'slugs' => [
                    'type'        => 'array',
                    'description' => 'List of plugin slugs to install (e.g. ["classic-editor", "woocommerce"]).',
                    'items'       => ['type' => 'string', 'minLength' => 1],
                ],
            ],
            'required'             => ['slugs'],
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

        $slugs   = (array) $args['slugs'];
        $results = [];

        foreach ( $slugs as $slug ) {
            $slug = sanitize_title($slug);
            try {
                $pluginFile = $this->pluginService->install($slug);
                $results[]  = [
                    'slug'        => $slug,
                    'success'     => true,
                    'plugin_file' => $pluginFile,
                    'message'     => 'Installed successfully.',
                ];
            } catch ( \Throwable $e ) {
                $results[] = [
                    'slug'    => $slug,
                    'success' => false,
                    'message' => $e->getMessage(),
                ];
            }
        }

        return ToolResult::json([
            'success' => true,
            'results' => $results,
            'count'   => count($results),
        ]);
    }
}
