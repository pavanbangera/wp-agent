<?php

declare(strict_types=1);

namespace WpAgent\Tools\Elementor;

use WpAgent\Auth\Identity;
use WpAgent\Exceptions\ToolException;
use WpAgent\MCP\Protocol\ToolResult;
use WpAgent\Services\ElementorService;
use WpAgent\Tools\AbstractTool;

/**
 * Tool: wordpress.elementor.template.duplicate
 *
 * Duplicates a library template from the Elementor Library (elementor_library CPT).
 *
 * Required scope: wp-agent:write
 * Required capability: edit_posts
 *
 * @package WpAgent\Tools\Elementor
 * @since   0.1.0
 */
final class DuplicateTemplateTool extends AbstractTool
{
    public function __construct(
        private readonly ElementorService $elementorService,
    ) {}

    public function getName(): string
    {
        return 'wordpress.elementor.template.duplicate';
    }

    public function getDescription(): string
    {
        return 'Duplicates an existing Elementor Library template. '
            . 'Copies all layout structure JSON, settings, and templates type tags.';
    }

    public function getInputSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'template_id'  => [
                    'type'        => 'integer',
                    'description' => 'The ID of the template post in Elementor library.',
                    'minimum'     => 1,
                ],
                'new_title'    => [
                    'type'        => 'string',
                    'description' => 'Title for the duplicated template (e.g. "Header - Copy").',
                    'minLength'   => 1,
                ],
            ],
            'required'             => ['template_id', 'new_title'],
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

        $templateId = (int) $args['template_id'];
        $newTitle   = $args['new_title'];

        $original = get_post($templateId);

        if ( ! ($original instanceof \WP_Post) || $original->post_type !== 'elementor_library' ) {
            throw ToolException::notFound(self::TOOL_NAME, 'Elementor Template', $templateId);
        }

        // Copy CPT data.
        $newPostData = [
            'post_type'    => 'elementor_library',
            'post_title'   => sanitize_text_field($newTitle),
            'post_status'  => 'publish',
            'post_content' => $original->post_content,
        ];

        $newId = wp_insert_post($newPostData, true);

        if ( is_wp_error($newId) ) {
            throw ToolException::fromWpError(self::TOOL_NAME, $newId);
        }

        // Copy Meta.
        $metaKeys = [
            '_elementor_data',
            '_elementor_edit_mode',
            '_elementor_template_type',
            '_elementor_page_settings',
        ];

        foreach ( $metaKeys as $key ) {
            $val = get_post_meta($templateId, $key, true);
            if ( ! empty($val) ) {
                update_post_meta($newId, $key, $val);
            }
        }

        return ToolResult::json([
            'success'      => true,
            'template_id'  => $newId,
            'title'        => $newTitle,
            'original_id'  => $templateId,
            'message'      => "Elementor Library template duplicated successfully with ID #{$newId}.",
        ]);
    }
}
