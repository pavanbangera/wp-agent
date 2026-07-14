<?php

declare(strict_types=1);

namespace WpAgent\Tools\SEO;

use WpAgent\Auth\Identity;
use WpAgent\MCP\Protocol\ToolResult;
use WpAgent\Services\SeoService;
use WpAgent\Tools\AbstractTool;

/**
 * Tool: wordpress.seo.schema.set
 *
 * Configures structured schema tags for search engine crawlers.
 *
 * Required scope: wp-agent:write
 * Required capability: edit_posts
 *
 * @package WpAgent\Tools\SEO
 * @since   0.1.0
 */
final class SetSchemaMarkupTool extends AbstractTool
{
    public function __construct(
        private readonly SeoService $seoService,
    ) {}

    public function getName(): string
    {
        return 'wordpress.seo.schema.set';
    }

    public function getDescription(): string
    {
        return 'Sets structured JSON-LD schema markup on a target page/post.';
    }

    public function getInputSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'post_id' => [
                    'type'        => 'integer',
                    'description' => 'The post/page ID.',
                    'minimum'     => 1,
                ],
                'schema'  => [
                    'type'        => 'object',
                    'description' => 'The structured JSON-LD schema payload (e.g. {"@context": "https://schema.org", "@type": "Product"}).',
                ],
            ],
            'required'             => ['post_id', 'schema'],
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
        $schema = (array) $args['schema'];

        $this->seoService->setSchemaMarkup($postId, $schema);

        return ToolResult::json([
            'success' => true,
            'post_id' => $postId,
            'message' => 'Structured JSON-LD schema markup configured successfully.',
        ]);
    }
}
