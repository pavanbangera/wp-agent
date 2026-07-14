<?php

declare(strict_types=1);

namespace WpAgent\Tools\Posts;

use WpAgent\Auth\Identity;
use WpAgent\MCP\Protocol\ToolResult;
use WpAgent\Services\Contracts\PostServiceInterface;
use WpAgent\Tools\AbstractTool;

/**
 * Tool: wordpress.posts.featured_image.set
 *
 * Sets or removes the featured image (thumbnail) for a post.
 *
 * @package WpAgent\Tools\Posts
 * @since   0.1.0
 */
final class SetFeaturedImageTool extends AbstractTool
{
    public function __construct(private readonly PostServiceInterface $postService) {}

    public function getName(): string { return 'wordpress.posts.featured_image.set'; }

    public function getDescription(): string
    {
        return 'Sets the featured image (thumbnail) for a WordPress post. '
            . 'Provide the attachment_id of an existing media item. '
            . 'Set attachment_id to 0 to remove the featured image. '
            . 'Use wordpress.media.upload first if you need to upload a new image.';
    }

    public function getInputSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'post_id'       => ['type' => 'integer', 'description' => 'Post ID.', 'minimum' => 1],
                'attachment_id' => [
                    'type'        => 'integer',
                    'description' => 'Media attachment ID. Set to 0 to remove the featured image.',
                    'minimum'     => 0,
                ],
            ],
            'required'             => ['post_id', 'attachment_id'],
            'additionalProperties' => false,
        ];
    }

    public function getRequiredScopes(): array { return ['wp-agent:write']; }

    protected function handle(array $args, Identity $identity): ToolResult
    {
        $this->requireCapability('edit_posts', $identity);

        $postId       = (int) $args['post_id'];
        $attachmentId = (int) $args['attachment_id'];

        $this->postService->setFeaturedImage($postId, $attachmentId);

        $message = $attachmentId > 0
            ? "Featured image (attachment #{$attachmentId}) set on post {$postId}."
            : "Featured image removed from post {$postId}.";

        return ToolResult::json([
            'success'       => true,
            'post_id'       => $postId,
            'attachment_id' => $attachmentId ?: null,
            'message'       => $message,
        ]);
    }
}
