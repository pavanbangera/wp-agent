<?php

declare(strict_types=1);

namespace WpAgent\Tools\Elementor;

use WpAgent\Auth\Identity;
use WpAgent\Exceptions\ToolException;
use WpAgent\MCP\Protocol\ToolResult;
use WpAgent\Services\ElementorService;
use WpAgent\Tools\AbstractTool;

/**
 * Tool: wordpress.elementor.template.import
 *
 * Required scope: wp-agent:write
 * Required capability: edit_posts
 *
 * @package WpAgent\Tools\Elementor
 * @since   0.1.0
 */
final class ImportTemplateTool extends AbstractTool
{
    public function __construct(
        private readonly ElementorService $elementorService,
    ) {}

    public function getName(): string
    {
        return 'wordpress.elementor.template.import';
    }

    public function getDescription(): string
    {
        return 'Imports an Elementor template JSON structure into the library.';
    }

    public function getInputSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'title'       => [
                    'type'        => 'string',
                    'description' => 'The title for the imported template.',
                    'minLength'   => 1,
                ],
                'layout_data' => [
                    'type'        => 'array',
                    'description' => 'The Elementor structured elements JSON layout array.',
                    'items'       => ['type' => 'object'],
                ],
                'type'        => [
                    'type'        => 'string',
                    'description' => 'The template type (e.g. "page", "section").',
                    'enum'        => ['page', 'section', 'header', 'footer', 'popup'],
                    'default'     => 'page',
                ],
            ],
            'required'             => ['title', 'layout_data'],
            'additionalProperties' => false,
        ];
    }

    public function getRequiredScopes(): array
    {
        return ['wp-agent:write'];
    }

    protected function handle(array $args, Identity $identity): ToolResult
    {
        $this->requireCapability('edit_posts', $identity);
        $this->elementorService->requireElementor();

        $title      = $args['title'];
        $layoutData = (array) $args['layout_data'];
        $type       = $args['type'] ?? 'page';

        $postData = [
            'post_type'    => 'elementor_library',
            'post_title'   => sanitize_text_field($title),
            'post_status'  => 'publish',
            'post_content' => '',
        ];

        $templateId = wp_insert_post($postData, true);

        if ( is_wp_error($templateId) ) {
            throw ToolException::fromWpError(self::TOOL_NAME, $templateId);
        }

        update_post_meta($templateId, '_elementor_data', wp_json_encode($layoutData));
        update_post_meta($templateId, '_elementor_edit_mode', 'builder');
        update_post_meta($templateId, '_elementor_template_type', sanitize_key($type));

        return ToolResult::json([
            'success'     => true,
            'template_id' => $templateId,
            'title'       => $title,
            'type'        => $type,
            'message'     => 'Elementor template successfully imported into library.',
        ]);
    }
}
