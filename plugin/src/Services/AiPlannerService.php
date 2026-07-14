<?php

declare(strict_types=1);

namespace WpAgent\Services;

use WpAgent\Exceptions\ToolException;

/**
 * AI multi-step goal planner and codebase scaffolder service.
 *
 * Suggests tool steps pipelines and writes compliant templates on disk.
 *
 * @package WpAgent\Services
 * @since   0.1.0
 */
final class AiPlannerService
{
    private const TOOL_NAME = 'ai_planner_service';

    /**
     * Maps user goal statements to recommended tool execution plans.
     *
     * @return array<string, mixed>
     */
    public function planGoal(string $goal): array
    {
        $goal = strtolower($goal);
        $steps = [];

        if ( str_contains($goal, 'shop') || str_contains($goal, 'store') || str_contains($goal, 'woocommerce') ) {
            $steps = [
                [
                    'step'        => 1,
                    'tool'        => 'wordpress.plugins.install',
                    'arguments'   => ['slug' => 'woocommerce'],
                    'explanation' => 'Install WooCommerce plugin from WordPress.org catalog.',
                ],
                [
                    'step'        => 2,
                    'tool'        => 'wordpress.plugins.activate',
                    'arguments'   => ['slug' => 'woocommerce/woocommerce.php'],
                    'explanation' => 'Activate WooCommerce store engine.',
                ],
                [
                    'step'        => 3,
                    'tool'        => 'wordpress.woo.product.create',
                    'arguments'   => ['name' => 'Featured Product', 'regular_price' => 19.99, 'status' => 'publish'],
                    'explanation' => 'Create a starter simple catalog product.',
                ],
            ];
        } elseif ( str_contains($goal, 'landing') || str_contains($goal, 'page') ) {
            $steps = [
                [
                    'step'        => 1,
                    'tool'        => 'wordpress.pages.create',
                    'arguments'   => ['title' => 'Home', 'status' => 'publish'],
                    'explanation' => 'Create a base static homepage post.',
                ],
                [
                    'step'        => 2,
                    'tool'        => 'wordpress.menus.create',
                    'arguments'   => ['name' => 'Main Navigation Menu'],
                    'explanation' => 'Create a navigation structure taxonomy.',
                ],
            ];
        } else {
            // General planning fallback sequence.
            $steps = [
                [
                    'step'        => 1,
                    'tool'        => 'wordpress.site.info.get',
                    'arguments'   => [],
                    'explanation' => 'Inspect active theme, plugins, and site configurations overview.',
                ],
            ];
        }

        return [
            'goal'             => $goal,
            'steps_count'      => count($steps),
            'recommended_plan' => $steps,
        ];
    }

    /**
     * Scaffolds a new plugin header file on local disk.
     *
     * @throws ToolException
     */
    public function scaffoldPlugin(string $slug, array $meta): bool
    {
        $slug      = sanitize_key($slug);
        $pluginDir = WP_PLUGIN_DIR . '/' . $slug;

        if ( file_exists($pluginDir) ) {
            throw new ToolException("Plugin folder '{$slug}' already exists.", self::TOOL_NAME);
        }

        if ( ! mkdir($pluginDir, 0755, true) && ! is_dir($pluginDir) ) {
            throw new ToolException("Failed to create plugin directory at {$pluginDir}.", self::TOOL_NAME);
        }

        $title       = sanitize_text_field($meta['title'] ?? ucfirst($slug));
        $desc        = sanitize_text_field($meta['description'] ?? 'Scaffolded plugin.');
        $version     = sanitize_text_field($meta['version'] ?? '1.0.0');
        $author      = sanitize_text_field($meta['author'] ?? 'WP Agent AI');
        $textDomain  = sanitize_key($meta['text_domain'] ?? $slug);

        $content = <<<PHP
<?php
/**
 * Plugin Name: {$title}
 * Description: {$desc}
 * Version:     {$version}
 * Author:      {$author}
 * Text Domain: {$textDomain}
 * License:     GPLv2 or later
 */

defined('ABSPATH') || exit;

// Register activation hook
register_activation_hook(__FILE__, function() {
    add_option('{$slug}_activated_at', time());
});
PHP;

        if ( false === file_put_contents("{$pluginDir}/{$slug}.php", $content) ) {
            throw new ToolException('Failed to write scaffolded plugin index script file.', self::TOOL_NAME);
        }

        return true;
    }

    /**
     * Scaffolds a basic custom classic theme on disk.
     *
     * @throws ToolException
     */
    public function scaffoldTheme(string $slug, array $meta): bool
    {
        $slug     = sanitize_key($slug);
        $themeDir = get_theme_root() . '/' . $slug;

        if ( file_exists($themeDir) ) {
            throw new ToolException("Theme folder '{$slug}' already exists.", self::TOOL_NAME);
        }

        if ( ! mkdir($themeDir, 0755, true) && ! is_dir($themeDir) ) {
            throw new ToolException("Failed to create theme directory at {$themeDir}.", self::TOOL_NAME);
        }

        $title   = sanitize_text_field($meta['title'] ?? ucfirst($slug));
        $author  = sanitize_text_field($meta['author'] ?? 'WP Agent AI');
        $desc    = sanitize_text_field($meta['description'] ?? 'Scaffolded theme.');

        $style = <<<CSS
/*
Theme Name:  {$title}
Author:      {$author}
Description: {$desc}
Version:     1.0.0
*/
CSS;

        $index = <<<PHP
<?php
get_header();
if (have_posts()) :
    while (have_posts()) : the_post();
        the_title('<h1>', '</h1>');
        the_content();
    endwhile;
endif;
get_footer();
PHP;

        if ( false === file_put_contents("{$themeDir}/style.css", $style) || false === file_put_contents("{$themeDir}/index.php", $index) ) {
            throw new ToolException('Failed to write scaffolded theme file structures.', self::TOOL_NAME);
        }

        return true;
    }
}
