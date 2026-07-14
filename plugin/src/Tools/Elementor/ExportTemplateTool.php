<?php

declare(strict_types=1);

namespace WpAgent\Tools\Elementor;

use WpAgent\Auth\Identity;
use WpAgent\Exceptions\ToolException;
use WpAgent\MCP\Protocol\ToolResult;
use WpAgent\Services\ElementorService;
use WpAgent\Tools\AbstractTool;

/**
 * Tool: wordpress.elementor.template.export
 *
 * Required scope: wp-agent:read
 * Required capability: edit_posts
 *
 * @package WpAgent\Tools\Elementor
 * @since   0.1.0
 */
final class ExportTemplateTool extends AbstractTool
{
    public function __construct(
        private readonly ElementorService $elementorService,
    ) {}

    public function getName(): string
    {
        return 'wordpress.elementor.template.export';
    }

    public function getDescription(): string
    {
        return 'Exports an Elementor template\'s layout structures and settings CPT data.';
    }

    public function getInputSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'template_id' => [
                    'type'        => 'integer',
                    'description' => 'The ID of the template post.',
                    'minimum'     => 1,
                ],
            ],
            'required'             => ['template_id'],
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
        $this->requireCapability('edit_posts', $identity);
        $this->elementorService->requireElementor();

        $templateId = (int) $args['template_id'];
        $post       = get_post($templateId);

        if ( ! ($post instanceof \WP_Post) || $post->post_type !== 'elementor_library' ) {
            throw ToolException::notFound(self::TOOL_NAME, 'Elementor Template', $templateId);
        }

        $layoutRaw  = get_post_meta($templateId, '_elementor_data', true);
        $layoutData = empty($layoutRaw) ? [] : json_decode($layoutRaw, true);
        $type       = get_post_meta($templateId, '_elementor_template_type', true) ?: 'page';

        return ToolResult::json([
            'template_id' => $templateId,
            'title'       => $post->post_title,
            'type'        => $type,
            'layout_data' => is_array($layoutData) ? $layoutData : [],
        ]);
    }
}
