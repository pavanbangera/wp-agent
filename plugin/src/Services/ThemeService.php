<?php

declare(strict_types=1);

namespace WpAgent\Services;

use WpAgent\Core\Upgrader\SilentUpgraderSkin;
use WpAgent\Exceptions\ToolException;

/**
 * Theme business logic service.
 *
 * Implements theme search, installation, activation, and child-theme generation.
 *
 * @package WpAgent\Services
 * @since   0.1.0
 */
final class ThemeService
{
    private const TOOL_NAME = 'theme_service';

    /**
     * Lists all installed themes.
     *
     * @return array<string, array<string, mixed>> Keyed by stylesheet/directory name.
     */
    public function list(): array
    {
        $themes = wp_get_themes();
        $activeStylesheet = get_stylesheet();

        $list = [];
        foreach ( $themes as $stylesheet => $theme ) {
            $list[$stylesheet] = [
                'stylesheet'  => $stylesheet,
                'name'        => $theme->get('Name'),
                'version'     => $theme->get('Version'),
                'author'      => $theme->get('Author'),
                'description' => strip_tags($theme->get('Description')),
                'active'      => $stylesheet === $activeStylesheet,
                'parent'      => $theme->parent() ? $theme->parent()->get_stylesheet() : null,
            ];
        }

        return $list;
    }

    /**
     * Searches themes in the WordPress.org directory.
     *
     * @throws ToolException
     */
    public function search(string $term, int $page = 1, int $perPage = 20): array
    {
        require_once ABSPATH . 'wp-admin/includes/theme-install.php';

        $api = themes_api(
            'query_themes',
            [
                'page'     => $page,
                'per_page' => $perPage,
                'search'   => $term,
                'fields'   => [
                    'description'     => true,
                    'rating'          => true,
                    'num_ratings'     => true,
                    'downloaded'      => true,
                    'active_installs' => true,
                ],
            ]
        );

        if ( is_wp_error($api) ) {
            throw ToolException::fromWpError(self::TOOL_NAME, $api);
        }

        $results = [];
        if ( ! empty($api->results) && is_array($api->results) ) {
            foreach ( $api->results as $theme ) {
                // $theme is typically an object inside results.
                $results[] = [
                    'name'            => $theme->name ?? '',
                    'slug'            => $theme->slug ?? '',
                    'version'         => $theme->version ?? '',
                    'author'          => strip_tags($theme->author ?? ''),
                    'description'     => strip_tags($theme->description ?? ''),
                    'rating'          => $theme->rating ?? 0,
                    'num_ratings'     => $theme->num_ratings ?? 0,
                    'downloaded'      => $theme->downloaded ?? 0,
                    'active_installs' => $theme->active_installs ?? 0,
                ];
            }
        }

        return [
            'themes' => $results,
            'info'   => [
                'page'    => $api->info['page'] ?? 1,
                'pages'   => $api->info['pages'] ?? 1,
                'results' => $api->info['results'] ?? 0,
            ],
        ];
    }

    /**
     * Installs a theme using the WordPress.org repository slug.
     *
     * @throws ToolException
     */
    public function install(string $slug): bool
    {
        require_once ABSPATH . 'wp-admin/includes/theme-install.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

        // Check if theme is already installed.
        $theme = wp_get_theme($slug);
        if ( $theme->exists() ) {
            return true;
        }

        $api = themes_api('theme_information', ['slug' => $slug]);

        if ( is_wp_error($api) ) {
            throw ToolException::fromWpError(self::TOOL_NAME, $api);
        }

        if ( empty($api->download_link) ) {
            throw new ToolException("Theme '{$slug}' download link is unavailable.", self::TOOL_NAME);
        }

        $skin     = new SilentUpgraderSkin();
        $upgrader = new \Theme_Upgrader($skin);
        $result   = $upgrader->install($api->download_link);

        if ( is_wp_error($result) ) {
            throw ToolException::fromWpError(self::TOOL_NAME, $result);
        }

        if ( ! $result ) {
            $errorMsg = ! empty($skin->errors) ? implode(' ', $skin->errors) : 'Theme installation failed.';
            throw new ToolException($errorMsg, self::TOOL_NAME);
        }

        return true;
    }

    /**
     * Activates an installed theme.
     *
     * @throws ToolException
     */
    public function activate(string $stylesheet): bool
    {
        $theme = wp_get_theme($stylesheet);

        if ( ! $theme->exists() ) {
            throw new ToolException("Theme '{$stylesheet}' is not installed.", self::TOOL_NAME, ToolException::RESOURCE_NOT_FOUND);
        }

        switch_theme($stylesheet);

        return true;
    }

    /**
     * Creates a fully-functional child theme on disk for a parent theme.
     *
     * @param string $parentSlug Styleheet name of the parent (e.g. "twentytwentyfour").
     * @param string $childSlug  Desired folder name for the child.
     * @param string $name       User-friendly name for the child theme.
     *
     * @return array<string, string> Created child theme details.
     *
     * @throws ToolException
     */
    public function createChildTheme(string $parentSlug, string $childSlug, string $name): array
    {
        $parent = wp_get_theme($parentSlug);

        if ( ! $parent->exists() ) {
            throw new ToolException("Parent theme '{$parentSlug}' is not installed.", self::TOOL_NAME, ToolException::RESOURCE_NOT_FOUND);
        }

        $childSlug = sanitize_title($childSlug);
        $themesDir = get_theme_root();
        $childDir  = $themesDir . '/' . $childSlug;

        if ( file_exists($childDir) ) {
            throw new ToolException("Child theme directory '{$childSlug}' already exists.", self::TOOL_NAME);
        }

        // Create directory.
        if ( ! @mkdir($childDir, 0755, true) ) {
            throw new ToolException("Failed to create directory '{$childDir}'. Check permissions.", self::TOOL_NAME);
        }

        // Generate style.css header.
        // phpcs:disable Generic.Files.LineLength
        $styleContent = "/*\n" .
            "Theme Name:   {$name}\n" .
            "Template:     {$parentSlug}\n" .
            "Author:       WP Agent\n" .
            "Version:      1.0.0\n" .
            "Text Domain:  {$childSlug}\n" .
            "*/\n";
        // phpcs:enable

        if ( false === @file_put_contents($childDir . '/style.css', $styleContent) ) {
            throw new ToolException("Failed to write style.css.", self::TOOL_NAME);
        }

        // Generate functions.php to enqueue styles.
        $functionsContent = "<?php\n" .
            "// Enqueue parent styles\n" .
            "add_action( 'wp_enqueue_scripts', 'wpa_enqueue_child_theme_styles' );\n" .
            "function wpa_enqueue_child_theme_styles() {\n" .
            "    wp_enqueue_style( 'parent-style', get_template_directory_uri() . '/style.css' );\n" .
            "}\n";

        if ( false === @file_put_contents($childDir . '/functions.php', $functionsContent) ) {
            throw new ToolException("Failed to write functions.php.", self::TOOL_NAME);
        }

        do_action('wpa_child_theme_created', $childSlug, $parentSlug);

        return [
            'stylesheet' => $childSlug,
            'name'       => $name,
            'parent'     => $parentSlug,
            'path'       => $childDir,
        ];
    }
}
