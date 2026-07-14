<?php

declare(strict_types=1);

namespace WpAgent\Tools\Pages;

use WpAgent\Auth\Identity;
use WpAgent\MCP\Protocol\ToolResult;
use WpAgent\Services\Contracts\PageServiceInterface;
use WpAgent\Tools\AbstractTool;
use WpAgent\Tools\Concerns\FormatsPost;

/**
 * Tool: wordpress.pages.get
 *
 * Retrieves a single page by ID with full details.
 *
 * Required scope: wp-agent:read
 *
 * @package WpAgent\Tools\Pages
 * @since   0.1.0
 */
final class GetPageTool extends AbstractTool
{
    public function __construct(
        private readonly PageServiceInterface $pageService,
    ) {}

    public function getName(): string
    {
        return 'wordpress.pages.get';
    }

    public function getDescription(): string
    {
        return 'Retrieves a single WordPress page by its ID. '
            . 'Returns full page data including content, status, permalink, SEO meta, '
            . 'featured image, template, and author information.';
    }

    public function getInputSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'page_id' => [
                    'type'        => 'integer',
                    'description' => 'The ID of the page to retrieve.',
                    'minimum'     => 1,
                ],
            ],
            'required'             => ['page_id'],
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

        $page = $this->pageService->get((int) $args['page_id']);

        return ToolResult::json(FormatsPost::format($page));
    }
}
