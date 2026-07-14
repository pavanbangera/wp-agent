<?php

declare(strict_types=1);

namespace WpAgent\Tools\Posts;

use WpAgent\Auth\Identity;
use WpAgent\MCP\Protocol\ToolResult;
use WpAgent\Services\Contracts\PostServiceInterface;
use WpAgent\Tools\AbstractTool;
use WpAgent\Tools\Concerns\FormatsPost;

/**
 * Tool: wordpress.posts.update
 *
 * Updates an existing WordPress post. Only provided fields are changed.
 *
 * @package WpAgent\Tools\Posts
 * @since   0.1.0
 */
final class UpdatePostTool extends AbstractTool
{
    public function __construct(private readonly PostServiceInterface $postService) {}

    public function getName(): string { return 'wordpress.posts.update'; }

    public function getDescription(): string
    {
        return 'Updates an existing WordPress post by ID. '
            . 'Only the fields you provide will be modified — all other fields remain unchanged. '
            . 'Supports all post fields including content, status, categories, tags, SEO, and featured image.';
    }

    public function getInputSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'post_id'            => ['type' => 'integer', 'description' => 'Post ID to update.', 'minimum' => 1],
                'title'              => ['type' => 'string', 'description' => 'New post title.'],
                'content'            => ['type' => 'string', 'description' => 'New post content.'],
                'excerpt'            => ['type' => 'string', 'description' => 'New post excerpt.'],
                'status'             => ['type' => 'string', 'enum' => ['draft', 'publish', 'private', 'pending', 'trash']],
                'slug'               => ['type' => 'string', 'description' => 'New URL slug.'],
                'categories'         => ['type' => 'array', 'items' => ['type' => 'integer'], 'description' => 'Category IDs (replaces all).'],
                'tags'               => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Tag names (replaces all).'],
                'featured_image_id'  => ['type' => 'integer', 'description' => 'Featured image attachment ID. Set 0 to remove.', 'minimum' => 0],
                'seo_title'          => ['type' => 'string'],
                'seo_description'    => ['type' => 'string'],
            ],
            'required'             => ['post_id'],
            'additionalProperties' => false,
        ];
    }

    public function getRequiredScopes(): array { return ['wp-agent:write']; }

    protected function handle(array $args, Identity $identity): ToolResult
    {
        $this->requireCapability('edit_posts', $identity);

        $postId = (int) $args['post_id'];
        unset($args['post_id']);

        return ToolResult::json(FormatsPost::format($this->postService->update($postId, $args)));
    }
}
