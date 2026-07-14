<?php

declare(strict_types=1);

namespace WpAgent\Exceptions;

/**
 * Thrown when MCP protocol handling fails.
 *
 * Carries a JSON-RPC error code for protocol-level responses.
 *
 * @package WpAgent\Exceptions
 * @since   0.1.0
 */
final class McpException extends WpAgentException
{
    // JSON-RPC 2.0 defined error codes.
    public const PARSE_ERROR      = -32700;
    public const INVALID_REQUEST  = -32600;
    public const METHOD_NOT_FOUND = -32601;
    public const INVALID_PARAMS   = -32602;
    public const INTERNAL_ERROR   = -32603;

    // MCP-defined error codes (-32000 to -32099 reserved).
    public const TOOL_NOT_FOUND       = -32001;
    public const TOOL_EXECUTION_ERROR = -32002;
    public const UNAUTHORIZED         = -32003;
    public const RATE_LIMITED         = -32004;
    public const VALIDATION_FAILED    = -32005;
    public const RESOURCE_NOT_FOUND   = -32006;

    /**
     * @param string               $message  Error message.
     * @param int                  $code     JSON-RPC error code.
     * @param array<string, mixed> $data     Additional error data.
     * @param \Throwable|null      $previous Previous exception.
     */
    public function __construct(
        string $message,
        int $code = self::INTERNAL_ERROR,
        array $data = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $data, $code, $previous);
    }

    /**
     * Converts to JSON-RPC error object.
     *
     * @return array{code: int, message: string, data?: array<string, mixed>}
     */
    public function toJsonRpcError(): array
    {
        $error = [
            'code'    => $this->getCode(),
            'message' => $this->getMessage(),
        ];

        if ( ! empty( $this->getContext() ) ) {
            $error['data'] = $this->getContext();
        }

        return $error;
    }

    /**
     * Factory: tool not found.
     */
    public static function toolNotFound(string $toolName): self
    {
        return new self(
            sprintf( 'Tool "%s" not found in registry.', $toolName ),
            self::TOOL_NOT_FOUND,
            ['tool' => $toolName],
        );
    }

    /**
     * Factory: method not found.
     */
    public static function methodNotFound(string $method): self
    {
        return new self(
            sprintf( 'Method "%s" is not supported.', $method ),
            self::METHOD_NOT_FOUND,
            ['method' => $method],
        );
    }

    /**
     * Factory: invalid params.
     *
     * @param array<string, mixed> $errors Validation errors.
     */
    public static function invalidParams(array $errors): self
    {
        return new self(
            'Invalid parameters provided.',
            self::INVALID_PARAMS,
            ['errors' => $errors],
        );
    }

    /**
     * Factory: unauthorized.
     */
    public static function unauthorized(string $reason = 'Authentication required.'): self
    {
        return new self( $reason, self::UNAUTHORIZED );
    }

    /**
     * Factory: rate limited.
     */
    public static function rateLimited(int $retryAfter): self
    {
        return new self(
            'Too many requests. Please retry after the specified interval.',
            self::RATE_LIMITED,
            ['retry_after' => $retryAfter],
        );
    }
}
