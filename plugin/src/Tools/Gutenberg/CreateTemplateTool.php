<?php

declare(strict_types=1);

namespace WpAgent\Tools\Gutenberg;

use WpAgent\Auth\Identity;
use WpAgent\MCP\Protocol\ToolResult;
use WpAgent\Services\GutenbergService;
use WpAgent\Tools\AbstractTool;

/**
 * Tool: wordpress.gutenberg.template.create
 *
 * Saves a block template or template part (stored as wp_template or wp_template_part CPT).
 *
 * Required scope: wp-agent:write
 * Required capability: edit_theme_options
 *
 * @package WpAgent\Tools\Gutenberg
 * @since   0.1.0
 */
final class CreateTemplateTool extends AbstractTool
{
    public function __construct(
        private readonly GutenbergService $gutenbergService,
    ) {}

    public function getName(): string
    {
        return 'wordpress.gutenberg.template.create';
    }

    public function getDescription(): string
    {
        return 'Creates or updates a block theme Template or Template Part (stored as Custom Post Types).';
    }

    public function getInputSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'slug'    => [
                    'type'        => 'string',
                    'description' => 'The template slug name (e.g. "archive-post").',
                    'minLength'   => 1,
                ],
                'type'    => [
                    'type'        => 'string',
                    'enum'        => ['wp_template', 'wp_template_part'],
                    'description' => '"wp_template" for main page structures, "wp_template_part" for fragments (header/footer).',
                ],
                'content' => [
                    'type'        => 'string',
                    'description' => 'Standard Gutenberg block markup content.',
                    'minLength'   => 1,
                ],
                'title'   => [
                    'type'        => 'string',
                    'description' => 'A user friendly label for the template.',
                    'minLength'   => 1,
                ],
            ],
            'required'             => ['slug', 'type', 'content', 'title'],
            'additionalProperties' => false,
        ];
    }

    public function getRequiredScopes(): array
    {
        return ['wp-agent:write'];
    }

    protected function handle(array $args, Identity $identity): ToolResult
    {
        $this->requireCapability('edit_theme_options', $identity);

        $slug    = $args['slug'];
        $type    = $args['type'];
        $content = $args['content'];
        $title   = $args['title'];

        $templateId = $this->gutenbergService->createTemplate($slug, $type, $content, $title);

        return ToolResult::json([
            'success'     => true,
            'template_id' => $templateId,
            'slug'        => $slug,
            'type'        => $type,
            'message'     => 'Gutenberg Template/Template Part successfully updated in DB.',
        ]);
    }
}
