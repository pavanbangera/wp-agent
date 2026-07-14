<?php

declare(strict_types=1);

namespace WpAgent\Tools\Pages;

use WpAgent\Auth\Identity;
use WpAgent\MCP\Protocol\ToolResult;
use WpAgent\Services\Contracts\PageServiceInterface;
use WpAgent\Tools\AbstractTool;
use WpAgent\Tools\Concerns\FormatsPost;

/**
 * Tool: wordpress.pages.publish
 *
 * Immediately publishes a page by setting its status to 'publish'.
 *
 * Required scope: wp-agent:write
 * Required capability: publish_pages
 *
 * @package WpAgent\Tools\Pages
 * @since   0.1.0
 */
final class PublishPageTool extends AbstractTool
{
    public function __construct(
        private readonly PageServiceInterface $pageService,
    ) {}

    public function getName(): string
    {
        return 'wordpress.pages.publish';
    }

    public function getDescription(): string
    {
        return 'Publishes a WordPress page immediately. '
            . 'Transitions the page from any status (draft, pending, private) to published. '
            . 'Sets the publish date to the current time if not already set.';
    }

    public function getInputSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'page_id' => [
                    'type'        => 'integer',
                    'description' => 'The ID of the page to publish.',
                    'minimum'     => 1,
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
        $this->requireCapability('publish_pages', $identity);

        $page = $this->pageService->publish((int) $args['page_id']);

        return ToolResult::json(array_merge(
            FormatsPost::format($page),
            ['message' => "Page '{$page->post_title}' has been published successfully."]
        ));
    }
}
