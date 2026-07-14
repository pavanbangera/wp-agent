<?php

declare(strict_types=1);

namespace WpAgent\Tools\SEO;

use WpAgent\Auth\Identity;
use WpAgent\MCP\Protocol\ToolResult;
use WpAgent\Services\SeoService;
use WpAgent\Tools\AbstractTool;

/**
 * Tool: wordpress.seo.audit
 *
 * Runs a local text-based SEO audit on a post/page.
 *
 * Required scope: wp-agent:read
 * Required capability: edit_posts
 *
 * @package WpAgent\Tools\SEO
 * @since   0.1.0
 */
final class RunSeoAuditTool extends AbstractTool
{
    public function __construct(
        private readonly SeoService $seoService,
    ) {}

    public function getName(): string
    {
        return 'wordpress.seo.audit';
    }

    public function getDescription(): string
    {
        return 'Analyzes page content, H-tags, title lengths, and image attributes for SEO recommendations.';
    }

    public function getInputSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'post_id' => [
                    'type'        => 'integer',
                    'description' => 'The post or page ID.',
                    'minimum'     => 1,
                ],
            ],
            'required'             => ['post_id'],
            'additionalProperties' => false,
        ];
    }

    public function getRequiredScopes(): array
    {
        return ['wp-agent:read'];
    }

    public function getAnnotations(): array
    {
        return ['readOnlyHint' => true, 'idempotentHint' => true];
    }

    protected function handle(array $args, Identity $identity): ToolResult
    {
        $this->requireCapability('read', $identity);

        $postId = (int) $args['post_id'];
        $audit  = $this->seoService->runSeoAudit($postId);

        return ToolResult::json($audit);
    }
}
