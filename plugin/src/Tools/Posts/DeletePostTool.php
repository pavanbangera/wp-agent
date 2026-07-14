<?php

declare(strict_types=1);

namespace WpAgent\Tools\Posts;

use WpAgent\Auth\Identity;
use WpAgent\MCP\Protocol\ToolResult;
use WpAgent\Services\Contracts\PostServiceInterface;
use WpAgent\Tools\AbstractTool;

/**
 * Tool: wordpress.posts.delete
 *
 * @package WpAgent\Tools\Posts
 * @since   0.1.0
 */
final class DeletePostTool extends AbstractTool
{
    public function __construct(private readonly PostServiceInterface $postService) {}

    public function getName(): string { return 'wordpress.posts.delete'; }

    public function getDescription(): string
    {
        return 'Deletes a WordPress post. Moves to Trash by default. '
            . 'Set force_delete to true for permanent deletion (irreversible).';
    }

    public function getInputSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'post_id'      => ['type' => 'integer', 'description' => 'Post ID.', 'minimum' => 1],
                'force_delete' => ['type' => 'boolean', 'description' => 'Permanently delete (irreversible).', 'default' => false],
            ],
            'required'             => ['post_id'],
            'additionalProperties' => false,
        ];
    }

    public function getRequiredScopes(): array { return ['wp-agent:write']; }

    public function getAnnotations(): array
    {
        return ['destructiveHint' => true, 'readOnlyHint' => false];
    }

    protected function handle(array $args, Identity $identity): ToolResult
    {
        $this->requireCapability('delete_posts', $identity);

        $postId      = (int) $args['post_id'];
        $forceDelete = (bool) ( $args['force_delete'] ?? false );

        $this->postService->delete($postId, $forceDelete);

        return ToolResult::json([
            'success'      => true,
            'post_id'      => $postId,
            'force_delete' => $forceDelete,
            'message'      => $forceDelete
                ? "Post {$postId} permanently deleted."
                : "Post {$postId} moved to Trash.",
        ]);
    }
}
