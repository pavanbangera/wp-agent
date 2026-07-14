<?php

declare(strict_types=1);

namespace WpAgent\Tools\SEO;

use WpAgent\Auth\Identity;
use WpAgent\MCP\Protocol\ToolResult;
use WpAgent\Services\SeoService;
use WpAgent\Tools\AbstractTool;

/**
 * Tool: wordpress.seo.opengraph.set
 *
 * Configures Open Graph metas.
 *
 * Required scope: wp-agent:write
 * Required capability: edit_posts
 *
 * @package WpAgent\Tools\SEO
 * @since   0.1.0
 */
final class SetOpenGraphTool extends AbstractTool
{
    public function __construct(
        private readonly SeoService $seoService,
    ) {}

    public function getName(): string
    {
        return 'wordpress.seo.opengraph.set';
    }

    public function getDescription(): string
    {
        return 'Configures Open Graph properties (title, description, image URL) for social networks sharing.';
    }

    public function getInputSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'post_id'     => [
                    'type'        => 'integer',
                    'description' => 'The post ID.',
                    'minimum'     => 1,
                ],
                'title'       => [
                    'type'        => 'string',
                    'description' => 'The sharing title.',
                ],
                'description' => [
                    'type'        => 'string',
                    'description' => 'The sharing description text.',
                ],
                'image_url'   => [
                    'type'        => 'string',
                    'description' => 'Absolute URL of the preview image.',
                ],
            ],
            'required'             => ['post_id'],
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

        $postId = (int) $args['post_id'];
        unset($args['post_id']);

        $this->seoService->setOgMeta($postId, $args);

        return ToolResult::json([
            'success' => true,
            'post_id' => $postId,
            'message' => 'Social sharing Open Graph meta tags configured successfully.',
        ]);
    }
}
