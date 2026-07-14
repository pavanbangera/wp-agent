<?php

declare(strict_types=1);

namespace WpAgent\Tools\Pages;

use WpAgent\Auth\Identity;
use WpAgent\MCP\Protocol\ToolResult;
use WpAgent\Services\Contracts\PageServiceInterface;
use WpAgent\Tools\AbstractTool;
use WpAgent\Tools\Concerns\FormatsPost;

/**
 * Tool: wordpress.pages.revisions.restore
 *
 * Restores a page to a specific historical revision.
 *
 * Required scope: wp-agent:write
 * Required capability: edit_pages
 *
 * @package WpAgent\Tools\Pages
 * @since   0.1.0
 */
final class RestoreRevisionTool extends AbstractTool
{
    public function __construct(
        private readonly PageServiceInterface $pageService,
    ) {}

    public function getName(): string
    {
        return 'wordpress.pages.revisions.restore';
    }

    public function getDescription(): string
    {
        return 'Restores a WordPress page to a specific previous revision. '
            . 'Use wordpress.pages.revisions.list first to get the revision_id. '
            . 'The restore operation creates a new revision so the current version is still recoverable.';
    }

    public function getInputSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'page_id'     => [
                    'type'        => 'integer',
                    'description' => 'The ID of the page.',
                    'minimum'     => 1,
                ],
                'revision_id' => [
                    'type'        => 'integer',
                    'description' => 'The ID of the revision to restore (from revisions.list).',
                    'minimum'     => 1,
                ],
            ],
            'required'             => ['page_id', 'revision_id'],
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

        $page = $this->pageService->restoreRevision(
            (int) $args['page_id'],
            (int) $args['revision_id']
        );

        return ToolResult::json(array_merge(
            FormatsPost::format($page),
            [
                'message'     => "Page restored to revision {$args['revision_id']} successfully.",
                'revision_id' => (int) $args['revision_id'],
            ]
        ));
    }
}
