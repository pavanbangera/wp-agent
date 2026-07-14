<?php

declare(strict_types=1);

namespace WpAgent\Tools\Elementor;

use WpAgent\Auth\Identity;
use WpAgent\MCP\Protocol\ToolResult;
use WpAgent\Services\ElementorService;
use WpAgent\Tools\AbstractTool;

/**
 * Tool: wordpress.elementor.container.create
 *
 * Appends a new top-level container/section into an Elementor page.
 *
 * Required scope: wp-agent:write
 * Required capability: edit_pages
 *
 * @package WpAgent\Tools\Elementor
 * @since   0.1.0
 */
final class CreateContainerTool extends AbstractTool
{
    public function __construct(
        private readonly ElementorService $elementorService,
    ) {}

    public function getName(): string
    {
        return 'wordpress.elementor.container.create';
    }

    public function getDescription(): string
    {
        return 'Creates and appends a new top-level Container or Section into an Elementor page layout.';
    }

    public function getInputSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'page_id'      => [
                    'type'        => 'integer',
                    'description' => 'The ID of the Elementor page.',
                    'minimum'     => 1,
                ],
                'container_id' => [
                    'type'        => 'string',
                    'description' => 'Unique ID for the new container (e.g. "c98a213").',
                    'minLength'   => 1,
                ],
                'settings'     => [
                    'type'        => 'object',
                    'description' => 'Optional settings for the container (e.g. padding, background color).',
                    'default'     => new \stdClass(),
                ],
            ],
            'required'             => ['page_id', 'container_id'],
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

        $pageId      = (int) $args['page_id'];
        $containerId = $args['container_id'];
        $settings    = (array) ($args['settings'] ?? []);

        $rawMeta = get_post_meta($pageId, '_elementor_data', true);
        $layout  = empty($rawMeta) ? [] : json_decode($rawMeta, true);

        if ( ! is_array($layout) ) {
            $layout = [];
        }

        // Create Container node.
        $newContainer = [
            'id'       => $containerId,
            'elType'   => 'container',
            'settings' => $settings,
            'elements' => [],
        ];

        $layout[] = $newContainer;
        $this->elementorService->createPageLayout($pageId, $layout);

        return ToolResult::json([
            'success'      => true,
            'page_id'      => $pageId,
            'container_id' => $containerId,
            'message'      => "Top-level container '{$containerId}' appended to layout successfully.",
        ]);
    }
}
