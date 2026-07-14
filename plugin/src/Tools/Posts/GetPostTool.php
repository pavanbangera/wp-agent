<?php

declare(strict_types=1);

namespace WpAgent\Tools\Posts;

use WpAgent\Auth\Identity;
use WpAgent\MCP\Protocol\ToolResult;
use WpAgent\Services\Contracts\PostServiceInterface;
use WpAgent\Tools\AbstractTool;
use WpAgent\Tools\Concerns\FormatsPost;

/**
 * Tool: wordpress.posts.get
 *
 * @package WpAgent\Tools\Posts
 * @since   0.1.0
 */
final class GetPostTool extends AbstractTool
{
    public function __construct(private readonly PostServiceInterface $postService) {}

    public function getName(): string { return 'wordpress.posts.get'; }

    public function getDescription(): string
    {
        return 'Retrieves a single WordPress post by ID with full details including '
            . 'content, categories, tags, featured image, SEO meta, and author information.';
    }

    public function getInputSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'post_id' => ['type' => 'integer', 'description' => 'Post ID.', 'minimum' => 1],
            ],
            'required'             => ['post_id'],
            'additionalProperties' => false,
        ];
    }

    public function getRequiredScopes(): array { return ['wp-agent:read']; }

    public function getAnnotations(): array { return ['readOnlyHint' => true, 'idempotentHint' => true]; }

    protected function handle(array $args, Identity $identity): ToolResult
    {
        $this->requireCapability('read', $identity);
        return ToolResult::json(FormatsPost::format($this->postService->get((int) $args['post_id'])));
    }
}
