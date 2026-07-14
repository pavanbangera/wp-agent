<?php

declare(strict_types=1);

namespace WpAgent\Tools\Elementor;

use WpAgent\Auth\Identity;
use WpAgent\MCP\Protocol\ToolResult;
use WpAgent\Services\ElementorService;
use WpAgent\Tools\AbstractTool;

/**
 * Tool: wordpress.elementor.widget.insert
 *
 * Inserts a widget (Heading, Text, Button, Image, etc.) into an Elementor container.
 *
 * Required scope: wp-agent:write
 * Required capability: edit_pages
 *
 * @package WpAgent\Tools\Elementor
 * @since   0.1.0
 */
final class InsertWidgetTool extends AbstractTool
{
    public function __construct(
        private readonly ElementorService $elementorService,
    ) {}

    public function getName(): string
    {
        return 'wordpress.elementor.widget.insert';
    }

    public function getDescription(): string
    {
        return 'Inserts an Elementor widget (heading, text editor, image, button, icon, etc.) '
            . 'into a target section/container ID on an Elementor page.';
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
                    'description' => 'The unique ID of the section/container to insert the widget into.',
                    'minLength'   => 1,
                ],
                'widget'       => [
                    'type'       => 'object',
                    'description' => 'The widget layout and configuration payload.',
                    'properties' => [
                        'id'       => [
                            'type'        => 'string',
                            'description' => 'Unique widget ID (alphanumeric, e.g. "w2129a").',
                        ],
                        'elType'   => [
                            'type'        => 'string',
                            'description' => 'Must be "widget".',
                            'enum'        => ['widget'],
                        ],
                        'widgetType' => [
                            'type'        => 'string',
                            'description' => 'The type of widget (e.g. "heading", "image", "button", "text-editor").',
                        ],
                        'settings'   => [
                            'type'        => 'object',
                            'description' => 'Settings for the widget (e.g. alignment, colors, title, link).',
                        ],
                    ],
                    'required'   => ['id', 'elType', 'widgetType', 'settings'],
                ],
            ],
            'required'             => ['page_id', 'container_id', 'widget'],
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

        $pageId      = (int) $args['page_id'];
        $containerId = $args['container_id'];
        $widget      = (array) $args['widget'];

        $updatedLayout = $this->elementorService->insertWidget($pageId, $containerId, $widget);

        return ToolResult::json([
            'success'      => true,
            'page_id'      => $pageId,
            'container_id' => $containerId,
            'widget_id'    => $widget['id'],
            'message'      => "Widget '{$widget['widgetType']}' successfully inserted into container '{$containerId}'.",
        ]);
    }
}
