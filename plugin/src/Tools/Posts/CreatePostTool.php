<?php

declare(strict_types=1);

namespace WpAgent\Tools\Posts;

use WpAgent\Auth\Identity;
use WpAgent\MCP\Protocol\ToolResult;
use WpAgent\Services\Contracts\PostServiceInterface;
use WpAgent\Tools\AbstractTool;
use WpAgent\Tools\Concerns\FormatsPost;

/**
 * Tool: wordpress.posts.create
 *
 * Creates a new WordPress blog post with full metadata support.
 *
 * Required scope: wp-agent:write
 * Required capability: edit_posts
 *
 * @package WpAgent\Tools\Posts
 * @since   0.1.0
 */
final class CreatePostTool extends AbstractTool
{
    public function __construct(
        private readonly PostServiceInterface $postService,
    ) {}

    public function getName(): string
    {
        return 'wordpress.posts.create';
    }

    public function getDescription(): string
    {
        return 'Creates a new WordPress blog post. '
            . 'Supports all standard post fields: title, content, excerpt, status, '
            . 'categories, tags, featured image, SEO meta, and post format. '
            . 'Content can be HTML or valid Gutenberg block markup. '
            . 'Returns the complete post object with its new ID.';
    }

    public function getInputSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'title'              => [
                    'type'        => 'string',
                    'description' => 'The post title.',
                    'minLength'   => 1,
                    'maxLength'   => 1000,
                ],
                'content'            => [
                    'type'        => 'string',
                    'description' => 'Post content. Accepts HTML or Gutenberg block markup.',
                    'default'     => '',
                ],
                'excerpt'            => [
                    'type'        => 'string',
                    'description' => 'Short excerpt/summary for the post.',
                    'default'     => '',
                ],
                'status'             => [
                    'type'        => 'string',
                    'description' => 'Publication status.',
                    'enum'        => ['draft', 'publish', 'private', 'pending', 'future'],
                    'default'     => 'draft',
                ],
                'slug'               => [
                    'type'        => 'string',
                    'description' => 'URL-friendly slug. Auto-generated from title if omitted.',
                ],
                'categories'         => [
                    'type'        => 'array',
                    'description' => 'Array of category IDs to assign.',
                    'items'       => ['type' => 'integer', 'minimum' => 1],
                    'default'     => [],
                ],
                'tags'               => [
                    'type'        => 'array',
                    'description' => 'Array of tag names or slugs.',
                    'items'       => ['type' => 'string'],
                    'default'     => [],
                ],
                'featured_image_id'  => [
                    'type'        => 'integer',
                    'description' => 'Attachment ID of the featured image.',
                    'minimum'     => 1,
                ],
                'format'             => [
                    'type'        => 'string',
                    'description' => 'Post format.',
                    'enum'        => ['standard', 'aside', 'gallery', 'link', 'image', 'quote', 'status', 'video', 'audio', 'chat'],
                    'default'     => 'standard',
                ],
                'comment_status'     => [
                    'type'        => 'string',
                    'enum'        => ['open', 'closed'],
                    'default'     => 'open',
                ],
                'seo_title'          => [
                    'type'        => 'string',
                    'description' => 'SEO meta title (Yoast, RankMath, or AIOSEO).',
                ],
                'seo_description'    => [
                    'type'        => 'string',
                    'description' => 'SEO meta description.',
                ],
            ],
            'required'             => ['title'],
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

        $post = $this->postService->create($args);

        return ToolResult::json(FormatsPost::format($post));
    }
}
