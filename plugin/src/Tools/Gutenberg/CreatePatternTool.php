<?php

declare(strict_types=1);

namespace WpAgent\Tools\Gutenberg;

use WpAgent\Auth\Identity;
use WpAgent\MCP\Protocol\ToolResult;
use WpAgent\Services\GutenbergService;
use WpAgent\Tools\AbstractTool;

/**
 * Tool: wordpress.gutenberg.pattern.create
 *
 * Registers a block pattern dynamically in WordPress.
 *
 * Required scope: wp-agent:write
 * Required capability: edit_theme_options
 *
 * @package WpAgent\Tools\Gutenberg
 * @since   0.1.0
 */
final class CreatePatternTool extends AbstractTool
{
    public function __construct(
        private readonly GutenbergService $gutenbergService,
    ) {}

    public function getName(): string
    {
        return 'wordpress.gutenberg.pattern.create';
    }

    public function getDescription(): string
    {
        return 'Registers a reusable Gutenberg Block Pattern in the system.';
    }

    public function getInputSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'title'      => [
                    'type'        => 'string',
                    'description' => 'The user-facing title for the block pattern.',
                    'minLength'   => 1,
                ],
                'slug'       => [
                    'type'        => 'string',
                    'description' => 'Unique alphanumeric slug for pattern (e.g. "my-custom-cta").',
                    'minLength'   => 1,
                ],
                'content'    => [
                    'type'        => 'string',
                    'description' => 'Standard Gutenberg block markup content of the pattern.',
                    'minLength'   => 1,
                ],
                'categories' => [
                    'type'        => 'array',
                    'description' => 'Categories to classify pattern under (e.g. ["buttons", "header"]).',
                    'items'       => ['type' => 'string'],
                    'default'     => ['general'],
                ],
            ],
            'required'             => ['title', 'slug', 'content'],
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

        $title      = $args['title'];
        $slug       = $args['slug'];
        $content    = $args['content'];
        $categories = (array) ($args['categories'] ?? ['general']);

        $this->gutenbergService->createPattern($title, $slug, $content, $categories);

        return ToolResult::json([
            'success' => true,
            'slug'    => $slug,
            'title'   => $title,
            'message' => "Block Pattern '{$slug}' successfully registered.",
        ]);
    }
}
