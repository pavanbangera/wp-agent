<?php

declare(strict_types=1);

namespace WpAgent\REST;

/**
 * Health and capability status endpoint.
 *
 * Route: GET /wp-json/wp-agent/v1/status
 *
 * Returns human-readable server status including:
 * - Plugin version and PHP version
 * - Registered tool count
 * - Authentication drivers
 * - MCP protocol version
 * - WordPress information
 *
 * This endpoint is publicly accessible but returns limited info
 * to unauthenticated callers.
 *
 * @package WpAgent\REST
 * @since   0.1.0
 */
final class StatusEndpoint
{
    public const NAMESPACE = 'wp-agent/v1';
    public const ROUTE     = '/status';

    public function __construct(
        private readonly \WpAgent\MCP\Registry\ToolRegistry $tools,
        private readonly \WpAgent\Auth\AuthManager           $auth,
        private readonly \WpAgent\Core\Config                $config,
    ) {}

    public function register(): void
    {
        register_rest_route(
            self::NAMESPACE,
            self::ROUTE,
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [ $this, 'handle' ],
                'permission_callback' => '__return_true',
            ]
        );
    }

    public function handle(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wp_version;

        $isAuth = is_user_logged_in();

        $status = [
            'status'  => 'ok',
            'plugin'  => [
                'name'    => 'WP Agent',
                'version' => WPA_VERSION,
                'tagline' => 'The Open Source AI Agent for WordPress',
            ],
            'mcp'     => [
                'protocol_version' => \WpAgent\MCP\Server\McpServer::PROTOCOL_VERSION,
                'endpoint'         => rest_url('wp-agent/v1/mcp'),
                'sse_endpoint'     => rest_url('wp-agent/v1/sse'),
            ],
            'server'  => [
                'php_version' => PHP_VERSION,
                'wp_version'  => $wp_version,
                'site_url'    => get_bloginfo('url'),
            ],
        ];

        // Only reveal tool details to authenticated callers.
        if ( $isAuth ) {
            $status['tools'] = [
                'count'   => $this->tools->count(),
                'drivers' => $this->auth->getDriverNames(),
            ];
        }

        return new \WP_REST_Response($status, 200);
    }
}
