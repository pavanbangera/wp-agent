<?php

declare(strict_types=1);

namespace WpAgent\Tools\Gutenberg;

use WpAgent\Auth\Identity;
use WpAgent\MCP\Protocol\ToolResult;
use WpAgent\Services\GutenbergService;
use WpAgent\Tools\AbstractTool;

/**
 * Tool: wordpress.gutenberg.block.insert
 *
 * Inserts a block markup inside an existing post's content at a specific offset/position.
 *
 * Required scope: wp-agent:write
 * Required capability: edit_posts
 *
 * @package WpAgent\Tools\Gutenberg
 * @since   0.1.0
 */
final class InsertBlockTool extends AbstractTool
{
    public function __construct(
        private readonly GutenbergService $gutenbergService,
    ) {}

    public function getName(): string
    {
        return 'wordpress.gutenberg.block.insert';
    }

    public function getDescription(): string
    {
        return 'Inserts a Gutenberg block markup string into an existing page or post content at a specified location.';
    }

    public function getInputSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'post_id'      => [
                    'type'        => 'integer',
                    'description' => 'The target post/page ID.',
                    'minimum'     => 1,
                ],
                'block_markup' => [
                    'type'        => 'string',
                    'description' => 'Standard Gutenberg block markup comment and content (e.g. <!-- wp:paragraph -->...).',
                    'minLength'   => 1,
                ],
                'position'     => [
                    'type'        => 'string',
                    'enum'        => ['append', 'prepend', 'after_index'],
                    'default'     => 'append',
                ],
                'index'        => [
                    'type'        => 'integer',
                    'description' => 'Zero-indexed block position to insert after when using position: "after_index".',
                    'minimum'     => 0,
                    'default'     => 0,
                ],
            ],
            'required'             => ['post_id', 'block_markup'],
            'additionalProperties' => false,
        ];
    }

    public function getRequiredScopes(): array
    {
        return ['wp-agent:write'];
    }

    protected function handle(array $args, Identity $identity): ToolResult
    {
        $this->requireCapability('edit_posts', $identity);

        $postId      = (int) $args['post_id'];
        $blockMarkup = $args['block_markup'];
        $position    = $args['position'] ?? 'append';
        $index       = (int) ($args['index'] ?? 0);

        $updatedContent = $this->gutenbergService->insertBlock($postId, $blockMarkup, $position, $index);

        return ToolResult::json([
            'success' => true,
            'post_id' => $postId,
            'message' => 'Block successfully inserted into content.',
        ]);
    }
}
