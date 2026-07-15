<?php

declare(strict_types=1);

namespace WpAgent\Tools\Elementor;

use WpAgent\Auth\Identity;
use WpAgent\MCP\Protocol\ToolResult;
use WpAgent\Services\ElementorService;
use WpAgent\Tools\AbstractTool;

/**
 * Tool: wordpress.elementor.template.update
 *
 * Updates an existing Elementor template's layout JSON (and optionally its title).
 * Internally enforces wp_slash() to prevent WordPress's stripslashes() from
 * corrupting JSON — callers must pass plain unescaped layout arrays.
 *
 * ⚠️  wp_slash() requirement: When saving Elementor data via update_post_meta(),
 * WordPress internally calls stripslashes() on the value. This strips all JSON
 * backslashes, corrupting any escaped quotes (\"), newlines (\n), etc. — leading
 * to silent blank-page rendering failures. This tool handles that correctly.
 * If you ever call update_post_meta() directly for _elementor_data, you MUST
 * wrap the JSON with wp_slash(): update_post_meta($id, '_elementor_data', wp_slash($json));
 *
 * Required scope: wp-agent:write
 * Required capability: edit_posts
 *
 * @package WpAgent\Tools\Elementor
 * @since   0.1.0
 */
final class UpdateTemplateTool extends AbstractTool
{
    public function __construct(
        private readonly ElementorService $elementorService,
    ) {}

    public function getName(): string
    {
        return 'wordpress.elementor.template.update';
    }

    public function getDescription(): string
    {
        return 'Updates an existing Elementor template\'s layout JSON and optionally its title. '
            . 'Pass the raw layout array — this tool handles wp_slash() internally so the JSON is '
            . 'stored correctly in the database. '
            . '⚠️  IMPORTANT: Do NOT pre-escape or wp_slash() the layout yourself — '
            . 'double-slashing will corrupt the data. '
            . 'Also clears the Elementor CSS cache for this template after saving.';
    }

    public function getInputSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'id' => [
                    'type'        => 'integer',
                    'description' => 'The Elementor template post ID (must be in elementor_library).',
                    'minimum'     => 1,
                ],
                'layout' => [
                    'type'        => 'array',
                    'description' => 'New Elementor layout elements array. Pass raw PHP array — do NOT pre-encode to JSON.',
                    'items'       => ['type' => 'object'],
                ],
                'title' => [
                    'type'        => 'string',
                    'description' => 'Optional new title for the template.',
                    'minLength'   => 1,
                    'maxLength'   => 255,
                ],
            ],
            'required'             => ['id', 'layout'],
            'additionalProperties' => false,
        ];
    }

    public function getRequiredScopes(): array
    {
        return ['wp-agent:write'];
    }

    public function getAnnotations(): array
    {
        return [
            'readOnlyHint'   => false,
            'destructiveHint' => false,
            'idempotentHint' => true,
        ];
    }

    protected function handle(array $args, Identity $identity): ToolResult
    {
        $this->requireCapability('edit_posts', $identity);
        $this->elementorService->requireElementor();

        $templateId = (int) $args['id'];
        $layout     = $args['layout'];
        $title      = $args['title'] ?? null;

        $this->elementorService->updateTemplate($templateId, $layout, $title);

        return ToolResult::json([
            'success'         => true,
            'template_id'     => $templateId,
            'layout_elements' => count($layout),
            'title_updated'   => null !== $title,
            'cache_cleared'   => true,
            'message'         => "Template #{$templateId} updated successfully with " . count($layout) . ' root element(s).',
        ]);
    }
}
