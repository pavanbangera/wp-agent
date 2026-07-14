<?php

declare(strict_types=1);

namespace WpAgent\Tools\Posts;

use WpAgent\Auth\Identity;
use WpAgent\MCP\Protocol\ToolResult;
use WpAgent\Services\Contracts\PostServiceInterface;
use WpAgent\Tools\AbstractTool;

/**
 * Tool: wordpress.posts.categories.manage
 *
 * Adds, removes, or replaces category assignments on a post.
 *
 * @package WpAgent\Tools\Posts
 * @since   0.1.0
 */
final class ManageCategoriesTool extends AbstractTool
{
    public function __construct(private readonly PostServiceInterface $postService) {}

    public function getName(): string { return 'wordpress.posts.categories.manage'; }

    public function getDescription(): string
    {
        return 'Manages category assignments for a WordPress post. '
            . 'Mode "set" replaces all categories. '
            . 'Mode "add" appends without removing existing ones. '
            . 'Mode "remove" removes only the specified categories. '
            . 'Returns the final list of assigned category IDs.';
    }

    public function getInputSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'post_id'      => ['type' => 'integer', 'description' => 'Post ID.', 'minimum' => 1],
                'category_ids' => [
                    'type'        => 'array',
                    'description' => 'Category IDs to assign/add/remove.',
                    'items'       => ['type' => 'integer', 'minimum' => 1],
                ],
                'mode'         => [
                    'type'        => 'string',
                    'description' => '"set" replaces all, "add" appends, "remove" removes specified.',
                    'enum'        => ['set', 'add', 'remove'],
                    'default'     => 'set',
                ],
            ],
            'required'             => ['post_id', 'category_ids'],
            'additionalProperties' => false,
        ];
    }

    public function getRequiredScopes(): array { return ['wp-agent:write']; }

    protected function handle(array $args, Identity $identity): ToolResult
    {
        $this->requireCapability('edit_posts', $identity);

        $postId      = (int) $args['post_id'];
        $categoryIds = array_map('intval', (array) $args['category_ids']);
        $mode        = $args['mode'] ?? 'set';

        $result = $this->postService->manageCategories($postId, $categoryIds, $mode);

        return ToolResult::json([
            'success'      => true,
            'post_id'      => $postId,
            'mode'         => $mode,
            'category_ids' => $result,
            'count'        => count($result),
        ]);
    }
}
