<?php

declare(strict_types=1);

namespace WpAgent\Services;

use WpAgent\Core\Upgrader\SilentUpgraderSkin;
use WpAgent\Exceptions\ToolException;

/**
 * Plugin business logic service.
 *
 * Implements search, list, install, activate, deactivate, delete, and rollback
 * capabilities utilizing WordPress Core upgrader and administration APIs.
 *
 * @package WpAgent\Services
 * @since   0.1.0
 */
final class PluginService
{
    private const TOOL_NAME = 'plugin_service';

    /**
     * Lists all installed plugins on the site.
     *
     * @return array<string, array<string, mixed>> Keyed by plugin file.
     */
    public function list(): array
    {
        if ( ! function_exists('get_plugins') ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $allPlugins    = get_plugins();
        $activePlugins = (array) get_option('active_plugins', []);
        $updates       = get_site_transient('update_plugins');

        $list = [];
        foreach ( $allPlugins as $file => $data ) {
            $hasUpdate = isset($updates->response[$file]);

            $list[$file] = [
                'file'            => $file,
                'name'            => $data['Name'],
                'version'         => $data['Version'],
                'author'          => $data['Author'],
                'description'     => strip_tags($data['Description']),
                'active'          => in_array($file, $activePlugins, true),
                'plugin_uri'      => $data['PluginURI'] ?? '',
                'update_available'=> $hasUpdate,
                'latest_version'  => $hasUpdate ? $updates->response[$file]->new_version : $data['Version'],
            ];
        }

        return $list;
    }

    /**
     * Searches the WordPress.org plugin directory.
     *
     * @param string $term    Search query.
     * @param int    $page    Page number.
     * @param int    $perPage Items per page.
     *
     * @return array<string, mixed> List of matching plugins.
     *
     * @throws ToolException
     */
    public function search(string $term, int $page = 1, int $perPage = 20): array
    {
        require_once ABSPATH . 'wp-admin/includes/plugin-install.php';

        $api = plugins_api(
            'query_plugins',
            [
                'page'     => $page,
                'per_page' => $perPage,
                'search'   => $term,
                'fields'   => [
                    'short_description' => true,
                    'version'           => true,
                    'author'            => true,
                    'rating'            => true,
                    'num_ratings'       => true,
                    'downloaded'        => true,
                    'active_installs'   => true,
                ],
            ]
        );

        if ( is_wp_error($api) ) {
            throw ToolException::fromWpError(self::TOOL_NAME, $api);
        }

        $results = [];
        if ( ! empty($api->plugins) && is_array($api->plugins) ) {
            foreach ( $api->plugins as $plugin ) {
                $results[] = [
                    'name'              => $plugin['name'] ?? '',
                    'slug'              => $plugin['slug'] ?? '',
                    'version'           => $plugin['version'] ?? '',
                    'author'            => strip_tags($plugin['author'] ?? ''),
                    'short_description' => strip_tags($plugin['short_description'] ?? ''),
                    'rating'            => $plugin['rating'] ?? 0,
                    'num_ratings'       => $plugin['num_ratings'] ?? 0,
                    'downloaded'        => $plugin['downloaded'] ?? 0,
                    'active_installs'   => $plugin['active_installs'] ?? 0,
                ];
            }
        }

        return [
            'plugins' => $results,
            'info'    => [
                'page'  => $api->info['page'] ?? 1,
                'pages' => $api->info['pages'] ?? 1,
                'results' => $api->info['results'] ?? 0,
            ],
        ];
    }

    /**
     * Installs a plugin using the WordPress.org repository slug.
     *
     * @throws ToolException
     */
    public function install(string $slug): string
    {
        require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

        // Check if plugin is already installed.
        $installed = $this->findPluginFileBySlug($slug);
        if ( null !== $installed ) {
            return $installed;
        }

        $api = plugins_api('plugin_information', ['slug' => $slug]);

        if ( is_wp_error($api) ) {
            throw ToolException::fromWpError(self::TOOL_NAME, $api);
        }

        if ( empty($api->download_link) ) {
            throw new ToolException("Plugin '{$slug}' does not have a download link available.", self::TOOL_NAME);
        }

        $skin      = new SilentUpgraderSkin();
        $upgrader  = new \Plugin_Upgrader($skin);
        $installed = $upgrader->install($api->download_link);

        if ( is_wp_error($installed) ) {
            throw ToolException::fromWpError(self::TOOL_NAME, $installed);
        }

        if ( ! $installed ) {
            $errorMsg = ! empty($skin->errors) ? implode(' ', $skin->errors) : 'Installation failed silently.';
            throw new ToolException($errorMsg, self::TOOL_NAME);
        }

        // Return the freshly resolved main plugin file.
        $pluginFile = $this->findPluginFileBySlug($slug);
        if ( null === $pluginFile ) {
            throw new ToolException('Failed to find plugin main file post-installation.', self::TOOL_NAME);
        }

        return $pluginFile;
    }

    /**
     * Activates an installed plugin.
     *
     * @throws ToolException
     */
    public function activate(string $pluginFile): bool
    {
        if ( ! function_exists('activate_plugin') ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $result = activate_plugin($pluginFile);

        if ( is_wp_error($result) ) {
            throw ToolException::fromWpError(self::TOOL_NAME, $result);
        }

        return true;
    }

    /**
     * Deactivates an active plugin.
     */
    public function deactivate(string $pluginFile): bool
    {
        if ( ! function_exists('deactivate_plugins') ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        deactivate_plugins($pluginFile);

        return true;
    }

    /**
     * Deletes a deactivated plugin.
     *
     * @throws ToolException
     */
    public function delete(string $pluginFile): bool
    {
        if ( ! function_exists('delete_plugins') ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        $installed = $this->list();
        if ( ! isset($installed[$pluginFile]) ) {
            throw new ToolException("Plugin '{$pluginFile}' is not installed.", self::TOOL_NAME, ToolException::RESOURCE_NOT_FOUND);
        }

        if ( $installed[$pluginFile]['active'] ) {
            throw new ToolException("Cannot delete active plugin '{$pluginFile}'. Deactivate it first.", self::TOOL_NAME);
        }

        $result = delete_plugins([$pluginFile]);

        if ( is_wp_error($result) ) {
            throw ToolException::fromWpError(self::TOOL_NAME, $result);
        }

        if ( false === $result ) {
            throw new ToolException("Failed to delete plugin '{$pluginFile}'. Check folder permissions.", self::TOOL_NAME);
        }

        return true;
    }

    /**
     * Updates an installed plugin.
     *
     * @throws ToolException
     */
    public function update(string $pluginFile): bool
    {
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

        $skin     = new SilentUpgraderSkin();
        $upgrader = new \Plugin_Upgrader($skin);
        $result   = $upgrader->upgrade($pluginFile);

        if ( is_wp_error($result) ) {
            throw ToolException::fromWpError(self::TOOL_NAME, $result);
        }

        if ( ! $result ) {
            $errorMsg = ! empty($skin->errors) ? implode(' ', $skin->errors) : 'Update failed silently.';
            throw new ToolException($errorMsg, self::TOOL_NAME);
        }

        return true;
    }

    /**
     * Rolls back or forces a plugin installation to a specific version.
     *
     * Constructs the ZIP download URL from WordPress.org using the slug and version.
     *
     * @throws ToolException
     */
    public function rollback(string $slug, string $version): string
    {
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

        // Check format of version.
        if ( ! preg_match('/^[a-zA-Z0-9\.\-_]+$/', $version) ) {
            throw new ToolException("Invalid version parameter '{$version}'.", self::TOOL_NAME, ToolException::INVALID_PARAMS);
        }

        // Deactivate old version if active.
        $pluginFile = $this->findPluginFileBySlug($slug);
        $isActive   = false;

        if ( null !== $pluginFile ) {
            $installed = $this->list();
            if ( isset($installed[$pluginFile]) && $installed[$pluginFile]['active'] ) {
                $isActive = true;
                $this->deactivate($pluginFile);
            }
        }

        // Construct WP.org older version download URL.
        $downloadUrl = sprintf('https://downloads.wordpress.org/plugin/%s.%s.zip', $slug, $version);

        // Perform download/install. Force override requires folder delete first in standard WP.
        if ( null !== $pluginFile ) {
            $this->delete($pluginFile);
        }

        $skin     = new SilentUpgraderSkin();
        $upgrader = new \Plugin_Upgrader($skin);
        $result   = $upgrader->install($downloadUrl);

        if ( is_wp_error($result) ) {
            throw ToolException::fromWpError(self::TOOL_NAME, $result);
        }

        if ( ! $result ) {
            $errorMsg = ! empty($skin->errors) ? implode(' ', $skin->errors) : 'Rollback installation failed.';
            throw new ToolException($errorMsg, self::TOOL_NAME);
        }

        $newFile = $this->findPluginFileBySlug($slug);
        if ( null === $newFile ) {
            throw new ToolException('Rolled-back plugin main file not found.', self::TOOL_NAME);
        }

        // Reactivate if it was active before.
        if ( $isActive ) {
            $this->activate($newFile);
        }

        return $newFile;
    }

    // -------------------------------------------------------------------------
    // Private Helpers
    // -------------------------------------------------------------------------

    /**
     * Resolves the main plugin filename by its directory slug.
     */
    private function findPluginFileBySlug(string $slug): ?string
    {
        if ( ! function_exists('get_plugins') ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $plugins = get_plugins();

        foreach ( array_keys($plugins) as $file ) {
            if ( dirname($file) === $slug || $file === $slug ) {
                return $file;
            }
        }

        return null;
    }
}
