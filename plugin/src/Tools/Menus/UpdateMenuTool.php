<?php

declare(strict_types=1);

namespace WpAgent\Tools\Menus;

use WpAgent\Auth\Identity;
use WpAgent\MCP\Protocol\ToolResult;
use WpAgent\Services\MenuService;
use WpAgent\Tools\AbstractTool;
use WpAgent\Tools\Concerns\FormatsMenu;

/**
 * Tool: wordpress.menus.update
 *
 * Replaces navigation menu items inside a menu.
 *
 * Required scope: wp-agent:write
 * Required capability: edit_theme_options
 *
 * @package WpAgent\Tools\Menus
 * @since   0.1.0
 */
final class UpdateMenuTool extends AbstractTool
{
    public function __construct(
        private readonly MenuService $menuService,
    ) {}

    public function getName(): string
    {
        return 'wordpress.menus.update';
    }

    public function getDescription(): string
    {
        return 'Updates or sets the items/hierarchy of a Navigation Menu. '
            . 'Sends a full list of items. Existing menu items that are not '
            . 'included in the list are deleted automatically from the menu structure. '
            . 'Allows adding page/post links and custom external URLs with nesting parent-child relations.';
    }

    public function getInputSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'menu_id' => [
                    'type'        => 'integer',
                    'description' => 'The ID of the target navigation menu.',
                    'minimum'     => 1,
                ],
                'items'   => [
                    'type'        => 'array',
                    'description' => 'List of menu items in hierarchical order.',
                    'items'       => [
                        'type'       => 'object',
                        'properties' => [
                            'id'        => [
                                'type'        => 'integer',
                                'description' => 'Optional existing menu item ID to update. Leave empty/0 for new items.',
                            ],
                            'title'     => [
                                'type'        => 'string',
                                'description' => 'Label to display in the menu.',
                                'minLength'   => 1,
                            ],
                            'type'      => [
                                'type'        => 'string',
                                'description' => 'Type of item: "post_type" (default) or "custom".',
                                'enum'        => ['post_type', 'custom'],
                                'default'     => 'post_type',
                            ],
                            'object'    => [
                                'type'        => 'string',
                                'description' => 'The post type name (e.g. "page", "post", "category") when type is post_type.',
                                'default'     => 'page',
                            ],
                            'object_id' => [
                                'type'        => 'integer',
                                'description' => 'The original page, post, or category ID when type is post_type.',
                                'minimum'     => 0,
                            ],
                            'url'       => [
                                'type'        => 'string',
                                'description' => 'External target URL when type is custom.',
                            ],
                            'parent_id' => [
                                'type'        => 'integer',
                                'description' => 'ID of parent menu item for nested dropdowns. Can reference temporary IDs from current index list.',
                                'default'     => 0,
                            ],
                            'position'  => [
                                'type'        => 'integer',
                                'description' => 'Order in the menu (1-indexed).',
                            ],
                        ],
                        'required'   => ['title', 'type'],
                    ],
                ],
            ],
            'required'             => ['menu_id', 'items'],
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

        $menuId = (int) $args['menu_id'];
        $items  = (array) $args['items'];

        $updatedItems = $this->menuService->updateItems($menuId, $items);

        return ToolResult::json([
            'success' => true,
            'menu_id' => $menuId,
            'items'   => array_map(
                static fn (\WP_Post $item): array => FormatsMenu::formatMenuItem($item),
                $updatedItems
            ),
            'count'   => count($updatedItems),
        ]);
    }
}
