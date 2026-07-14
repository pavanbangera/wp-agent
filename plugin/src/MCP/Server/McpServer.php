<?php

declare(strict_types=1);

namespace WpAgent\MCP\Server;

use WpAgent\Auth\AuthManager;
use WpAgent\Auth\Identity;
use WpAgent\Core\Config;
use WpAgent\Exceptions\AuthException;
use WpAgent\Exceptions\McpException;
use WpAgent\Exceptions\ToolException;
use WpAgent\Exceptions\ValidationException;
use WpAgent\Logger\LogManager;
use WpAgent\MCP\Protocol\JsonRpc;
use WpAgent\MCP\Protocol\McpCapabilities;
use WpAgent\MCP\Protocol\ToolResult;
use WpAgent\MCP\Registry\ToolRegistry;

/**
 * Core MCP Server.
 *
 * Implements the Model Context Protocol server-side message handling.
 * Routes JSON-RPC 2.0 requests to the appropriate method handler,
 * enforces authentication, and returns spec-compliant responses.
 *
 * Supported methods:
 * - initialize
 * - notifications/initialized
 * - ping
 * - tools/list
 * - tools/call
 * - logging/setLevel
 *
 * @package WpAgent\MCP\Server
 * @since   0.1.0
 * @see     https://spec.modelcontextprotocol.io/specification/
 */
final class McpServer
{
    /** MCP protocol version this server implements. */
    public const PROTOCOL_VERSION = '2024-11-05';

    /**
     * Methods that do NOT require authentication.
     *
     * @var string[]
     */
    private const PUBLIC_METHODS = ['initialize', 'ping'];

    public function __construct(
        private readonly ToolRegistry   $tools,
        private readonly AuthManager    $auth,
        private readonly Config         $config,
        private readonly LogManager     $logger,
    ) {}

    // -------------------------------------------------------------------------
    // Entry point
    // -------------------------------------------------------------------------

    /**
     * Handles a raw JSON-RPC request body.
     *
     * @param string           $body    Raw JSON request body.
     * @param \WP_REST_Request $request The WordPress REST request.
     *
     * @return array<string, mixed> JSON-RPC response array (ready for json_encode).
     */
    public function handle(string $body, \WP_REST_Request $request): array
    {
        $requestId = null;

        try {
            $rpc       = JsonRpc::parseRequest($body);
            $requestId = $rpc['id'];
            $method    = $rpc['method'];
            $params    = $rpc['params'];

            // Authenticate (skip for public methods).
            $identity = null;
            if ( ! in_array($method, self::PUBLIC_METHODS, true) ) {
                $identity = $this->auth->authenticate($request);
            }

            $result = $this->dispatch($method, $params, $identity, $request);

            return JsonRpc::success($result, $requestId);

        } catch ( McpException $e ) {
            $this->logger->warning('MCP error', ['code' => $e->getCode(), 'message' => $e->getMessage()]);
            return JsonRpc::fromException($e, $requestId);

        } catch ( AuthException $e ) {
            $this->logger->warning('Auth failure', ['message' => $e->getMessage()]);
            return JsonRpc::error(McpException::UNAUTHORIZED, $e->getMessage(), null, $requestId);

        } catch ( ValidationException $e ) {
            return JsonRpc::error(
                McpException::INVALID_PARAMS,
                'Validation failed.',
                $e->getErrors(),
                $requestId,
            );

        } catch ( ToolException $e ) {
            return JsonRpc::error(
                McpException::TOOL_EXECUTION_ERROR,
                $e->getMessage(),
                $e->getContext(),
                $requestId,
            );

        } catch ( \JsonException $e ) {
            return JsonRpc::error(McpException::PARSE_ERROR, 'JSON parse error: ' . $e->getMessage(), null, $requestId);

        } catch ( \Throwable $e ) {
            $this->logger->error('Unexpected MCP error', ['exception' => $e->getMessage()]);
            return JsonRpc::error(
                McpException::INTERNAL_ERROR,
                defined('WP_DEBUG') && WP_DEBUG
                    ? $e->getMessage()
                    : 'An internal server error occurred.',
                null,
                $requestId,
            );
        }
    }

    // -------------------------------------------------------------------------
    // Method dispatcher
    // -------------------------------------------------------------------------

    /**
     * Routes a method to its handler.
     *
     * @param string               $method   JSON-RPC method name.
     * @param array<string, mixed> $params   Method parameters.
     * @param Identity|null        $identity Authenticated identity (null for public methods).
     * @param \WP_REST_Request     $request  Original WordPress request.
     *
     * @return mixed Handler result.
     *
     * @throws McpException For unknown methods.
     */
    private function dispatch(
        string $method,
        array $params,
        ?Identity $identity,
        \WP_REST_Request $request,
    ): mixed {
        return match ($method) {
            'initialize'               => $this->handleInitialize($params, $request),
            'notifications/initialized' => $this->handleInitialized(),
            'ping'                     => $this->handlePing(),
            'tools/list'               => $this->handleToolsList($params, $identity),
            'tools/call'               => $this->handleToolsCall($params, $identity),
            'logging/setLevel'         => $this->handleLoggingSetLevel($params, $identity),
            default                    => throw McpException::methodNotFound($method),
        };
    }

