<?php

declare(strict_types=1);

namespace WpAgent\Tools\Gutenberg;

use WpAgent\Auth\Identity;
use WpAgent\MCP\Protocol\ToolResult;
use WpAgent\Services\GutenbergService;
use WpAgent\Tools\AbstractTool;

/**
 * Tool: wordpress.gutenberg.markup.generate
 *
 * Generates Gutenberg-compatible comment-wrapped block markup strings.
 *
 * Required scope: wp-agent:read
 * Required capability: read
 *
 * @package WpAgent\Tools\Gutenberg
 * @since   0.1.0
 */
final class GenerateBlockMarkupTool extends AbstractTool
{
    public function __construct(
        private readonly GutenbergService $gutenbergService,
    ) {}

    public function getName(): string
    {
        return 'wordpress.gutenberg.markup.generate';
    }

    public function getDescription(): string
    {
        return 'Generates valid Gutenberg block markup comment tags from JSON specifications.';
    }

    public function getInputSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'block_name' => [
                    'type'        => 'string',
                    'description' => 'The namespace/name of the block type (e.g. "core/paragraph", "core/image").',
                    'minLength'   => 1,
                ],
                'attrs'      => [
                    'type'        => 'object',
                    'description' => 'Block attributes settings mapping.',
                    'default'     => new \stdClass(),
                ],
                'inner_html' => [
                    'type'        => 'string',
                    'description' => 'Inner HTML markup content enclosed by block tags. Leave empty for self-closing blocks.',
                    'default'     => '',
                ],
            ],
            'required'             => ['block_name'],
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
        $this->requireCapability('read', $identity);

        $blockName = $args['block_name'];
        $attrs     = (array) ($args['attrs'] ?? []);
        $innerHtml = $args['inner_html'] ?? '';

        $markup = $this->gutenbergService->generateBlockMarkup($blockName, $attrs, $innerHtml);

        return ToolResult::json([
            'success'      => true,
            'block_markup' => $markup,
        ]);
    }
}
