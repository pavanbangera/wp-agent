<?php

declare(strict_types=1);

namespace WpAgent\Tools\Elementor;

use WpAgent\Auth\Identity;
use WpAgent\MCP\Protocol\ToolResult;
use WpAgent\Services\ElementorService;
use WpAgent\Tools\AbstractTool;

/**
 * Tool: wordpress.elementor.page.setLayout
 *
 * Safe wrapper for setting the Elementor layout on any existing post/page.
 * Unlike calling update_post_meta() directly, this tool enforces wp_slash()
 * on the JSON before saving — preventing the common JSON-corruption bug that
 * causes blank Elementor pages when data is saved programmatically.
 *
 * ⚠️  wp_slash() is required because update_post_meta() calls stripslashes()
 * internally. Without it, escaped quotes (\") and newlines (\n) in the JSON
 * are stripped, corrupting the stored Elementor data and causing the_content()
 * to return empty for the affected page.
 *
 * Required scope: wp-agent:write
 * Required capability: edit_pages
 *
 * @package WpAgent\Tools\Elementor
 * @since   0.1.0
 */
final class SetPageLayoutTool extends AbstractTool
{
    public function __construct(
        private readonly ElementorService $elementorService,
    ) {}

    public function getName(): string
    {
        return 'wordpress.elementor.page.setLayout';
    }

    public function getDescription(): string
    {
        return 'Sets the Elementor layout on an existing post or page by ID. '
            . 'This is the safe way to programmatically write Elementor layout data — '
            . 'it enforces wp_slash() internally so the JSON is stored correctly. '
            . '⚠️  IMPORTANT: If you call update_post_meta() directly for _elementor_data, '
            . 'you MUST use: update_post_meta($id, "_elementor_data", wp_slash(json_encode($layout))). '
            . 'Omitting wp_slash() corrupts the stored JSON and causes blank Elementor pages. '
            . 'This tool handles that correctly so you do not need to pre-slash the layout.';
    }

    public function getInputSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'page_id' => [
                    'type'        => 'integer',
                    'description' => 'The WordPress post/page ID to apply the layout to.',
                    'minimum'     => 1,
                ],
                'layout' => [
                    'type'        => 'array',
                    'description' => 'Elementor layout elements array. Pass raw PHP array — do NOT pre-encode to JSON or call wp_slash().',
                    'items'       => ['type' => 'object'],
                    'default'     => [],
                ],
                'template' => [
                    'type'        => 'string',
                    'description' => 'Elementor page template (e.g. "elementor_header_footer", "elementor_canvas"). Defaults to current template.',
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

    protected function handle(array $args, Identity $identity): ToolResult
    {
        $this->requireCapability('edit_pages', $identity);
        $this->elementorService->requireElementor();

        $pageId   = (int) $args['page_id'];
        $layout   = $args['layout'] ?? [];
        $template = $args['template'] ?? null;

        // Verify page exists.
        $post = get_post($pageId);
        if ( ! ($post instanceof \WP_Post) ) {
            return ToolResult::error("Post with ID {$pageId} not found.");
        }

        // Optionally update the page template.
        if ( null !== $template ) {
            update_post_meta($pageId, '_wp_page_template', sanitize_text_field($template));
        }

        // createPageLayout() internally uses wp_slash() — always safe.
        $this->elementorService->createPageLayout($pageId, $layout);

        return ToolResult::json([
            'success'           => true,
            'page_id'           => $pageId,
            'post_title'        => $post->post_title,
            'layout_elements'   => count($layout),
            'template_applied'  => $template ?? get_post_meta($pageId, '_wp_page_template', true),
            'elementor_enabled' => true,
            'cache_cleared'     => true,
            'message'           => "Elementor layout applied to post #{$pageId} with " . count($layout) . ' root element(s).',
        ]);
    }
}