    // -------------------------------------------------------------------------
    // Method handlers
    // -------------------------------------------------------------------------

    /**
     * Handles `initialize` — MCP handshake.
     *
     * @param array<string, mixed> $params
     * @param \WP_REST_Request     $request
     *
     * @return array<string, mixed>
     */
    private function handleInitialize(array $params, \WP_REST_Request $request): array
    {
        $clientVersion = $params['protocolVersion'] ?? '';

        $this->logger->info('MCP initialize', [
            'client_protocol' => $clientVersion,
            'client_info'     => $params['clientInfo'] ?? [],
        ]);

        $capabilities = new McpCapabilities();

        return [
            'protocolVersion' => self::PROTOCOL_VERSION,
            'capabilities'    => $capabilities->toArray(),
            'serverInfo'      => [
                'name'    => $this->config->string('mcp.server_name', 'wp-agent'),
                'version' => $this->config->string('mcp.server_version', WPA_VERSION),
            ],
            'instructions'    => $this->getInstructions(),
        ];
    }

    /**
     * Handles `notifications/initialized` — client acknowledgement.
     */
    private function handleInitialized(): array
    {
        // This is a notification; we return an empty result.
        return [];
    }

    /**
     * Handles `ping` — keepalive.
     *
     * @return array<string, mixed>
     */
    private function handlePing(): array
    {
        return [];
    }

    /**
     * Handles `tools/list` — returns the tool manifest.
     *
     * @param array<string, mixed> $params
     * @param Identity|null        $identity
     *
     * @return array<string, mixed>
     */
    private function handleToolsList(array $params, ?Identity $identity): array
    {
        // Optional cursor-based pagination.
        // For v0.1, we return all tools without pagination.
        $manifest = $this->tools->toManifest();

        $this->logger->debug('tools/list', ['count' => count($manifest['tools'])]);

        return $manifest;
    }

    /**
     * Handles `tools/call` — executes a tool.
     *
     * @param array<string, mixed> $params
     * @param Identity|null        $identity
     *
     * @return array<string, mixed>
     *
     * @throws McpException     On tool not found or execution error.
     * @throws AuthException    On insufficient scopes.
     * @throws ValidationException On invalid arguments.
     */
    private function handleToolsCall(array $params, ?Identity $identity): array
    {
        if ( null === $identity ) {
            throw McpException::unauthorized();
        }

        $toolName  = $params['name']      ?? '';
        $arguments = $params['arguments'] ?? [];

        if ( ! is_string($toolName) || empty($toolName) ) {
            throw McpException::invalidParams(['name' => ['Tool name is required.']]);
        }

        if ( ! is_array($arguments) ) {
            throw McpException::invalidParams(['arguments' => ['Arguments must be an object.']]);
        }

        // Resolve the tool.
        $tool = $this->tools->resolve($toolName);

        // Authorize.
        $requiredScopes = $tool->getRequiredScopes();
        if ( ! empty($requiredScopes) ) {
            $this->auth->authorize($identity, $requiredScopes);
        }

        // Log execution start.
        $startTime = microtime(true);
        $this->logger->info('tool.call.start', [
            'tool'    => $toolName,
            'user_id' => $identity->getUserId(),
        ]);

        // Execute.
        $result = $tool->execute($arguments, $identity);

        $duration = (int) round((microtime(true) - $startTime) * 1000);

        $this->logger->info('tool.call.end', [
            'tool'        => $toolName,
            'duration_ms' => $duration,
            'is_error'    => $result->isError(),
        ]);

        /**
         * Fires after a tool has been called.
         *
         * @param ToolResult $result   Tool result.
         * @param string     $toolName Tool name.
         * @param Identity   $identity Authenticated caller.
         * @param int        $duration Execution duration in ms.
         *
         * @since 0.1.0
         */
        do_action('wpa_tool_called', $result, $toolName, $identity, $duration);

        return $result->toArray();
    }

    /**
     * Handles `logging/setLevel` — adjusts server log level.
     *
     * @param array<string, mixed> $params
     * @param Identity|null        $identity
     *
     * @return array<string, mixed>
     */
    private function handleLoggingSetLevel(array $params, ?Identity $identity): array
    {
        $level = sanitize_text_field((string) ( $params['level'] ?? 'info' ));
        $allowed = ['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency'];

        if ( ! in_array($level, $allowed, true) ) {
            throw McpException::invalidParams(['level' => ["Invalid log level '{$level}'."]]);
        }

        $this->logger->setLevel($level);

        return [];
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function getInstructions(): string
    {
        return 'WP Agent — The Open Source AI Agent for WordPress. '
            . 'Use tools/list to discover available capabilities. '
            . 'All tools follow the "wordpress.entity.action" naming convention. '
            . 'Authenticate via WordPress Application Passwords (default) or JWT Bearer tokens.';
    }
}
