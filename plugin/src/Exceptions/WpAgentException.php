<?php

declare(strict_types=1);

namespace WpAgent\Exceptions;

/**
 * Base exception for all WP Agent errors.
 *
 * @package WpAgent\Exceptions
 * @since   0.1.0
 */
class WpAgentException extends \RuntimeException
{
    /**
     * Creates a new exception with a context payload.
     *
     * @param string          $message  Human-readable error message.
     * @param array<string, mixed> $context  Additional diagnostic data.
     * @param int             $code     Error code.
     * @param \Throwable|null $previous Previous exception for chaining.
     */
    public function __construct(
        string $message,
        private readonly array $context = [],
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Returns the structured context array for logging/serialization.
     *
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return $this->context;
    }
}
