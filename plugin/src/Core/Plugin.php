<?php

declare(strict_types=1);

namespace WpAgent\Core;

use WpAgent\Core\Providers\AuthProvider;
use WpAgent\Core\Providers\McpProvider;
use WpAgent\Core\Providers\RouterProvider;
use WpAgent\Core\Providers\ToolProvider;
use WpAgent\Database\Migrator;

/**
 * Main plugin orchestrator.
 *
 * Bootstraps the DI container, registers service providers,
 * and manages the plugin lifecycle (activate / deactivate / uninstall).
 *
 * @package WpAgent\Core
 * @since   0.1.0
 */
final class Plugin
{
    private static ?self $instance = null;

    private readonly Container $container;
    private readonly Config    $config;
    private readonly HookLoader $hooks;

    /** @var class-string<ServiceProvider>[] */
    private array $providers = [
        AuthProvider::class,
        ToolProvider::class,
        McpProvider::class,
        RouterProvider::class,
    ];

    private function __construct()
    {
        $this->container = new Container();
        $this->config    = new Config();
        $this->hooks     = new HookLoader();

        // Register core instances so providers can depend on them.
        $this->container->instance(Container::class, $this->container);
        $this->container->instance(Config::class, $this->config);
        $this->container->instance(HookLoader::class, $this->hooks);
    }

    /**
     * Returns the singleton plugin instance.
     */
    public static function getInstance(): self
    {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Boots the plugin — runs once on `plugins_loaded`.
     */
    public function boot(): void
    {
        if ( ! $this->meetsRequirements() ) {
            return;
        }

        // Allow external packages to register providers before core boot.
        do_action('wpa_register_providers', $this);

        $this->registerProviders();

        $this->container->boot();

        $this->bootProviders();

        $this->hooks->run();

        if ( is_admin() ) {
            $dashboard = new \WpAgent\Core\Admin\AdminDashboard($this->container->get(\WpAgent\MCP\Registry\ToolRegistry::class));
            $dashboard->registerHooks();
        }

        do_action('wpa_booted', $this->container);
    }

    /**
     * Allows third-party plugins to add service providers.
     *
     * @param class-string<ServiceProvider> $providerClass
     */
    public function addProvider(string $providerClass): void
    {
        $this->providers[] = $providerClass;
    }

    public function getContainer(): Container
    {
        return $this->container;
    }

    public function getConfig(): Config
    {
        return $this->config;
    }

    // -------------------------------------------------------------------------
    // Lifecycle hooks
    // -------------------------------------------------------------------------

    /**
     * Runs on plugin activation.
     *
     * Creates database tables, sets default options, schedules cron.
     */
    public static function activate(): void
    {
        if ( ! current_user_can('activate_plugins') ) {
            return;
        }

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $migrator = new Migrator();
        $migrator->run();

        // Set activation flag for redirect to welcome screen.
        update_option('wpa_activation_redirect', true);
        update_option('wpa_version', WPA_VERSION);

        flush_rewrite_rules();
    }

    /**
     * Runs on plugin deactivation.
     */
    public static function deactivate(): void
    {
        wp_clear_scheduled_hook('wpa_cleanup_logs');
        wp_clear_scheduled_hook('wpa_cleanup_sessions');
        flush_rewrite_rules();
    }

    /**
     * Runs on plugin deletion (static — no instance available).
     */
    public static function uninstall(): void
    {
        if ( ! defined('WP_UNINSTALL_PLUGIN') ) {
            return;
        }

        // Only delete data if the user opted in.
        if ( ! get_option('wpa_delete_data_on_uninstall', false) ) {
            return;
        }

        global $wpdb;

        // phpcs:disable WordPress.DB.DirectDatabaseQuery
        $tables = [
            $wpdb->prefix . 'wpa_execution_log',
            $wpdb->prefix . 'wpa_workflows',
            $wpdb->prefix . 'wpa_sessions',
            $wpdb->prefix . 'wpa_rate_limits',
            $wpdb->prefix . 'wpa_backups',
        ];

        foreach ( $tables as $table ) {
            $wpdb->query( "DROP TABLE IF EXISTS `{$table}`" ); // phpcs:ignore
        }
        // phpcs:enable

        delete_option('wpa_settings');
        delete_option('wpa_version');
        delete_option('wpa_auth_keys');
        delete_option('wpa_tool_permissions');
        delete_option('wpa_connected_clients');
        delete_option('wpa_activation_redirect');
        delete_option('wpa_delete_data_on_uninstall');
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function registerProviders(): void
    {
        foreach ( $this->providers as $providerClass ) {
            $provider = new $providerClass($this->container, $this->hooks, $this->config);
            $provider->register();

            // Store provider instance for boot phase.
            $this->container->instance($providerClass, $provider);
        }
    }

    private function bootProviders(): void
    {
        foreach ( $this->providers as $providerClass ) {
            /** @var ServiceProvider $provider */
            $provider = $this->container->get($providerClass);
            $provider->boot();
        }
    }

    private function meetsRequirements(): bool
    {
        global $wp_version;

        if ( version_compare($wp_version, WPA_MIN_WP_VERSION, '<') ) {
            add_action('admin_notices', static function (): void {
                $msg = sprintf(
                    /* translators: %s: Required WP version */
                    esc_html__('WP Agent requires WordPress %s or higher.', 'wp-agent'),
                    WPA_MIN_WP_VERSION
                );
                echo '<div class="notice notice-error"><p>' . esc_html($msg) . '</p></div>';
            });
            return false;
        }

        return true;
    }
}
