<?php

declare(strict_types=1);

namespace WpAgent\Tools\Elementor;

use WpAgent\Auth\Identity;
use WpAgent\MCP\Protocol\ToolResult;
use WpAgent\Services\ElementorService;
use WpAgent\Tools\AbstractTool;

/**
 * Tool: wordpress.elementor.template.get
 *
 * Retrieves an Elementor template from the library by ID or title, returning
 * its complete layout JSON, type, status, and URL. Eliminates the need for
 * raw PHP calls like get_page_by_title() + get_post_meta() for template lookups.
 *
 * Required scope: wp-agent:read
 * Required capability: edit_posts
 *
 * @package WpAgent\Tools\Elementor
 * @since   0.1.0
 */
final class GetTemplateTool extends AbstractTool
{
    public function __construct(
        private readonly ElementorService $elementorService,
    ) {}

    public function getName(): string
    {
        return 'wordpress.elementor.template.get';
    }

    public function getDescription(): string
    {
        return 'Retrieves an Elementor template from the template library by ID or title. '
            . 'Returns the template\'s layout JSON, type (page/header/footer/popup/section), '
            . 'status, and permalink URL. Use this to verify a template exists or inspect its '
            . 'current layout structure before modifying it.';
    }

    public function getInputSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'id' => [
                    'type'        => 'integer',
                    'description' => 'Template post ID. Use either id or title, not both.',
                    'minimum'     => 1,
                ],
                'title' => [
                    'type'        => 'string',
                    'description' => 'Exact template title. Used if id is not provided.',
                    'minLength'   => 1,
                ],
                'type' => [
                    'type'        => 'string',
                    'description' => 'Optional type filter to validate the found template\'s type.',
                    'enum'        => ['any', 'page', 'section', 'header', 'footer', 'popup', 'kit', 'wp-page', 'wp-post'],
                    'default'     => 'any',
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
        return [
            'readOnlyHint'   => true,
            'destructiveHint' => false,
            'idempotentHint' => true,
        ];
    }

    protected function handle(array $args, Identity $identity): ToolResult
    {
        $this->requireCapability('edit_posts', $identity);
        $this->elementorService->requireElementor();

        if ( empty($args['id']) && empty($args['title']) ) {
            return ToolResult::error('Either id or title must be provided.');
        }

        $idOrTitle = ! empty($args['id']) ? (int) $args['id'] : (string) $args['title'];
        $type      = $args['type'] ?? 'any';

        $template = $this->elementorService->getTemplate($idOrTitle, $type);

        return ToolResult::json(array_merge(
            $template,
            ['layout_elements' => count($template['layout'])]
        ));
    }
}
