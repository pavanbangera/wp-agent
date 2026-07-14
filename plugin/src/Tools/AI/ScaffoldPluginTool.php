<?php

declare(strict_types=1);

namespace WpAgent\Tools\AI;

use WpAgent\Auth\Identity;
use WpAgent\MCP\Protocol\ToolResult;
use WpAgent\Services\AiPlannerService;
use WpAgent\Tools\AbstractTool;

/**
 * Tool: wordpress.scaffold.plugin
 *
 * Scaffolds plugin boilerplates.
 *
 * Required scope: wp-agent:admin
 * Required capability: install_plugins
 *
 * @package WpAgent\Tools\AI
 * @since   0.1.0
 */
final class ScaffoldPluginTool extends AbstractTool
{
    public function __construct(
        private readonly AiPlannerService $plannerService,
    ) {}

    public function getName(): string
    {
        return 'wordpress.scaffold.plugin';
    }

    public function getDescription(): string
    {
        return 'Generates boilerplate files for a new WordPress plugin on disk.';
    }

    public function getInputSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'slug'        => [
                    'type'        => 'string',
                    'description' => 'The alphanumeric plugin folder name (e.g. "my-helper-plugin").',
                    'minLength'   => 1,
                ],
                'title'       => [
                    'type'        => 'string',
                    'description' => 'User friendly Name of the plugin.',
                ],
                'description' => [
                    'type'        => 'string',
                    'description' => 'A brief description of the plugin functionality.',
                ],
                'author'      => [
                    'type'        => 'string',
                    'description' => 'Author attribution name.',
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
        $this->requireCapability('install_plugins', $identity);

        $slug = $args['slug'];
        unset($args['slug']);

        $this->plannerService->scaffoldPlugin($slug, $args);

        return ToolResult::json([
            'success' => true,
            'slug'    => $slug,
            'message' => "Plugin structure scaffolded under wp-content/plugins/{$slug}.",
        ]);
    }
}
