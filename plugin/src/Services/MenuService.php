<?php

declare(strict_types=1);

namespace WpAgent\Services;

use WpAgent\Exceptions\ToolException;

/**
 * Navigation Menu management service.
 *
 * Interacts with WordPress core nav menu functions:
 * wp_create_nav_menu(), wp_delete_nav_menu(), wp_get_nav_menu_items(),
 * wp_update_nav_menu_item(), etc.
 *
 * @package WpAgent\Services
 * @since   0.1.0
 */
final class MenuService
{
    private const TOOL_NAME = 'menu_service';

    /**
     * Creates a new navigation menu.
     *
     * @param string $name Name of the menu (e.g. "Main Menu").
     *
     * @return \WP_Term The created menu term.
     *
     * @throws ToolException
     */
    public function create(string $name): \WP_Term
    {
        $menuId = wp_create_nav_menu($name);

        if ( is_wp_error($menuId) ) {
            throw ToolException::fromWpError(self::TOOL_NAME, $menuId);
        }

        $menu = wp_get_nav_menu_object($menuId);

        if ( ! ($menu instanceof \WP_Term) ) {
            throw new ToolException("Failed to retrieve created menu.", self::TOOL_NAME);
        }

        do_action('wpa_menu_created', $menu);

        return $menu;
    }

    /**
     * Lists all navigation menus.
     *
     * @return \WP_Term[]
     */
    public function list(): array
    {
        return wp_get_nav_menus();
    }

    /**
     * Retrieves a single menu by ID or Name.
     *
     * @throws ToolException
     */
    public function get(int|string $menuIdOrName): \WP_Term
    {
        $menu = wp_get_nav_menu_object($menuIdOrName);

        if ( ! ($menu instanceof \WP_Term) ) {
            throw ToolException::notFound(self::TOOL_NAME, 'Menu', $menuIdOrName);
        }

        return $menu;
    }

    /**
     * Deletes a navigation menu.
     *
     * @throws ToolException
     */
    public function delete(int|string $menuIdOrName): bool
    {
        $menu = $this->get($menuIdOrName);

        $result = wp_delete_nav_menu($menu->term_id);

        if ( is_wp_error($result) ) {
            throw ToolException::fromWpError(self::TOOL_NAME, $result);
        }

        if ( false === $result ) {
            throw new ToolException("Failed to delete menu ID {$menu->term_id}.", self::TOOL_NAME);
        }

        do_action('wpa_menu_deleted', $menu->term_id);

        return true;
    }

    /**
     * Retrieves all items assigned to a navigation menu.
     *
     * @return \WP_Post[] List of menu item posts.
     *
     * @throws ToolException
     */
    public function getItems(int|string $menuIdOrName): array
    {
        $menu = $this->get($menuIdOrName);
        $items = wp_get_nav_menu_items($menu->term_id);

        if ( false === $items ) {
            return [];
        }

        return $items;
    }

    /**
     * Replaces or updates the menu items for a menu term.
     *
     * @param int|string           $menuIdOrName Menu identifier.
     * @param array<int, array{
     *     id?: int,
     *     title: string,
     *     type: string,
     *     object_id?: int,
     *     object?: string,
     *     url?: string,
     *     parent_id?: int,
     *     position?: int
     * }> $items Menu items description.
     *
     * @return \WP_Post[] The updated menu items.
     *
     * @throws ToolException
     */
    public function updateItems(int|string $menuIdOrName, array $items): array
    {
        $menu = $this->get($menuIdOrName);

        // Map parent temporary/UI indexes to DB inserted item IDs.
        $tempToDbMap = [];

        // Track items we updated or inserted so we can delete obsolete ones.
        $keptDbIds = [];

        foreach ( $items as $position => $item ) {
            $dbId      = $item['id'] ?? 0;
            $type      = $item['type']; // 'post_type' (pages, posts) or 'custom' (external URLs)
            $parentId  = $item['parent_id'] ?? 0;

            // Resolve parent ID if parent is a temporary ID.
            if ( $parentId > 0 && isset($tempToDbMap[$parentId]) ) {
                $parentId = $tempToDbMap[$parentId];
            }

            $menuItemData = [
                'menu-item-title'     => sanitize_text_field($item['title']),
                'menu-item-position'  => $item['position'] ?? ($position + 1),
                'menu-item-parent-id' => $parentId,
                'menu-item-status'    => 'publish',
            ];

            if ( $type === 'custom' ) {
                $menuItemData['menu-item-type'] = 'custom';
                $menuItemData['menu-item-url']  = esc_url_raw($item['url'] ?? '');
            } else {
                // E.g., 'post', 'page', 'category'.
                $menuItemData['menu-item-type']      = 'post_type';
                $menuItemData['menu-item-object-id'] = (int) ($item['object_id'] ?? 0);
                $menuItemData['menu-item-object']    = sanitize_key($item['object'] ?? 'page');
            }

            $resultId = wp_update_nav_menu_item($menu->term_id, $dbId, $menuItemData);

            if ( is_wp_error($resultId) ) {
                throw ToolException::fromWpError(self::TOOL_NAME, $resultId);
            }

            if ( $resultId > 0 ) {
                $keptDbIds[] = $resultId;
                if ( isset($item['id']) ) {
                    $tempToDbMap[$item['id']] = $resultId;
                }
            }
        }

        // Clean up any items currently in the menu that weren't in the update list.
        $currentItems = wp_get_nav_menu_items($menu->term_id);
        if ( is_array($currentItems) ) {
            foreach ( $currentItems as $currentItem ) {
                if ( ! in_array((int) $currentItem->ID, $keptDbIds, true) ) {
                    wp_delete_post($currentItem->ID, true);
                }
            }
        }

        do_action('wpa_menu_items_updated', $menu->term_id, $keptDbIds);

        return $this->getItems($menu->term_id);
    }
}
