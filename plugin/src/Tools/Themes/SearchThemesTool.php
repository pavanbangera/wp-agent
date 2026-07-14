<?php

declare(strict_types=1);

namespace WpAgent\Tools\Themes;

use WpAgent\Auth\Identity;
use WpAgent\MCP\Protocol\ToolResult;
use WpAgent\Services\ThemeService;
use WpAgent\Tools\AbstractTool;

/**
 * Tool: wordpress.themes.search
 *
 * Searches the WordPress.org theme directory.
 *
 * Required scope: wp-agent:read
 * Required capability: install_themes
 *
 * @package WpAgent\Tools\Themes
 * @since   0.1.0
 */
final class SearchThemesTool extends AbstractTool
{
    public function __construct(
        private readonly ThemeService $themeService,
    ) {}

    public function getName(): string
    {
        return 'wordpress.themes.search';
    }

    public function getDescription(): string
    {
        return 'Searches the official WordPress.org theme directory for themes matching a query. '
            . 'Returns slug, rating, active installs, and details needed for installation.';
    }

    public function getInputSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'term'     => [
                    'type'        => 'string',
                    'description' => 'The search query (e.g. "astra", "generatepress").',
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
                    'description' => 'Number of themes to return per page.',
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
        $this->requireCapability('install_themes', $identity);

        $term    = $args['term'];
        $page    = (int) ($args['page'] ?? 1);
        $perPage = (int) ($args['per_page'] ?? 20);

        $result = $this->themeService->search($term, $page, $perPage);

        return ToolResult::json($result);
    }
}
