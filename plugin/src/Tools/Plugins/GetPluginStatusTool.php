<?php

declare(strict_types=1);

namespace WpAgent\Tools\Plugins;

use WpAgent\Auth\Identity;
use WpAgent\MCP\Protocol\ToolResult;
use WpAgent\Tools\AbstractTool;

/**
 * Tool: wordpress.plugin.get_status
 *
 * Returns the installed/active/inactive state of a plugin by its slug or file path.
 * Eliminates the need for raw PHP calls like is_plugin_active() or get_plugin_data()
 * for plugin verification during automated setup workflows.
 *
 * Required scope: wp-agent:read
 * Required capability: activate_plugins
 *
 * @package WpAgent\Tools\Plugins
 * @since   0.1.0
 */
final class GetPluginStatusTool extends AbstractTool
{
    public function getName(): string
    {
        return 'wordpress.plugin.get_status';
    }

    public function getDescription(): string
    {
        return 'Returns the status of a WordPress plugin by its slug or plugin file path. '
            . 'The slug format is "plugin-folder/plugin-file.php" (e.g. "elementor/elementor.php"). '
            . 'You can also pass just the folder slug (e.g. "elementor") and the tool will auto-detect '
            . 'the main plugin file. Returns installed, active, network_active, name, version, and author.';
    }

    public function getInputSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'slug' => [
                    'type'        => 'string',
                    'description' => 'Plugin slug (e.g. "elementor/elementor.php") or just folder name (e.g. "elementor").',
                    'minLength'   => 1,
                    'maxLength'   => 255,
                ],
            ],
            'required'             => ['slug'],
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
        $this->requireCapability('activate_plugins', $identity);

        if ( ! function_exists('get_plugins') ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $inputSlug    = $args['slug'];
        $pluginFile   = $this->resolvePluginFile($inputSlug);

        if ( null === $pluginFile ) {
            return ToolResult::json([
                'slug'           => $inputSlug,
                'installed'      => false,
                'active'         => false,
                'network_active' => false,
                'message'        => "Plugin '{$inputSlug}' is not installed.",
            ]);
        }

        $allPlugins    = get_plugins();
        $pluginData    = $allPlugins[$pluginFile] ?? [];
        $isActive      = is_plugin_active($pluginFile);
        $isNetActive   = is_plugin_active_for_network($pluginFile);

        return ToolResult::json([
            'slug'           => $inputSlug,
            'plugin_file'    => $pluginFile,
            'installed'      => true,
            'active'         => $isActive,
            'network_active' => $isNetActive,
            'name'           => $pluginData['Name']        ?? '',
            'version'        => $pluginData['Version']     ?? '',
            'author'         => $pluginData['Author']      ?? '',
            'author_uri'     => $pluginData['AuthorURI']   ?? '',
            'plugin_uri'     => $pluginData['PluginURI']   ?? '',
            'description'    => $pluginData['Description'] ?? '',
            'requires_wp'    => $pluginData['RequiresWP']  ?? '',
            'requires_php'   => $pluginData['RequiresPHP'] ?? '',
        ]);
    }

    /**
     * Resolves the canonical plugin file path (e.g. "elementor/elementor.php") from a slug.
     *
     * Handles three input formats:
     *  - Full path: "elementor/elementor.php"        → verified directly
     *  - Folder only: "elementor"                    → guesses "elementor/elementor.php"
     *  - Any variant: scans all installed plugins for a folder match
     *
     * @return string|null Plugin file key, or null if not found.
     */
    private function resolvePluginFile(string $slug): ?string
    {
        $allPlugins = get_plugins();

        // 1. Exact match (e.g. "elementor/elementor.php").
        if ( isset($allPlugins[$slug]) ) {
            return $slug;
        }

        // 2. Folder-only slug: guess "slug/slug.php".
        $guessed = "{$slug}/{$slug}.php";
        if ( isset($allPlugins[$guessed]) ) {
            return $guessed;
        }

        // 3. Scan all plugins for any whose folder matches the slug.
        foreach ( array_keys($allPlugins) as $pluginFile ) {
            $folder = dirname($pluginFile);
            if ( $folder === $slug ) {
                return $pluginFile;
            }
        }

        return null;
    }
}
