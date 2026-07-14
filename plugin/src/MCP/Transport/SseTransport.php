<?php

declare(strict_types=1);

namespace WpAgent\MCP\Transport;

use WpAgent\Core\Config;
use WpAgent\Logger\LogManager;

/**
 * Server-Sent Events (SSE) transport for MCP.
 *
 * Implements the MCP SSE transport specification, which allows
 * AI clients like Cursor, Claude Code, and Windsurf to maintain
 * a persistent connection to the MCP server.
 *
 * Connection flow:
 * 1. Client connects to GET /wp-json/wp-agent/v1/sse
 * 2. Server sends the `endpoint` event with the POST URL
 * 3. Client sends JSON-RPC messages to the POST URL
 * 4. Server pushes responses via the SSE stream
 *
 * @package WpAgent\MCP\Transport
 * @since   0.1.0
 * @see     https://spec.modelcontextprotocol.io/specification/basic/transports/#http-with-sse
 */
final class SseTransport
{
    private bool $streaming = false;

    public function __construct(
        private readonly Config    $config,
        private readonly LogManager $logger,
    ) {}

    /**
     * Opens an SSE stream and sends the initial endpoint event.
     *
     * This method sets appropriate headers and echoes the SSE preamble.
     * It should be called from the GET /sse REST endpoint handler.
     *
     * @param string $messagesUrl The URL where the client should POST messages.
     */
    public function open(string $messagesUrl): void
    {
        $this->setHeaders();
        $this->streaming = true;

        // Per MCP SSE spec: send the endpoint event first.
        $this->sendEvent('endpoint', $messagesUrl);

        $this->logger->debug('SSE stream opened', ['endpoint' => $messagesUrl]);
    }

    /**
     * Sends a JSON-RPC response to the connected client via SSE.
     *
     * @param array<string, mixed> $response JSON-RPC response array.
     */
    public function send(array $response): void
    {
        if ( ! $this->streaming ) {
            return;
        }

        $data = json_encode($response, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $this->sendEvent('message', $data);
    }

    /**
     * Sends an MCP notification event.
     *
     * @param string               $method Notification method (e.g. 'notifications/tools/list_changed').
     * @param array<string, mixed> $params Notification params.
     */
    public function sendNotification(string $method, array $params = []): void
    {
        if ( ! $this->streaming ) {
            return;
        }

        $notification = [
            'jsonrpc' => '2.0',
            'method'  => $method,
            'params'  => $params,
        ];

        $this->send($notification);
    }

    /**
     * Sends a heartbeat ping to keep the connection alive.
     */
    public function heartbeat(): void
    {
        if ( ! $this->streaming ) {
            return;
        }

        // SSE comment lines keep the connection alive without triggering event handlers.
        echo ": heartbeat\n\n";
        $this->flush();
    }

    /**
     * Closes the SSE stream gracefully.
     */
    public function close(): void
    {
        if ( $this->streaming ) {
            $this->sendEvent('close', '{}');
            $this->streaming = false;
        }
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function setHeaders(): void
    {
        // Disable output buffering.
        while ( ob_get_level() > 0 ) {
            ob_end_clean();
        }

        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no'); // Disable nginx buffering.
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Headers: Authorization, Content-Type, X-Session-Id');

        // Remove any buffering at the PHP level.
        if ( function_exists('set_time_limit') ) {
            set_time_limit(0);
        }
    }

    /**
     * Emits a single SSE event.
     *
     * @param string $event Event name.
     * @param string $data  Event data payload.
     */
    private function sendEvent(string $event, string $data): void
    {
        // Validate event name to prevent header injection.
        if ( ! preg_match('/^[\w\-\/]+$/', $event) ) {
            return;
        }

        echo "event: {$event}\n";

        // Data must be split on newlines per the SSE spec.
        foreach ( explode("\n", $data) as $line ) {
            echo "data: {$line}\n";
        }

        echo "\n";
        $this->flush();
    }

    private function flush(): void
    {
        if ( function_exists('ob_flush') ) {
            @ob_flush(); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
        }
        flush();
    }
}
