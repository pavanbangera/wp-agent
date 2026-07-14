<?php

declare(strict_types=1);

namespace WpAgent\Tools\Posts;

use WpAgent\Auth\Identity;
use WpAgent\MCP\Protocol\ToolResult;
use WpAgent\Services\Contracts\PostServiceInterface;
use WpAgent\Tools\AbstractTool;
use WpAgent\Tools\Concerns\FormatsPost;

/**
 * Tool: wordpress.posts.list
 *
 * @package WpAgent\Tools\Posts
 * @since   0.1.0
 */
final class ListPostsTool extends AbstractTool
{
    public function __construct(private readonly PostServiceInterface $postService) {}

    public function getName(): string { return 'wordpress.posts.list'; }

    public function getDescription(): string
    {
        return 'Lists WordPress posts with filtering by status, category, tag, author, '
            . 'search query, and sorting. Returns paginated results with post IDs, '
            . 'titles, and status for efficient browsing.';
    }

    public function getInputSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'status'      => ['type' => 'string', 'enum' => ['any', 'publish', 'draft', 'private', 'future', 'pending', 'trash'], 'default' => 'any'],
                'search'      => ['type' => 'string', 'description' => 'Search query.'],
                'category_id' => ['type' => 'integer', 'description' => 'Filter by category ID.', 'minimum' => 1],
                'tag'         => ['type' => 'string', 'description' => 'Filter by tag slug.'],
                'author_id'   => ['type' => 'integer', 'description' => 'Filter by author user ID.', 'minimum' => 1],
                'per_page'    => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20],
                'page'        => ['type' => 'integer', 'minimum' => 1, 'default' => 1],
                'orderby'     => ['type' => 'string', 'enum' => ['date', 'title', 'modified', 'ID', 'comment_count', 'rand'], 'default' => 'date'],
                'order'       => ['type' => 'string', 'enum' => ['ASC', 'DESC'], 'default' => 'DESC'],
            ],
            'additionalProperties' => false,
        ];
    }

    public function getRequiredScopes(): array { return ['wp-agent:read']; }

    public function getAnnotations(): array { return ['readOnlyHint' => true, 'idempotentHint' => true]; }

    protected function handle(array $args, Identity $identity): ToolResult
    {
        $this->requireCapability('read', $identity);

        $result = $this->postService->list($args);

        return ToolResult::json([
            'posts'       => array_map(
                static fn (\WP_Post $p): array => FormatsPost::formatListItem($p),
                $result['posts']
            ),
            'total'       => $result['total'],
            'total_pages' => $result['pages'],
            'per_page'    => $args['per_page'] ?? 20,
            'current_page' => $args['page'] ?? 1,
        ]);
    }
}
