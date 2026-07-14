<?php

declare(strict_types=1);

namespace WpAgent\Tools\Concerns;

/**
 * Normalizes WP_Term menu objects and menu item posts to consistent MCP outputs.
 *
 * @package WpAgent\Tools\Concerns
 * @since   0.1.0
 */
final class FormatsMenu
{
    /**
     * Formats a menu term object.
     *
     * @return array<string, mixed>
     */
    public static function format(\WP_Term $menu): array
    {
        return [
            'id'    => $menu->term_id,
            'name'  => $menu->name,
            'slug'  => $menu->slug,
            'count' => (int) $menu->count,
        ];
    }

    /**
     * Formats a menu item post object.
     *
     * @return array<string, mixed>
     */
    public static function formatMenuItem(\WP_Post $item): array
    {
        return [
            'id'        => $item->ID,
            'title'     => $item->title ?: $item->post_title, // Nav menu items store label in title.
            'type'      => $item->type ?: $item->post_mime_type, // custom or post_type
            'object'    => $item->object, // e.g. page, category, post
            'object_id' => (int) $item->object_id, // original post/term ID
            'url'       => $item->url, // link target
            'parent_id' => (int) $item->menu_item_parent, // structural hierarchy parent
            'position'  => (int) $item->menu_order,
        ];
    }
}
