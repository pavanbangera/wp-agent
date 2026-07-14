<?php

declare(strict_types=1);

namespace WpAgent\Tools\Pages;

use WpAgent\Auth\Identity;
use WpAgent\MCP\Protocol\ToolResult;
use WpAgent\Services\Contracts\PageServiceInterface;
use WpAgent\Tools\AbstractTool;
use WpAgent\Tools\Concerns\FormatsPost;

/**
 * Tool: wordpress.pages.list
 *
 * Lists WordPress pages with optional filtering, sorting, and pagination.
 *
 * Required scope: wp-agent:read
 *
 * @package WpAgent\Tools\Pages
 * @since   0.1.0
 */
final class ListPagesTool extends AbstractTool
{
    public function __construct(
        private readonly PageServiceInterface $pageService,
    ) {}

    public function getName(): string
    {
        return 'wordpress.pages.list';
    }

    public function getDescription(): string
    {
        return 'Lists WordPress pages with filtering, sorting, and pagination. '
            . 'Use this to discover existing pages before creating or editing. '
            . 'Returns page IDs, titles, statuses, and permalinks. '
            . 'For full page content, use wordpress.pages.get with a specific ID.';
    }

    public function getInputSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'status'    => [
                    'type'        => 'string',
                    'description' => 'Filter by status. Use "any" for all statuses.',
                    'enum'        => ['any', 'publish', 'draft', 'private', 'future', 'pending', 'trash'],
                    'default'     => 'any',
                ],
                'search'    => [
                    'type'        => 'string',
                    'description' => 'Search query to filter pages by title or content.',
                ],
                'parent_id' => [
                    'type'        => 'integer',
                    'description' => 'Filter by parent page ID. Use 0 for top-level pages.',
                    'minimum'     => 0,
                ],
                'per_page'  => [
                    'type'        => 'integer',
                    'description' => 'Number of pages per page.',
                    'minimum'     => 1,
                    'maximum'     => 100,
                    'default'     => 20,
                ],
                'page'      => [
                    'type'        => 'integer',
                    'description' => 'Page number for pagination.',
                    'minimum'     => 1,
                    'default'     => 1,
                ],
                'orderby'   => [
                    'type'        => 'string',
                    'enum'        => ['date', 'title', 'menu_order', 'modified', 'ID'],
                    'default'     => 'menu_order',
                ],
                'order'     => [
                    'type'        => 'string',
                    'enum'        => ['ASC', 'DESC'],
                    'default'     => 'ASC',
                ],
            ],
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

        $result = $this->pageService->list($args);

        return ToolResult::json([
            'pages'      => array_map(
                static fn (\WP_Post $p): array => FormatsPost::formatListItem($p),
                $result['posts']
            ),
            'total'      => $result['total'],
            'total_pages' => $result['pages'],
            'per_page'   => $args['per_page'] ?? 20,
            'current_page' => $args['page'] ?? 1,
        ]);
    }
}
