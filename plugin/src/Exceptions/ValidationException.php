<?php

declare(strict_types=1);

namespace WpAgent\Exceptions;

/**
 * Thrown when tool input validation fails against JSON Schema.
 *
 * @package WpAgent\Exceptions
 * @since   0.1.0
 */
final class ValidationException extends WpAgentException
{
    /**
     * @param array<string, string[]> $errors Field → error messages map.
     */
    public function __construct(
        private readonly array $errors,
        string $message = 'Validation failed.',
    ) {
        parent::__construct($message, $errors, 0);
    }

    /**
     * Returns field-level validation errors.
     *
     * @return array<string, string[]>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Factory: create from a flat list of error messages.
     *
     * @param string[] $messages
     */
    public static function fromMessages(array $messages): self
    {
        return new self( ['_' => $messages] );
    }

    /**
     * Factory: missing required field.
     */
    public static function missingRequired(string $field): self
    {
        return new self( [$field => ["Field '{$field}' is required."]] );
    }
}
