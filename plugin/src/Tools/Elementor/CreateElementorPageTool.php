<?php

declare(strict_types=1);

namespace WpAgent\Tools\Elementor;

use WpAgent\Auth\Identity;
use WpAgent\MCP\Protocol\ToolResult;
use WpAgent\Services\Contracts\PageServiceInterface;
use WpAgent\Services\ElementorService;
use WpAgent\Tools\AbstractTool;
use WpAgent\Tools\Concerns\FormatsPost;

/**
 * Tool: wordpress.elementor.page.create
 *
 * Creates a new Page and activates Elementor editor mode on it with a structured layout.
 *
 * Required scope: wp-agent:write
 * Required capability: edit_pages
 *
 * @package WpAgent\Tools\Elementor
 * @since   0.1.0
 */
final class CreateElementorPageTool extends AbstractTool
{
    public function __construct(
        private readonly PageServiceInterface $pageService,
        private readonly ElementorService     $elementorService,
    ) {}

    public function getName(): string
    {
        return 'wordpress.elementor.page.create';
    }

    public function getDescription(): string
    {
        return 'Creates a new WordPress page and configures it to use the Elementor page builder. '
            . 'Allows passing an optional layout structure containing containers, columns, and widgets.';
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
                'layout'  => [
                    'type'        => 'array',
                    'description' => 'Elementor layout elements structure array.',
                    'items'       => ['type' => 'object'],
                    'default'     => [],
                ],
                'status'  => [
                    'type'        => 'string',
                    'enum'        => ['draft', 'publish', 'private'],
                    'default'     => 'draft',
                ],
                'template' => [
                    'type'        => 'string',
                    'description' => 'E.g. "elementor_header_footer" (Elementor Full Width) or "elementor_canvas".',
                    'default'     => 'elementor_header_footer',
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
        $this->elementorService->requireElementor();

        $title    = $args['title'];
        $layout   = $args['layout'] ?? [];
        $status   = $args['status'] ?? 'draft';
        $template = $args['template'] ?? 'elementor_header_footer';

        // 1. Create the page.
        $page = $this->pageService->create([
            'title'    => $title,
            'status'   => $status,
            'template' => $template,
        ]);

        // 2. Set up the Elementor layout metadata.
        $this->elementorService->createPageLayout($page->ID, $layout);

        return ToolResult::json(array_merge(
            FormatsPost::format($page),
            [
                'elementor_enabled' => true,
                'layout_elements'   => count($layout),
            ]
        ));
    }
}
