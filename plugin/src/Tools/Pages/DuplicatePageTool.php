<?php

declare(strict_types=1);

namespace WpAgent\Tools\Pages;

use WpAgent\Auth\Identity;
use WpAgent\MCP\Protocol\ToolResult;
use WpAgent\Services\Contracts\PageServiceInterface;
use WpAgent\Tools\AbstractTool;
use WpAgent\Tools\Concerns\FormatsPost;

/**
 * Tool: wordpress.pages.duplicate
 *
 * Creates an exact draft copy of an existing page, including all
 * content, meta, and taxonomy assignments.
 *
 * Required scope: wp-agent:write
 * Required capability: edit_pages
 *
 * @package WpAgent\Tools\Pages
 * @since   0.1.0
 */
final class DuplicatePageTool extends AbstractTool
{
    public function __construct(
        private readonly PageServiceInterface $pageService,
    ) {}

    public function getName(): string
    {
        return 'wordpress.pages.duplicate';
    }

    public function getDescription(): string
    {
        return 'Creates a draft copy of an existing page. '
            . 'Copies all content, custom fields (post meta), template, and taxonomy assignments. '
            . 'The duplicate is always created as a draft to prevent accidental publishing. '
            . 'Returns the new page object.';
    }

    public function getInputSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'page_id'      => [
                    'type'        => 'integer',
                    'description' => 'The ID of the page to duplicate.',
                    'minimum'     => 1,
                ],
                'title_suffix' => [
                    'type'        => 'string',
                    'description' => 'Suffix to append to the duplicated page title.',
                    'default'     => ' (Copy)',
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

        $pageId      = (int) $args['page_id'];
        $titleSuffix = $args['title_suffix'] ?? ' (Copy)';

        $duplicate = $this->pageService->duplicate($pageId, $titleSuffix);

        return ToolResult::json(array_merge(
            FormatsPost::format($duplicate),
            ['source_page_id' => $pageId]
        ));
    }
}
