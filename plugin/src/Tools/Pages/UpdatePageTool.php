<?php

declare(strict_types=1);

namespace WpAgent\Tools\Pages;

use WpAgent\Auth\Identity;
use WpAgent\MCP\Protocol\ToolResult;
use WpAgent\Services\Contracts\PageServiceInterface;
use WpAgent\Tools\AbstractTool;
use WpAgent\Tools\Concerns\FormatsPost;

/**
 * Tool: wordpress.pages.update
 *
 * Updates an existing WordPress page. Only fields that are provided
 * will be changed — omitted fields retain their current values.
 *
 * Required scope: wp-agent:write
 * Required capability: edit_pages
 *
 * @package WpAgent\Tools\Pages
 * @since   0.1.0
 */
final class UpdatePageTool extends AbstractTool
{
    public function __construct(
        private readonly PageServiceInterface $pageService,
    ) {}

    public function getName(): string
    {
        return 'wordpress.pages.update';
    }

    public function getDescription(): string
    {
        return 'Updates an existing WordPress page by ID. '
            . 'Only the fields you provide will be modified — all other fields remain unchanged. '
            . 'Supports content, title, status, SEO meta, template, and all standard page fields.';
    }

    public function getInputSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'page_id'         => [
                    'type'        => 'integer',
                    'description' => 'The ID of the page to update.',
                    'minimum'     => 1,
                ],
                'title'           => [
                    'type'        => 'string',
                    'description' => 'New page title.',
                    'minLength'   => 1,
                ],
                'content'         => [
                    'type'        => 'string',
                    'description' => 'New page content (HTML or Gutenberg blocks).',
                ],
                'excerpt'         => [
                    'type'        => 'string',
                    'description' => 'New page excerpt.',
                ],
                'status'          => [
                    'type'        => 'string',
                    'description' => 'New publication status.',
                    'enum'        => ['draft', 'publish', 'private', 'pending', 'trash'],
                ],
                'slug'            => [
                    'type'        => 'string',
                    'description' => 'New URL slug.',
                ],
                'parent_id'       => [
                    'type'        => 'integer',
                    'description' => 'New parent page ID.',
                    'minimum'     => 0,
                ],
                'template'        => [
                    'type'        => 'string',
                    'description' => 'Page template filename.',
                ],
                'menu_order'      => [
                    'type'        => 'integer',
                    'description' => 'Menu order position.',
                ],
                'comment_status'  => [
                    'type'        => 'string',
                    'enum'        => ['open', 'closed'],
                ],
                'seo_title'       => [
                    'type'        => 'string',
                    'description' => 'SEO meta title.',
                ],
                'seo_description' => [
                    'type'        => 'string',
                    'description' => 'SEO meta description.',
                ],
            ],
            'required'             => ['page_id'],
            'additionalProperties' => false,
        ];
    }

    public function getRequiredScopes(): array
    {
        return ['wp-agent:write'];
    }

    protected function handle(array $args, Identity $identity): ToolResult
    {
        $this->requireCapability('edit_pages', $identity);

        $pageId = (int) $args['page_id'];
        unset($args['page_id']);

        $page = $this->pageService->update($pageId, $args);

        return ToolResult::json(FormatsPost::format($page));
    }
}
