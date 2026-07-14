<?php

declare(strict_types=1);

namespace WpAgent\Services;

use WpAgent\Exceptions\ToolException;

/**
 * Elementor Page Builder integration service.
 *
 * Interacts with Elementor core models, kits, template library, and CPT structures.
 *
 * @package WpAgent\Services
 * @since   0.1.0
 */
final class ElementorService
{
    private const TOOL_NAME = 'elementor_service';

    /**
     * Asserts that Elementor is installed and active.
     *
     * @throws ToolException
     */
    public function requireElementor(): void
    {
        if ( ! defined('ELEMENTOR_VERSION') ) {
            throw new ToolException(
                'Elementor plugin is not active. Please install and activate Elementor first.',
                self::TOOL_NAME,
                ToolException::OPERATION_FAILED
            );
        }
    }

    /**
     * Sets up a page to be edited in Elementor and sets its JSON layout.
     *
     * @param int                  $pageId The post/page ID.
     * @param array<int, array<string, mixed>> $layout Elementor layout elements hierarchy.
     *
     * @throws ToolException
     */
    public function createPageLayout(int $pageId, array $layout): bool
    {
        $this->requireElementor();

        // Validate post exists.
        $post = get_post($pageId);
        if ( ! ($post instanceof \WP_Post) ) {
            throw ToolException::notFound(self::TOOL_NAME, 'Page', $pageId);
        }

        // Elementor requires JSON-encoded data stored in _elementor_data meta.
        $json = wp_json_encode($layout);

        update_post_meta($pageId, '_elementor_data', $json);
        update_post_meta($pageId, '_elementor_edit_mode', 'builder');
        update_post_meta($pageId, '_elementor_template_type', 'wp-page');

        // Clean elementor cache for this page.
        if ( class_exists('\Elementor\Plugin') ) {
            \Elementor\Plugin::$instance->documents->get($pageId)?->save([]);
        }

        do_action('wpa_elementor_page_layout_created', $pageId, $layout);

        return true;
    }

    /**
     * Inserts a widget into an existing container.
     *
     * @throws ToolException
     */
    public function insertWidget(int $pageId, string $containerId, array $widgetData): array
    {
        $this->requireElementor();

        $rawMeta = get_post_meta($pageId, '_elementor_data', true);
        $layout  = empty($rawMeta) ? [] : json_decode($rawMeta, true);

        if ( ! is_array($layout) ) {
            $layout = [];
        }

        $widgetInserted = false;

        // Recursive walker to find target container and insert widget.
        $walker = function (array &$elements) use ($containerId, $widgetData, &$widgetInserted, &$walker): void {
            foreach ( $elements as &$element ) {
                if ( isset($element['id']) && $element['id'] === $containerId ) {
                    $element['elements'][] = $widgetData;
                    $widgetInserted = true;
                    return;
                }

                if ( ! empty($element['elements']) && is_array($element['elements']) ) {
                    $walker($element['elements']);
                }
            }
        };

        $walker($layout);

        if ( ! $widgetInserted ) {
            // Fallback: If container not found, append a new section and insert the widget there.
            $newContainer = [
                'id'       => $containerId,
                'elType'   => 'container',
                'settings' => [],
                'elements' => [$widgetData],
            ];
            $layout[] = $newContainer;
        }

        $this->createPageLayout($pageId, $layout);

        return $layout;
    }

    /**
     * Sets global colors in the active Elementor Kit.
     *
     * @param array<string, string> $colors Key-value map of custom colors (e.g. ['primary' => '#ff0000']).
     *
     * @throws ToolException
     */
    public function setGlobalColors(array $colors): bool
    {
        $this->requireElementor();

        $kitId = $this->getActiveKitId();
        if ( ! $kitId ) {
            throw new ToolException('Active Elementor Kit not found.', self::TOOL_NAME);
        }

        $settings = get_post_meta($kitId, '_elementor_page_settings', true);
        if ( ! is_array($settings) ) {
            $settings = [];
        }

        $currentColors = $settings['system_colors'] ?? [];

        foreach ( $colors as $key => $hex ) {
            $hex = sanitize_hex_color($hex);
            if ( ! $hex ) {
                continue;
            }

            // Elementor maps standard keys (primary, secondary, text, accent) under system_colors indices.
            $found = false;
            foreach ( $currentColors as &$currentColor ) {
                if ( ($currentColor['_id'] ?? '') === $key ) {
                    $currentColor['color'] = $hex;
                    $found = true;
                    break;
                }
            }

            if ( ! $found ) {
                $currentColors[] = [
                    '_id'   => $key,
                    'title' => ucfirst($key),
                    'color' => $hex,
                ];
            }
        }

        $settings['system_colors'] = $currentColors;
        update_post_meta($kitId, '_elementor_page_settings', $settings);

        // Regenerate global CSS files.
        if ( class_exists('\Elementor\Plugin') ) {
            \Elementor\Plugin::$instance->kits_manager->clear_cache();
        }

        return true;
    }

    /**
     * Sets global typography/fonts.
     *
     * @throws ToolException
     */
    public function setGlobalFonts(array $fonts): bool
    {
        $this->requireElementor();

        $kitId = $this->getActiveKitId();
        if ( ! $kitId ) {
            throw new ToolException('Active Elementor Kit not found.', self::TOOL_NAME);
        }

        $settings = get_post_meta($kitId, '_elementor_page_settings', true);
        if ( ! is_array($settings) ) {
            $settings = [];
        }

        $currentTypography = $settings['system_typography'] ?? [];

        foreach ( $fonts as $key => $config ) {
            $found = false;
            foreach ( $currentTypography as &$item ) {
                if ( ($item['_id'] ?? '') === $key ) {
                    $item['typography_font_family'] = sanitize_text_field($config['family'] ?? '');
                    if ( isset($config['weight']) ) {
                        $item['typography_font_weight'] = sanitize_text_field($config['weight']);
                    }
                    $found = true;
                    break;
                }
            }

            if ( ! $found ) {
                $currentTypography[] = [
                    '_id'                    => $key,
                    'title'                  => ucfirst($key),
                    'typography_font_family' => sanitize_text_field($config['family'] ?? ''),
                    'typography_font_weight' => sanitize_text_field($config['weight'] ?? 'normal'),
                ];
            }
        }

        $settings['system_typography'] = $currentTypography;
        update_post_meta($kitId, '_elementor_page_settings', $settings);

        if ( class_exists('\Elementor\Plugin') ) {
            \Elementor\Plugin::$instance->kits_manager->clear_cache();
        }

        return true;
    }

    /**
     * Resolves the active Kit ID from elementor library.
     */
    private function getActiveKitId(): int
    {
        $kitId = (int) get_option('elementor_active_kit');

        if ( $kitId > 0 ) {
            return $kitId;
        }

        // Fallback search.
        $kits = get_posts([
            'post_type'   => 'elementor_library',
            'meta_key'    => '_elementor_template_type',
            'meta_value'  => 'kit',
            'post_status' => 'publish',
            'numberposts' => 1,
            'fields'      => 'ids',
        ]);

        return ! empty($kits) ? (int) $kits[0] : 0;
    }
}
