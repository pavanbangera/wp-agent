<?php

declare(strict_types=1);

namespace WpAgent\Tools\Themes;

use WpAgent\Auth\Identity;
use WpAgent\MCP\Protocol\ToolResult;
use WpAgent\Tools\AbstractTool;

/**
 * Tool: wordpress.theme.get_active
 *
 * Returns full details about the currently active WordPress theme including parent/child
 * theme relationship, version, author, and URI. Eliminates the need for raw PHP snippets
 * like get_stylesheet() / get_template() for basic theme verification.
 *
 * Required scope: wp-agent:read
 * Required capability: read
 *
 * @package WpAgent\Tools\Themes
 * @since   0.1.0
 */
final class GetActiveThemeTool extends AbstractTool
{
    public function getName(): string
    {
        return 'wordpress.theme.get_active';
    }

    public function getDescription(): string
    {
        return 'Returns the currently active WordPress theme and its parent theme (if a child theme is active). '
            . 'Provides stylesheet slug, template slug, display name, version, author, '
            . 'theme URI, and a boolean indicating whether a child theme is active. '
            . 'Use this instead of raw PHP calls to get_stylesheet() / get_template().';
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
        return [
            'readOnlyHint'   => true,
            'destructiveHint' => false,
            'idempotentHint' => true,
        ];
    }

    protected function handle(array $args, Identity $identity): ToolResult
    {
        $this->requireCapability('read', $identity);

        $theme        = wp_get_theme();
        $isChildTheme = $theme->get_stylesheet() !== $theme->get_template();

        $result = [
            'stylesheet'    => $theme->get_stylesheet(),
            'template'      => $theme->get_template(),
            'is_child_theme' => $isChildTheme,
            'active_theme'  => [
                'name'      => $theme->get('Name'),
                'version'   => $theme->get('Version'),
                'author'    => $theme->get('Author'),
                'theme_uri' => $theme->get('ThemeURI'),
                'author_uri' => $theme->get('AuthorURI'),
                'description' => $theme->get('Description'),
                'tags'      => $theme->get('Tags'),
                'status'    => $theme->get('Status'),
                'directory' => $theme->get_stylesheet_directory(),
                'directory_uri' => $theme->get_stylesheet_directory_uri(),
            ],
        ];

        // Include parent theme details if a child theme is active.
        if ( $isChildTheme ) {
            $parent = wp_get_theme($theme->get_template());

            $result['parent_theme'] = [
                'name'      => $parent->get('Name'),
                'version'   => $parent->get('Version'),
                'author'    => $parent->get('Author'),
                'theme_uri' => $parent->get('ThemeURI'),
                'directory' => $parent->get_template_directory(),
                'directory_uri' => $parent->get_template_directory_uri(),
            ];
        }

        return ToolResult::json($result);
    }
}
