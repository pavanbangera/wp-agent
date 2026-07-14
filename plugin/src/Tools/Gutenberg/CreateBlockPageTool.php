<?php

declare(strict_types=1);

namespace WpAgent\Tools\Gutenberg;

use WpAgent\Auth\Identity;
use WpAgent\MCP\Protocol\ToolResult;
use WpAgent\Services\Contracts\PageServiceInterface;
use WpAgent\Tools\AbstractTool;
use WpAgent\Tools\Concerns\FormatsPost;

/**
 * Tool: wordpress.gutenberg.page.create
 *
 * Creates a page with Gutenberg block editor compatibility.
 *
 * Required scope: wp-agent:write
 * Required capability: edit_pages
 *
 * @package WpAgent\Tools\Gutenberg
 * @since   0.1.0
 */
final class CreateBlockPageTool extends AbstractTool
{
    public function __construct(
        private readonly PageServiceInterface $pageService,
    ) {}

    public function getName(): string
    {
        return 'wordpress.gutenberg.page.create';
    }

    public function getDescription(): string
    {
        return 'Creates a new page with structural Gutenberg block editor HTML comments.';
    }

    public function getInputSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'title'   => [
                    'type'        => 'string',
                    'description' => 'The page title.',
                    'minLength'   => 1,
                ],
                'content' => [
                    'type'        => 'string',
                    'description' => 'Standard Gutenberg block markup content.',
                    'default'     => '',
                ],
                'status'  => [
                    'type'        => 'string',
                    'enum'        => ['draft', 'publish', 'private'],
                    'default'     => 'draft',
                ],
            ],
            'required'             => ['title'],
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

        $page = $this->pageService->create([
            'title'   => $args['title'],
            'content' => $args['content'] ?? '',
            'status'  => $args['status'] ?? 'draft',
        ]);

        return ToolResult::json(FormatsPost::format($page));
    }
}
