<?php

declare(strict_types=1);

namespace WpAgent\Tools\Pages;

use WpAgent\Auth\Identity;
use WpAgent\MCP\Protocol\ToolResult;
use WpAgent\Services\Contracts\PageServiceInterface;
use WpAgent\Tools\AbstractTool;
use WpAgent\Tools\Concerns\FormatsPost;

/**
 * Tool: wordpress.pages.create
 *
 * Creates a new WordPress page with full content, SEO meta,
 * template, and parent page support.
 *
 * Required scope: wp-agent:write
 * Required capability: edit_pages
 *
 * @package WpAgent\Tools\Pages
 * @since   0.1.0
 */
final class CreatePageTool extends AbstractTool
{
    public function __construct(
        private readonly PageServiceInterface $pageService,
    ) {}

    public function getName(): string
    {
        return 'wordpress.pages.create';
    }

    public function getDescription(): string
    {
        return 'Creates a new WordPress page. Supports all standard page fields including '
            . 'title, content (HTML or Gutenberg blocks), excerpt, slug, parent page, '
            . 'template, SEO meta, and publication status. '
            . 'Returns the created page object with its ID for use in subsequent operations.';
    }

    public function getInputSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'title'           => [
                    'type'        => 'string',
                    'description' => 'The page title.',
                    'minLength'   => 1,
                    'maxLength'   => 1000,
                ],
                'content'         => [
                    'type'        => 'string',
                    'description' => 'Page content. Accepts HTML or valid Gutenberg block markup.',
                    'default'     => '',
                ],
                'excerpt'         => [
                    'type'        => 'string',
                    'description' => 'Short excerpt/summary of the page.',
                    'default'     => '',
                ],
                'status'          => [
                    'type'        => 'string',
                    'description' => 'Publication status.',
                    'enum'        => ['draft', 'publish', 'private', 'pending'],
                    'default'     => 'draft',
                ],
                'slug'            => [
                    'type'        => 'string',
                    'description' => 'URL slug. Auto-generated from title if omitted.',
                ],
                'parent_id'       => [
                    'type'        => 'integer',
                    'description' => 'ID of the parent page (for hierarchical structure).',
                    'minimum'     => 0,
                    'default'     => 0,
                ],
                'template'        => [
                    'type'        => 'string',
                    'description' => 'Page template filename (e.g. "page-full-width.php"). Leave empty for default.',
                    'default'     => '',
                ],
                'menu_order'      => [
                    'type'        => 'integer',
                    'description' => 'Order in navigation menus.',
                    'default'     => 0,
                ],
                'comment_status'  => [
                    'type'        => 'string',
                    'enum'        => ['open', 'closed'],
                    'default'     => 'closed',
                ],
                'seo_title'       => [
                    'type'        => 'string',
                    'description' => 'SEO meta title (works with Yoast SEO, RankMath, AIOSEO).',
                ],
                'seo_description' => [
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

    public function getAnnotations(): array
    {
        return [
            'readOnlyHint'    => false,
            'destructiveHint' => false,
            'idempotentHint'  => false,
        ];
    }

    protected function handle(array $args, Identity $identity): ToolResult
    {
        $this->requireCapability('edit_pages', $identity);

        $page = $this->pageService->create($args);

        return ToolResult::json(FormatsPost::format($page));
    }
}
