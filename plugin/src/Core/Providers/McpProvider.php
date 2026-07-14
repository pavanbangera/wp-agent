<?php

declare(strict_types=1);

namespace WpAgent\Core\Providers;

use WpAgent\Core\ServiceProvider;
use WpAgent\Logger\Handlers\DatabaseHandler;
use WpAgent\Logger\LogManager;
use WpAgent\MCP\Protocol\McpCapabilities;
use WpAgent\MCP\Registry\ToolRegistry;
use WpAgent\MCP\Server\McpServer;
use WpAgent\MCP\Transport\SseTransport;

/**
 * Registers MCP server, transport, and registry services.
 *
 * @package WpAgent\Core\Providers
 * @since   0.1.0
 */
final class McpProvider extends ServiceProvider
{
    public function register(): void
    {
        // Logger.
        $this->container->singleton(LogManager::class, function ($c): LogManager {
            $config  = $c->get(\WpAgent\Core\Config::class);
            $manager = new LogManager($config->string('logging.level', 'info'));

            if ( in_array('database', $config->array('logging.drivers', ['database']), true) ) {
                $manager->addHandler(new DatabaseHandler());
            }

            return $manager;
        });

        // Tool registry.
        $this->container->singleton(
            ToolRegistry::class,
            fn ($c) => new ToolRegistry()
        );

        // SSE transport.
        $this->container->singleton(
            SseTransport::class,
            fn ($c) => new SseTransport(
                $c->get(\WpAgent\Core\Config::class),
                $c->get(LogManager::class),
            )
        );

        // MCP server.
        $this->container->singleton(
            McpServer::class,
            fn ($c) => new McpServer(
                $c->get(ToolRegistry::class),
                $c->get(\WpAgent\Auth\AuthManager::class),
                $c->get(\WpAgent\Core\Config::class),
                $c->get(LogManager::class),
            )
        );
    }

    public function boot(): void
    {
        // Allow third-party plugins to register tools via action hook.
        $registry = $this->container->get(ToolRegistry::class);

        /**
         * Fires when the tool registry is ready for registrations.
         *
         * Third-party plugins should hook here to register custom tools.
         *
         * @param ToolRegistry $registry The tool registry.
         *
         * @since 0.1.0
         */
        do_action('wpa_register_tools', $registry);
    }
}
