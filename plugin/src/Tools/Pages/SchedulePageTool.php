<?php

declare(strict_types=1);

namespace WpAgent\Tools\Pages;

use WpAgent\Auth\Identity;
use WpAgent\MCP\Protocol\ToolResult;
use WpAgent\Services\Contracts\PageServiceInterface;
use WpAgent\Tools\AbstractTool;
use WpAgent\Tools\Concerns\FormatsPost;

/**
 * Tool: wordpress.pages.schedule
 *
 * Schedules a page to automatically publish at a future date/time.
 *
 * Required scope: wp-agent:write
 * Required capability: publish_pages
 *
 * @package WpAgent\Tools\Pages
 * @since   0.1.0
 */
final class SchedulePageTool extends AbstractTool
{
    public function __construct(
        private readonly PageServiceInterface $pageService,
    ) {}

    public function getName(): string
    {
        return 'wordpress.pages.schedule';
    }

    public function getDescription(): string
    {
        return 'Schedules a WordPress page to automatically publish at a future date and time. '
            . 'The date must be in the future. Uses the site\'s configured timezone. '
            . 'The page status will be set to "future" and WordPress will automatically '
            . 'publish it at the scheduled time via wp-cron.';
    }

    public function getInputSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'page_id'      => [
                    'type'        => 'integer',
                    'description' => 'The ID of the page to schedule.',
                    'minimum'     => 1,
                ],
                'publish_date' => [
                    'type'        => 'string',
                    'description' => 'Future publish date/time in ISO 8601 format (e.g. "2025-12-31T09:00:00"). Must be in the future.',
                    'pattern'     => '^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}',
                ],
            ],
            'required'             => ['page_id', 'publish_date'],
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

        $page = $this->pageService->schedule((int) $args['page_id'], $args['publish_date']);

        return ToolResult::json(array_merge(
            FormatsPost::format($page),
            ['message' => "Page '{$page->post_title}' scheduled to publish at {$args['publish_date']}."]
        ));
    }
}
