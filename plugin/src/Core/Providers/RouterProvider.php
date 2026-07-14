<?php

declare(strict_types=1);

namespace WpAgent\Core\Providers;

use WpAgent\Auth\AuthManager;
use WpAgent\Core\Config;
use WpAgent\Core\ServiceProvider;
use WpAgent\MCP\Registry\ToolRegistry;
use WpAgent\MCP\Server\McpServer;
use WpAgent\MCP\Transport\SseTransport;
use WpAgent\REST\McpEndpoint;
use WpAgent\REST\SseEndpoint;
use WpAgent\REST\StatusEndpoint;

/**
 * Registers WordPress REST API routes for WP Agent.
 *
 * @package WpAgent\Core\Providers
 * @since   0.1.0
 */
final class RouterProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->container->singleton(
            McpEndpoint::class,
            fn ($c) => new McpEndpoint($c->get(McpServer::class))
        );

        $this->container->singleton(
            SseEndpoint::class,
            fn ($c) => new SseEndpoint(
                $c->get(McpServer::class),
                $c->get(SseTransport::class),
                $c->get(AuthManager::class),
                $c->get(Config::class),
            )
        );

        $this->container->singleton(
            StatusEndpoint::class,
            fn ($c) => new StatusEndpoint(
                $c->get(ToolRegistry::class),
                $c->get(AuthManager::class),
                $c->get(Config::class),
            )
        );
    }

    public function boot(): void
    {
        // Register routes on the WordPress rest_api_init hook.
        $this->hooks->addAction('rest_api_init', function (): void {
            $this->container->get(McpEndpoint::class)->register();
            $this->container->get(SseEndpoint::class)->register();
            $this->container->get(StatusEndpoint::class)->register();
        });
    }
}
