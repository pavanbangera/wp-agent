<?php

declare(strict_types=1);

namespace WpAgent\Tools\Menus;

use WpAgent\Auth\Identity;
use WpAgent\MCP\Protocol\ToolResult;
use WpAgent\Services\MenuService;
use WpAgent\Tools\AbstractTool;
use WpAgent\Tools\Concerns\FormatsMenu;

/**
 * Tool: wordpress.menus.list
 *
 * Lists all navigation menus and optionally their child menu items.
 *
 * Required scope: wp-agent:read
 * Required capability: read
 *
 * @package WpAgent\Tools\Menus
 * @since   0.1.0
 */
final class ListMenusTool extends AbstractTool
{
    public function __construct(
        private readonly MenuService $menuService,
    ) {}

    public function getName(): string
    {
        return 'wordpress.menus.list';
    }

    public function getDescription(): string
    {
        return 'Lists all defined WordPress Navigation Menus. '
            . 'If a specific menu_id is provided, lists all menu items assigned '
            . 'to that menu along with their hierarchical positions, links, and nesting structures.';
    }

    public function getInputSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'menu_id' => [
                    'type'        => 'integer',
                    'description' => 'Optional menu term ID. If provided, details of its menu items are returned.',
                    'minimum'     => 1,
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
        return ['readOnlyHint' => true, 'idempotentHint' => true];
    }

    protected function handle(array $args, Identity $identity): ToolResult
    {
        $this->requireCapability('read', $identity);

        $menuId = isset($args['menu_id']) ? (int) $args['menu_id'] : null;

        if ( null !== $menuId ) {
            $menu  = $this->menuService->get($menuId);
            $items = $this->menuService->getItems($menuId);

            return ToolResult::json([
                'menu'  => FormatsMenu::format($menu),
                'items' => array_map(
                    static fn (\WP_Post $item): array => FormatsMenu::formatMenuItem($item),
                    $items
                ),
                'count' => count($items),
            ]);
        }

        // List all menus.
        $menus = $this->menuService->list();

        return ToolResult::json([
            'menus' => array_map(
                static fn (\WP_Term $m): array => FormatsMenu::format($m),
                $menus
            ),
            'count' => count($menus),
        ]);
    }
}
