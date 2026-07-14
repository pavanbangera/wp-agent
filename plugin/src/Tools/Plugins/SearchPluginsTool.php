<?php

declare(strict_types=1);

namespace WpAgent\Tools\Plugins;

use WpAgent\Auth\Identity;
use WpAgent\MCP\Protocol\ToolResult;
use WpAgent\Services\PluginService;
use WpAgent\Tools\AbstractTool;

/**
 * Tool: wordpress.plugins.search
 *
 * Searches the WordPress.org plugin directory.
 *
 * Required scope: wp-agent:read
 * Required capability: install_plugins
 *
 * @package WpAgent\Tools\Plugins
 * @since   0.1.0
 */
final class SearchPluginsTool extends AbstractTool
{
    public function __construct(
        private readonly PluginService $pluginService,
    ) {}

    public function getName(): string
    {
        return 'wordpress.plugins.search';
    }

    public function getDescription(): string
    {
        return 'Searches the official WordPress.org plugin directory for plugins matching a query. '
            . 'Returns slug, version, rating, active installs, and details needed for installation.';
    }

    public function getInputSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'term'     => [
                    'type'        => 'string',
                    'description' => 'The search query (e.g. "woocommerce", "contact form").',
                    'minLength'   => 1,
                ],
                'page'     => [
                    'type'        => 'integer',
                    'description' => 'Pagination page number.',
                    'minimum'     => 1,
                    'default'     => 1,
                ],
                'per_page' => [
                    'type'        => 'integer',
                    'description' => 'Number of plugins to return per page.',
                    'minimum'     => 1,
                    'maximum'     => 100,
                    'default'     => 20,
                ],
            ],
            'required'             => ['term'],
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
        $this->requireCapability('install_plugins', $identity);

        $term    = $args['term'];
        $page    = (int) ($args['page'] ?? 1);
        $perPage = (int) ($args['per_page'] ?? 20);

        $result = $this->pluginService->search($term, $page, $perPage);

        return ToolResult::json($result);
    }
}
