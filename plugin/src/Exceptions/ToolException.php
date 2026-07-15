<?php

declare(strict_types=1);

namespace WpAgent\Exceptions;

/**
 * Thrown when a tool fails during execution (not validation).
 *
 * @package WpAgent\Exceptions
 * @since   0.1.0
 */
final class ToolException extends WpAgentException
{
    public const WORDPRESS_ERROR       = 2001;
    public const PERMISSION_DENIED     = 2002;
    public const RESOURCE_NOT_FOUND    = 2003;
    public const EXTERNAL_API_ERROR    = 2004;
    public const OPERATION_FAILED      = 2005;
    public const INVALID_PARAMS        = 2006;
    public const FILE_WRITE_FAILED     = 2010;
    public const FILE_READ_FAILED      = 2011;
    public const FILE_PATH_TRAVERSAL   = 2012;
    public const ELEMENTOR_CACHE_STALE = 2020;
    public const CDN_PURGE_FAILED      = 2021;
    public const REWRITE_FLUSH_FAILED  = 2022;

    /**
     * @param string               $message  Error message.
     * @param string               $toolName The tool that failed.
     * @param int                  $code     Error code.
     * @param array<string, mixed> $context  Additional context.
     * @param \Throwable|null      $previous Previous exception.
     */
    public function __construct(
        string $message,
        private readonly string $toolName,
        int $code = self::OPERATION_FAILED,
        array $context = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            $message,
            array_merge(['tool' => $toolName], $context),
            $code,
            $previous,
        );
    }

    public function getToolName(): string
    {
        return $this->toolName;
    }

    /**
     * Factory: wrap a WP_Error object.
     */
    public static function fromWpError(string $toolName, \WP_Error $error): self
    {
        return new self(
            $error->get_error_message(),
            $toolName,
            self::WORDPRESS_ERROR,
            ['wp_error_code' => $error->get_error_code()],
        );
    }

    /**
     * Factory: permission denied for a WP capability.
     */
    public static function permissionDenied(string $toolName, string $capability): self
    {
        return new self(
            sprintf( 'Permission denied. Capability "%s" is required.', $capability ),
            $toolName,
            self::PERMISSION_DENIED,
            ['capability' => $capability],
        );
    }

    /**
     * Factory: resource not found.
     */
    public static function notFound(string $toolName, string $resourceType, int|string $id): self
    {
        return new self(
            sprintf( '%s with ID "%s" was not found.', $resourceType, $id ),
            $toolName,
            self::RESOURCE_NOT_FOUND,
            ['resource_type' => $resourceType, 'id' => $id],
        );
    }
}
