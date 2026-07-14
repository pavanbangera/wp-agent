<?php

declare(strict_types=1);

namespace WpAgent\Tools\Themes;

use WpAgent\Auth\Identity;
use WpAgent\MCP\Protocol\ToolResult;
use WpAgent\Services\ThemeService;
use WpAgent\Tools\AbstractTool;

/**
 * Tool: wordpress.themes.child.create
 *
 * Creates a custom child theme directory and assets enqueuing the parent styles.
 *
 * Required scope: wp-agent:admin
 * Required capability: install_themes
 *
 * @package WpAgent\Tools\Themes
 * @since   0.1.0
 */
final class CreateChildThemeTool extends AbstractTool
{
    public function __construct(
        private readonly ThemeService $themeService,
    ) {}

    public function getName(): string
    {
        return 'wordpress.themes.child.create';
    }

    public function getDescription(): string
    {
        return 'Creates a child theme on disk under wp-content/themes/. '
            . 'Generates the required style.css metadata and enqueues parent stylesheet links in functions.php. '
            . 'Allows you to customize theme designs safely without losing changes on parent updates.';
    }

    public function getInputSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'parent_stylesheet' => [
                    'type'        => 'string',
                    'description' => 'The parent theme folder/stylesheet name (e.g. "twentytwentyfour").',
                    'minLength'   => 1,
                ],
                'child_slug'        => [
                    'type'        => 'string',
                    'description' => 'The directory name for the child theme (e.g. "twentytwentyfour-child").',
                    'minLength'   => 1,
                ],
                'child_name'        => [
                    'type'        => 'string',
                    'description' => 'The display name for the child theme (e.g. "Twenty Twenty-Four Child").',
                    'minLength'   => 1,
                ],
            ],
            'required'             => ['parent_stylesheet', 'child_slug', 'child_name'],
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

        $parentSlug = $args['parent_stylesheet'];
        $childSlug  = $args['child_slug'];
        $childName  = $args['child_name'];

        $childTheme = $this->themeService->createChildTheme($parentSlug, $childSlug, $childName);

        return ToolResult::json(array_merge(
            ['success' => true],
            $childTheme
        ));
    }
}
