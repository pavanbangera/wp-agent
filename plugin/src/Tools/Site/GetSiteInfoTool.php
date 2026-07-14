<?php

declare(strict_types=1);

namespace WpAgent\Tools\Site;

use WpAgent\Auth\Identity;
use WpAgent\MCP\Protocol\ToolResult;
use WpAgent\Tools\AbstractTool;

/**
 * Tool: wordpress.site.info
 *
 * Returns comprehensive information about the WordPress installation.
 * This is typically the first tool called by an AI agent to understand
 * the site it is working with.
 *
 * Required scope: wp-agent:read
 *
 * @package WpAgent\Tools\Site
 * @since   0.1.0
 */
final class GetSiteInfoTool extends AbstractTool
{
    public function getName(): string
    {
        return 'wordpress.site.info';
    }

    public function getDescription(): string
    {
        return 'Returns comprehensive information about the WordPress site including '
            . 'name, URL, version, active theme, installed plugins, and site settings. '
            . 'Use this as the first tool to understand the site context before taking actions.';
    }

    public function getInputSchema(): array
    {
        return [
            'type'                 => 'object',
            'properties'           => [
                'include' => [
                    'type'        => 'array',
                    'description' => 'Specific sections to include. If omitted, all sections are returned.',
                    'items'       => [
                        'type' => 'string',
                        'enum' => ['basic', 'theme', 'plugins', 'settings', 'health'],
                    ],
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
        return [
            'readOnlyHint'   => true,
            'destructiveHint' => false,
            'idempotentHint' => true,
        ];
    }

    protected function handle(array $args, Identity $identity): ToolResult
    {
        global $wp_version;

        $include = $args['include'] ?? ['basic', 'theme', 'plugins', 'settings'];
        $include = is_array($include) ? $include : ['basic', 'theme', 'plugins', 'settings'];

        $info = [];

        if ( in_array('basic', $include, true) ) {
            $info['basic'] = [
                'site_name'    => get_bloginfo('name'),
                'tagline'      => get_bloginfo('description'),
                'url'          => get_bloginfo('url'),
                'admin_email'  => get_bloginfo('admin_email'),
                'language'     => get_bloginfo('language'),
                'charset'      => get_bloginfo('charset'),
                'wp_version'   => $wp_version,
                'php_version'  => PHP_VERSION,
                'is_multisite' => is_multisite(),
                'timezone'     => wp_timezone_string(),
                'date_format'  => get_option('date_format'),
                'time_format'  => get_option('time_format'),
                'permalink'    => get_option('permalink_structure'),
            ];
        }

        if ( in_array('theme', $include, true) ) {
            $theme = wp_get_theme();
            $info['theme'] = [
                'name'        => $theme->get('Name'),
                'version'     => $theme->get('Version'),
                'author'      => $theme->get('Author'),
                'template'    => $theme->get_template(),
                'is_child'    => $theme->parent() !== false,
                'parent'      => $theme->parent() ? $theme->parent()->get('Name') : null,
                'supports'    => array_keys(get_theme_support_all()),
            ];
        }

        if ( in_array('plugins', $include, true) ) {
            if ( ! function_exists('get_plugins') ) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }

            $allPlugins    = get_plugins();
            $activePlugins = (array) get_option('active_plugins', []);

            $plugins = [];
            foreach ( $allPlugins as $pluginFile => $pluginData ) {
                $plugins[] = [
                    'file'    => $pluginFile,
                    'name'    => $pluginData['Name'],
                    'version' => $pluginData['Version'],
                    'author'  => $pluginData['Author'],
                    'active'  => in_array($pluginFile, $activePlugins, true),
                ];
            }

            $info['plugins'] = [
                'total'  => count($plugins),
                'active' => count($activePlugins),
                'list'   => $plugins,
            ];
        }

        if ( in_array('settings', $include, true) ) {
            $info['settings'] = [
                'reading' => [
                    'posts_per_page'   => (int) get_option('posts_per_page'),
                    'show_on_front'    => get_option('show_on_front'),
                    'page_on_front'    => (int) get_option('page_on_front'),
                    'page_for_posts'   => (int) get_option('page_for_posts'),
                ],
                'writing' => [
                    'default_category' => (int) get_option('default_category'),
                    'default_post_format' => get_option('default_post_format'),
                ],
                'discussion' => [
                    'comments_open'    => (bool) get_option('default_comment_status') === 'open',
                    'moderation'       => (bool) get_option('comment_moderation'),
                ],
            ];
        }

        return ToolResult::json($info);
    }
}

/**
 * Helper: returns all registered theme supports.
 *
 * @return array<string, mixed>
 */
function get_theme_support_all(): array
{
    global $_wp_theme_features;
    return is_array($_wp_theme_features) ? $_wp_theme_features : [];
}
