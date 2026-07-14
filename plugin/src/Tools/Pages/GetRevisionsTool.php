<?php

declare(strict_types=1);

namespace WpAgent\Tools\Pages;

use WpAgent\Auth\Identity;
use WpAgent\MCP\Protocol\ToolResult;
use WpAgent\Services\Contracts\PageServiceInterface;
use WpAgent\Tools\AbstractTool;
use WpAgent\Tools\Concerns\FormatsPost;

/**
 * Tool: wordpress.pages.revisions.list
 *
 * Returns the full revision history for a page.
 *
 * Required scope: wp-agent:read
 *
 * @package WpAgent\Tools\Pages
 * @since   0.1.0
 */
final class GetRevisionsTool extends AbstractTool
{
    public function __construct(
        private readonly PageServiceInterface $pageService,
    ) {}

    public function getName(): string
    {
        return 'wordpress.pages.revisions.list';
    }

    public function getDescription(): string
    {
        return 'Returns the revision history for a WordPress page. '
            . 'Each revision includes its ID, date, and author. '
            . 'Use the revision ID with wordpress.pages.revisions.restore to revert.';
    }

    public function getInputSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'page_id' => [
                    'type'        => 'integer',
                    'description' => 'The ID of the page.',
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
        $this->requireCapability('edit_pages', $identity);

        $revisions = $this->pageService->getRevisions((int) $args['page_id']);

        return ToolResult::json([
            'page_id'   => $args['page_id'],
            'count'     => count($revisions),
            'revisions' => array_map(
                static fn (\WP_Post $r): array => FormatsPost::formatRevision($r),
                $revisions
            ),
        ]);
    }
}
