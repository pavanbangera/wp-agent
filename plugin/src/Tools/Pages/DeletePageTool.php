<?php

declare(strict_types=1);

namespace WpAgent\Tools\Pages;

use WpAgent\Auth\Identity;
use WpAgent\MCP\Protocol\ToolResult;
use WpAgent\Services\Contracts\PageServiceInterface;
use WpAgent\Tools\AbstractTool;

/**
 * Tool: wordpress.pages.delete
 *
 * Moves a page to trash or permanently deletes it.
 * Defaults to trash (reversible) to protect against accidental loss.
 *
 * Required scope: wp-agent:write
 * Required capability: delete_pages
 *
 * @package WpAgent\Tools\Pages
 * @since   0.1.0
 */
final class DeletePageTool extends AbstractTool
{
    public function __construct(
        private readonly PageServiceInterface $pageService,
    ) {}

    public function getName(): string
    {
        return 'wordpress.pages.delete';
    }

    public function getDescription(): string
    {
        return 'Deletes a WordPress page. By default moves the page to the Trash (recoverable). '
            . 'Set force_delete to true to permanently delete. '
            . 'CAUTION: Permanent deletion cannot be undone. '
            . 'Always confirm page ID before deletion.';
    }

    public function getInputSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'page_id'      => [
                    'type'        => 'integer',
                    'description' => 'The ID of the page to delete.',
                    'minimum'     => 1,
                ],
                'force_delete' => [
                    'type'        => 'boolean',
                    'description' => 'Set to true to permanently delete (bypasses trash). IRREVERSIBLE.',
                    'default'     => false,
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

    public function getAnnotations(): array
    {
        return [
            'readOnlyHint'    => false,
            'destructiveHint' => true,
            'idempotentHint'  => false,
        ];
    }

    protected function handle(array $args, Identity $identity): ToolResult
    {
        $this->requireCapability('delete_pages', $identity);

        $pageId      = (int) $args['page_id'];
        $forceDelete = (bool) ( $args['force_delete'] ?? false );

        $this->pageService->delete($pageId, $forceDelete);

        return ToolResult::json([
            'success'      => true,
            'page_id'      => $pageId,
            'force_delete' => $forceDelete,
            'message'      => $forceDelete
                ? "Page {$pageId} has been permanently deleted."
                : "Page {$pageId} has been moved to the Trash.",
        ]);
    }
}
