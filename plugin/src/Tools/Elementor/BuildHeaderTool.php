<?php

declare(strict_types=1);

namespace WpAgent\Tools\Elementor;

use WpAgent\Auth\Identity;
use WpAgent\Exceptions\ToolException;
use WpAgent\MCP\Protocol\ToolResult;
use WpAgent\Services\ElementorService;
use WpAgent\Tools\AbstractTool;

/**
 * Tool: wordpress.elementor.header.build
 *
 * Required scope: wp-agent:write
 * Required capability: edit_theme_options
 *
 * @package WpAgent\Tools\Elementor
 * @since   0.1.0
 */
final class BuildHeaderTool extends AbstractTool
{
    public function __construct(
        private readonly ElementorService $elementorService,
    ) {}

    public function getName(): string
    {
        return 'wordpress.elementor.header.build';
    }

    public function getDescription(): string
    {
        return 'Creates a new Header template inside the Elementor Library.';
    }

    public function getInputSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'title'  => [
                    'type'        => 'string',
                    'description' => 'The title of the header template (e.g. "Main Header").',
                    'minLength'   => 1,
                ],
                'layout' => [
                    'type'        => 'array',
                    'description' => 'The elements JSON layout array.',
                    'items'       => ['type' => 'object'],
                    'default'     => [],
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
        $this->requireCapability('edit_theme_options', $identity);
        $this->elementorService->requireElementor();

        $title  = $args['title'];
        $layout = $args['layout'] ?? [];

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

        update_post_meta($templateId, '_elementor_data', wp_json_encode($layout));
        update_post_meta($templateId, '_elementor_edit_mode', 'builder');
        update_post_meta($templateId, '_elementor_template_type', 'header');

        return ToolResult::json([
            'success'     => true,
            'template_id' => $templateId,
            'title'       => $title,
            'message'     => 'Elementor Header template successfully created.',
        ]);
    }
}
