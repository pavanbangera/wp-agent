<?php

declare(strict_types=1);

namespace WpAgent\MCP\Protocol;

use WpAgent\Exceptions\McpException;

/**
 * JSON-RPC 2.0 request/response value objects and factory.
 *
 * Strictly implements the JSON-RPC 2.0 specification.
 *
 * @package WpAgent\MCP\Protocol
 * @since   0.1.0
 * @see     https://www.jsonrpc.org/specification
 */
final class JsonRpc
{
    public const VERSION = '2.0';

    // -------------------------------------------------------------------------
    // Request parsing
    // -------------------------------------------------------------------------

    /**
     * Parses raw JSON body into a normalized request array.
     *
     * @param string $body Raw JSON string from the request body.
     *
     * @return array{jsonrpc: string, method: string, params: array<string, mixed>, id: string|int|null}
     *
     * @throws McpException On parse error or invalid request.
     */
    public static function parseRequest(string $body): array
    {
        if ( empty($body) ) {
            throw new McpException('Empty request body.', McpException::PARSE_ERROR);
        }

        $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

        if ( ! is_array($decoded) ) {
            throw new McpException('Request body must be a JSON object.', McpException::PARSE_ERROR);
        }

        return self::validateRequest($decoded);
    }

    /**
     * Validates a decoded JSON-RPC request array.
     *
     * @param array<string, mixed> $data
     *
     * @return array{jsonrpc: string, method: string, params: array<string, mixed>, id: string|int|null}
     *
     * @throws McpException
     */
    public static function validateRequest(array $data): array
    {
        if ( ( $data['jsonrpc'] ?? '' ) !== self::VERSION ) {
            throw new McpException(
                'Request must include "jsonrpc": "2.0".',
                McpException::INVALID_REQUEST
            );
        }

        if ( ! isset($data['method']) || ! is_string($data['method']) || $data['method'] === '' ) {
            throw new McpException(
                'Request must include a non-empty "method" string.',
                McpException::INVALID_REQUEST
            );
        }

        $params = $data['params'] ?? [];
        if ( ! is_array($params) ) {
            throw new McpException(
                '"params" must be an array or object.',
                McpException::INVALID_PARAMS
            );
        }

        return [
            'jsonrpc' => self::VERSION,
            'method'  => $data['method'],
            'params'  => $params,
            'id'      => $data['id'] ?? null,  // null = notification.
        ];
    }

    // -------------------------------------------------------------------------
    // Response builders
    // -------------------------------------------------------------------------

    /**
     * Builds a success response.
     *
     * @param mixed            $result The result data.
     * @param string|int|null  $id     The request ID.
     *
     * @return array{jsonrpc: string, result: mixed, id: string|int|null}
     */
    public static function success(mixed $result, string|int|null $id): array
    {
        return [
            'jsonrpc' => self::VERSION,
            'result'  => $result,
            'id'      => $id,
        ];
    }

    /**
     * Builds an error response.
     *
     * @param int              $code    JSON-RPC error code.
     * @param string           $message Human-readable error message.
     * @param mixed            $data    Optional error data.
     * @param string|int|null  $id      The request ID.
     *
     * @return array{jsonrpc: string, error: array{code: int, message: string, data?: mixed}, id: string|int|null}
     */
    public static function error(
        int $code,
        string $message,
        mixed $data = null,
        string|int|null $id = null,
    ): array {
        $errorObj = [
            'code'    => $code,
            'message' => $message,
        ];

        if ( null !== $data ) {
            $errorObj['data'] = $data;
        }

        return [
            'jsonrpc' => self::VERSION,
            'error'   => $errorObj,
            'id'      => $id,
        ];
    }

    /**
     * Builds an error response from an McpException.
     *
     * @param string|int|null $id Request ID.
     *
     * @return array{jsonrpc: string, error: array{code: int, message: string, data?: mixed}, id: string|int|null}
     */
    public static function fromException(McpException $e, string|int|null $id = null): array
    {
        return self::error(
            $e->getCode(),
            $e->getMessage(),
            empty($e->getContext()) ? null : $e->getContext(),
            $id,
        );
    }

    /**
     * Serializes a response array to JSON.
     *
     * @param array<string, mixed> $response
     */
    public static function encode(array $response): string
    {
        return json_encode($response, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
