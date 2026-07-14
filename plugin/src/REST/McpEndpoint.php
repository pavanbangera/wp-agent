<?php

declare(strict_types=1);

namespace WpAgent\REST;

use WpAgent\MCP\Protocol\JsonRpc;
use WpAgent\MCP\Server\McpServer;

/**
 * WordPress REST API endpoint for MCP JSON-RPC requests.
 *
 * Route: POST /wp-json/wp-agent/v1/mcp
 *
 * This endpoint handles all stateless MCP interactions. For persistent
 * SSE connections, use the SseEndpoint alongside this endpoint.
 *
 * Authentication is handled inside McpServer (not at the WP REST layer)
 * so we can return proper JSON-RPC error responses rather than WP_Error.
 *
 * @package WpAgent\REST
 * @since   0.1.0
 */
final class McpEndpoint
{
    public const NAMESPACE = 'wp-agent/v1';
    public const ROUTE     = '/mcp';

    public function __construct(private readonly McpServer $server) {}

    /**
     * Registers the REST route with WordPress.
     */
    public function register(): void
    {
        register_rest_route(
            self::NAMESPACE,
            self::ROUTE,
            [
                [
                    'methods'             => \WP_REST_Server::CREATABLE, // POST
                    'callback'            => [ $this, 'handle' ],
                    'permission_callback' => '__return_true', // Auth handled by McpServer.
                    'args'                => [
                        // Intentionally empty — we parse the raw body.
                    ],
                ],
                [
                    'methods'             => \WP_REST_Server::READABLE, // OPTIONS (CORS)
                    'callback'            => [ $this, 'handleOptions' ],
                    'permission_callback' => '__return_true',
                ],
            ]
        );
    }

    /**
     * Handles an incoming MCP POST request.
     */
    public function handle(\WP_REST_Request $request): \WP_REST_Response
    {
        $body   = $request->get_body();
        $result = $this->server->handle($body, $request);

        $response = new \WP_REST_Response($result, 200);
        $response->header('Content-Type', 'application/json');
        $response->header('X-WP-Agent-Version', WPA_VERSION);

        // Add CORS headers.
        $response->header('Access-Control-Allow-Origin', '*');
        $response->header('Access-Control-Allow-Methods', 'POST, OPTIONS');
        $response->header('Access-Control-Allow-Headers', 'Authorization, Content-Type, X-Session-Id');

        return $response;
    }

    /**
     * Handles CORS preflight requests.
     */
    public function handleOptions(\WP_REST_Request $request): \WP_REST_Response
    {
        $response = new \WP_REST_Response(null, 204);
        $response->header('Access-Control-Allow-Origin', '*');
        $response->header('Access-Control-Allow-Methods', 'POST, OPTIONS');
        $response->header('Access-Control-Allow-Headers', 'Authorization, Content-Type, X-Session-Id');
        $response->header('Access-Control-Max-Age', '86400');

        return $response;
    }
}
