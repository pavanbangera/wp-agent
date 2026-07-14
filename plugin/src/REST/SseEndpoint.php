<?php

declare(strict_types=1);

namespace WpAgent\REST;

use WpAgent\Auth\AuthManager;
use WpAgent\Core\Config;
use WpAgent\MCP\Protocol\JsonRpc;
use WpAgent\MCP\Server\McpServer;
use WpAgent\MCP\Transport\SseTransport;

/**
 * WordPress REST endpoint for MCP Server-Sent Events stream.
 *
 * Route: GET  /wp-json/wp-agent/v1/sse         (open stream)
 * Route: POST /wp-json/wp-agent/v1/sse/messages (send message)
 *
 * Flow:
 * 1. AI client GETs /sse — receives `endpoint` event with POST URL
 * 2. Client POSTs JSON-RPC messages to /sse/messages
 * 3. Server sends responses via the open SSE stream
 *
 * @package WpAgent\REST
 * @since   0.1.0
 */
final class SseEndpoint
{
    public const NAMESPACE        = 'wp-agent/v1';
    public const SSE_ROUTE        = '/sse';
    public const MESSAGES_ROUTE   = '/sse/messages';

    public function __construct(
        private readonly McpServer    $server,
        private readonly SseTransport $transport,
        private readonly AuthManager  $auth,
        private readonly Config       $config,
    ) {}

    /**
     * Registers both SSE REST routes.
     */
    public function register(): void
    {
        // GET /sse — Opens the SSE stream.
        register_rest_route(
            self::NAMESPACE,
            self::SSE_ROUTE,
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [ $this, 'openStream' ],
                'permission_callback' => '__return_true',
            ]
        );

        // POST /sse/messages — Accepts JSON-RPC messages.
        register_rest_route(
            self::NAMESPACE,
            self::MESSAGES_ROUTE,
            [
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => [ $this, 'handleMessage' ],
                'permission_callback' => '__return_true',
            ]
        );
    }

    /**
     * Opens the SSE stream.
     *
     * This callback intentionally keeps execution alive and streams
     * events until the client disconnects or a timeout is reached.
     */
    public function openStream(\WP_REST_Request $request): void
    {
        // Authenticate before opening the stream.
        try {
            $identity = $this->auth->authenticate($request);
        } catch ( \Throwable $e ) {
            // Can't use WP_REST_Response here — stream not yet open.
            http_response_code(401);
            header('Content-Type: application/json');
            echo JsonRpc::encode([
                'error' => 'Unauthorized: ' . $e->getMessage(),
            ]);
            return;
        }

        // Build the messages URL for the endpoint event.
        $messagesUrl = rest_url(self::NAMESPACE . self::MESSAGES_ROUTE);
        $messagesUrl = add_query_arg('session', $identity->getSessionId(), $messagesUrl);

        $this->transport->open($messagesUrl);

        // Heartbeat loop — keep the connection alive.
        $heartbeatInterval = $this->config->int('mcp.sse_heartbeat', 30);
        $maxDuration       = 300; // 5 minutes max stream per request.
        $started           = time();

        while ( ! connection_aborted() && (time() - $started) < $maxDuration ) {
            $this->transport->heartbeat();
            sleep($heartbeatInterval);
        }

        $this->transport->close();
        exit; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    /**
     * Handles an incoming JSON-RPC message on the SSE channel.
     */
    public function handleMessage(\WP_REST_Request $request): \WP_REST_Response
    {
        $body   = $request->get_body();
        $result = $this->server->handle($body, $request);

        return new \WP_REST_Response($result, 200);
    }
}
