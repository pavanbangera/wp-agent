<?php

declare(strict_types=1);

namespace WpAgent\Tools\Posts;

use WpAgent\Auth\Identity;
use WpAgent\MCP\Protocol\ToolResult;
use WpAgent\Services\Contracts\PostServiceInterface;
use WpAgent\Tools\AbstractTool;

/**
 * Tool: wordpress.posts.tags.manage
 *
 * Adds, removes, or replaces tag assignments on a post.
 *
 * @package WpAgent\Tools\Posts
 * @since   0.1.0
 */
final class ManageTagsTool extends AbstractTool
{
    public function __construct(private readonly PostServiceInterface $postService) {}

    public function getName(): string { return 'wordpress.posts.tags.manage'; }

    public function getDescription(): string
    {
        return 'Manages tag assignments for a WordPress post. '
            . 'Accepts tag names (creates them if they do not exist). '
            . 'Mode "set" replaces all tags. "add" appends. "remove" removes specified tags.';
    }

    public function getInputSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'post_id' => ['type' => 'integer', 'description' => 'Post ID.', 'minimum' => 1],
                'tags'    => [
                    'type'        => 'array',
                    'description' => 'Tag names or slugs. New tags are created automatically.',
                    'items'       => ['type' => 'string', 'minLength' => 1],
                ],
                'mode'    => [
                    'type'        => 'string',
                    'enum'        => ['set', 'add', 'remove'],
                    'default'     => 'set',
                    'description' => '"set" replaces all tags, "add" appends, "remove" removes specified.',
                ],
            ],
            'required'             => ['post_id', 'tags'],
            'additionalProperties' => false,
        ];
    }

    public function getRequiredScopes(): array { return ['wp-agent:write']; }

    protected function handle(array $args, Identity $identity): ToolResult
    {
        $this->requireCapability('edit_posts', $identity);

        $postId = (int) $args['post_id'];
        $tags   = array_map('sanitize_text_field', (array) $args['tags']);
        $mode   = $args['mode'] ?? 'set';

        $result = $this->postService->manageTags($postId, $tags, $mode);

        return ToolResult::json([
            'success' => true,
            'post_id' => $postId,
            'mode'    => $mode,
            'tags'    => $result,
            'count'   => count($result),
        ]);
    }
}
