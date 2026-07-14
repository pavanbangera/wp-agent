<?php

declare(strict_types=1);

namespace WpAgent\Tools\Themes;

use WpAgent\Auth\Identity;
use WpAgent\MCP\Protocol\ToolResult;
use WpAgent\Services\ThemeService;
use WpAgent\Tools\AbstractTool;

/**
 * Tool: wordpress.themes.install
 *
 * Downloads and installs a theme from WP.org by slug.
 *
 * Required scope: wp-agent:admin
 * Required capability: install_themes
 *
 * @package WpAgent\Tools\Themes
 * @since   0.1.0
 */
final class InstallThemeTool extends AbstractTool
{
    public function __construct(
        private readonly ThemeService $themeService,
    ) {}

    public function getName(): string
    {
        return 'wordpress.themes.install';
    }

    public function getDescription(): string
    {
        return 'Downloads and installs a theme from WordPress.org by its slug (e.g. "astra"). '
            . 'Does not activate it automatically. Use wordpress.themes.activate afterwards.';
    }

    public function getInputSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'slug' => [
                    'type'        => 'string',
                    'description' => 'The slug of the theme on WordPress.org (e.g. "astra").',
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
        $this->requireCapability('install_themes', $identity);

        $slug = $args['slug'];
        $this->themeService->install($slug);

        return ToolResult::json([
            'success' => true,
            'slug'    => $slug,
            'message' => "Theme '{$slug}' successfully installed. Use wordpress.themes.activate to switch to it.",
        ]);
    }
}
