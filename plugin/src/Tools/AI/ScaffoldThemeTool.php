<?php

declare(strict_types=1);

namespace WpAgent\Tools\AI;

use WpAgent\Auth\Identity;
use WpAgent\MCP\Protocol\ToolResult;
use WpAgent\Services\AiPlannerService;
use WpAgent\Tools\AbstractTool;

/**
 * Tool: wordpress.scaffold.theme
 *
 * Scaffolds custom classic/block theme.
 *
 * Required scope: wp-agent:admin
 * Required capability: install_themes
 *
 * @package WpAgent\Tools\AI
 * @since   0.1.0
 */
final class ScaffoldThemeTool extends AbstractTool
{
    public function __construct(
        private readonly AiPlannerService $plannerService,
    ) {}

    public function getName(): string
    {
        return 'wordpress.scaffold.theme';
    }

    public function getDescription(): string
    {
        return 'Generates files for a new WordPress custom theme on disk.';
    }

    public function getInputSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'slug'        => [
                    'type'        => 'string',
                    'description' => 'The alphanumeric theme folder name (e.g. "my-custom-theme").',
                    'minLength'   => 1,
                ],
                'title'       => [
                    'type'        => 'string',
                    'description' => 'Name of the theme.',
                ],
                'description' => [
                    'type'        => 'string',
                    'description' => 'A brief description of the theme styling.',
                ],
                'author'      => [
                    'type'        => 'string',
                    'description' => 'Author attribution.',
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
        unset($args['slug']);

        $this->plannerService->scaffoldTheme($slug, $args);

        return ToolResult::json([
            'success' => true,
            'slug'    => $slug,
            'message' => "Theme structure scaffolded under wp-content/themes/{$slug}.",
        ]);
    }
}
