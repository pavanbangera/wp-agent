<?php

declare(strict_types=1);

namespace WpAgent\Tools\Themes;

use WpAgent\Auth\Identity;
use WpAgent\MCP\Protocol\ToolResult;
use WpAgent\Services\ThemeService;
use WpAgent\Tools\AbstractTool;

/**
 * Tool: wordpress.themes.activate
 *
 * Activates an installed theme.
 *
 * Required scope: wp-agent:admin
 * Required capability: switch_themes
 *
 * @package WpAgent\Tools\Themes
 * @since   0.1.0
 */
final class ActivateThemeTool extends AbstractTool
{
    public function __construct(
        private readonly ThemeService $themeService,
    ) {}

    public function getName(): string
    {
        return 'wordpress.themes.activate';
    }

    public function getDescription(): string
    {
        return 'Activates/switches to an installed theme by its stylesheet directory name (e.g. "twentytwentyfour").';
    }

    public function getInputSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'stylesheet' => [
                    'type'        => 'string',
                    'description' => 'The theme directory name/stylesheet (e.g. "twentytwentyfour").',
                    'minLength'   => 1,
                ],
            ],
            'required'             => ['stylesheet'],
            'additionalProperties' => false,
        ];
    }

    public function getRequiredScopes(): array
    {
        return ['wp-agent:admin'];
    }

    protected function handle(array $args, Identity $identity): ToolResult
    {
        $this->requireCapability('switch_themes', $identity);

        $stylesheet = $args['stylesheet'];
        $this->themeService->activate($stylesheet);

        return ToolResult::json([
            'success'    => true,
            'stylesheet' => $stylesheet,
            'message'    => "Theme '{$stylesheet}' successfully activated and is now active.",
        ]);
    }
}
