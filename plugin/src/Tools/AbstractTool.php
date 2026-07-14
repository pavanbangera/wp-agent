<?php

declare(strict_types=1);

namespace WpAgent\Tools;

use WpAgent\Auth\Identity;
use WpAgent\Exceptions\ValidationException;
use WpAgent\MCP\Contracts\ToolInterface;
use WpAgent\MCP\Protocol\ToolResult;

/**
 * Abstract base class for all WP Agent MCP tools.
 *
 * Handles input validation (JSON Schema 7), capability verification,
 * and error normalization so concrete tools contain only business logic.
 *
 * Implementation pattern:
 * ```php
 * final class CreatePageTool extends AbstractTool
 * {
 *     public function getName(): string { return 'wordpress.pages.create'; }
 *     public function getDescription(): string { return 'Creates a new WordPress page.'; }
 *     public function getInputSchema(): array { return [...]; }
 *     protected function handle(array $args, Identity $identity): ToolResult { ... }
 * }
 * ```
 *
 * @package WpAgent\Tools
 * @since   0.1.0
 */
abstract class AbstractTool implements ToolInterface
{
    /**
     * {@inheritDoc}
     *
     * Default: no scopes required. Override in tools that need protection.
     */
    public function getRequiredScopes(): array
    {
        return [];
    }

    /**
     * {@inheritDoc}
     *
     * Default: no annotations. Override for readOnly, destructive, etc.
     */
    public function getAnnotations(): array
    {
        return [];
    }

    /**
     * {@inheritDoc}
     *
     * Validates input, then delegates to handle().
     * Do NOT override this method in concrete tools.
     */
    final public function execute(array $args, Identity $identity): ToolResult
    {
        $this->validateInput($args);

        return $this->handle($args, $identity);
    }

    /**
     * The tool's business logic implementation.
     *
     * @param array<string, mixed> $args     Validated arguments.
     * @param Identity             $identity Authenticated caller.
     */
    abstract protected function handle(array $args, Identity $identity): ToolResult;

    // -------------------------------------------------------------------------
    // Validation helpers — available to concrete tools
    // -------------------------------------------------------------------------

    /**
     * Asserts a WordPress capability against the current user.
     *
     * @throws \WpAgent\Exceptions\ToolException
     */
    protected function requireCapability(string $capability, Identity $identity): void
    {
        if ( ! $identity->can($capability) ) {
            throw \WpAgent\Exceptions\ToolException::permissionDenied($this->getName(), $capability);
        }
    }

    /**
     * Asserts a post/page exists and returns it.
     *
     * @throws \WpAgent\Exceptions\ToolException
     */
    protected function requirePost(int $postId, string $postType = 'any'): \WP_Post
    {
        $post = get_post($postId);

        if ( ! ($post instanceof \WP_Post) ) {
            throw \WpAgent\Exceptions\ToolException::notFound($this->getName(), 'Post', $postId);
        }

        if ( $postType !== 'any' && $post->post_type !== $postType ) {
            throw \WpAgent\Exceptions\ToolException::notFound($this->getName(), ucfirst($postType), $postId);
        }

        return $post;
    }

    /**
     * Wraps a WP_Error or false result from a WordPress function.
     *
     * @param \WP_Error|int|false $result WordPress operation result.
     * @param string              $onEmpty Error message for false/0 results.
     *
     * @return int The post/term ID on success.
     *
     * @throws \WpAgent\Exceptions\ToolException
     */
    protected function unwrapWpResult(\WP_Error|int|false $result, string $onEmpty = 'Operation failed.'): int
    {
        if ( is_wp_error($result) ) {
            throw \WpAgent\Exceptions\ToolException::fromWpError($this->getName(), $result);
        }

        if ( false === $result || 0 === $result ) {
            throw new \WpAgent\Exceptions\ToolException(
                $onEmpty,
                $this->getName(),
                \WpAgent\Exceptions\ToolException::OPERATION_FAILED,
            );
        }

        return (int) $result;
    }

    // -------------------------------------------------------------------------
    // Private: input validation
    // -------------------------------------------------------------------------

    /**
     * Validates input arguments against the tool's JSON Schema.
     *
     * Uses a lightweight schema validator rather than a full library
     * to keep dependencies minimal. Complex schemas can use
     * json_validate() (PHP 8.3) or a custom validator.
     *
     * @param array<string, mixed> $args
     *
     * @throws ValidationException
     */
    private function validateInput(array $args): void
    {
        $schema = $this->getInputSchema();
        $errors = [];

        // Validate required fields.
        $required = $schema['required'] ?? [];
        foreach ( $required as $field ) {
            if ( ! array_key_exists($field, $args) ) {
                $errors[$field][] = "Field '{$field}' is required.";
            }
        }

        // Validate property types.
        $properties = $schema['properties'] ?? [];
        foreach ( $args as $key => $value ) {
            if ( ! isset($properties[$key]) ) {
                // additionalProperties: false check.
                if ( false === ( $schema['additionalProperties'] ?? true ) ) {
                    $errors[$key][] = "Unknown property '{$key}'.";
                }
                continue;
            }

            $propSchema = $properties[$key];
            $typeErrors = $this->validateType($key, $value, $propSchema);
            if ( ! empty($typeErrors) ) {
                $errors[$key] = array_merge($errors[$key] ?? [], $typeErrors);
            }
        }

        if ( ! empty($errors) ) {
            throw new ValidationException($errors);
        }
    }

    /**
     * Validates a single value against a JSON Schema type definition.
     *
     * @param string               $key    Property name (for error messages).
     * @param mixed                $value  Value to validate.
     * @param array<string, mixed> $schema Property schema.
     *
     * @return string[] Errors (empty if valid).
     */
    private function validateType(string $key, mixed $value, array $schema): array
    {
        $errors = [];
        $type   = $schema['type'] ?? null;

        if ( null === $type ) {
            return $errors;
        }

        $valid = match ($type) {
            'string'  => is_string($value),
            'integer' => is_int($value),
            'number'  => is_int($value) || is_float($value),
            'boolean' => is_bool($value),
            'array'   => is_array($value),
            'object'  => is_array($value) || is_object($value),
            'null'    => is_null($value),
            default   => true,
        };

        if ( ! $valid ) {
            $errors[] = "Property '{$key}' must be of type '{$type}', got " . gettype($value) . '.';
        }

        // Enum validation.
        if ( isset($schema['enum']) && ! in_array($value, $schema['enum'], true) ) {
            $allowed  = implode(', ', array_map(
                static fn (mixed $v): string => (string) json_encode($v),
                $schema['enum']
            ));
            $errors[] = "Property '{$key}' must be one of: {$allowed}.";
        }

        // String constraints.
        if ( 'string' === $type && is_string($value) ) {
            if ( isset($schema['minLength']) && strlen($value) < $schema['minLength'] ) {
                $errors[] = "Property '{$key}' must be at least {$schema['minLength']} characters.";
            }
            if ( isset($schema['maxLength']) && strlen($value) > $schema['maxLength'] ) {
                $errors[] = "Property '{$key}' must not exceed {$schema['maxLength']} characters.";
            }
            if ( isset($schema['pattern']) && ! preg_match('/' . $schema['pattern'] . '/', $value) ) {
                $errors[] = "Property '{$key}' does not match the required pattern.";
            }
        }

        // Numeric constraints.
        if ( in_array($type, ['integer', 'number'], true) && is_numeric($value) ) {
            if ( isset($schema['minimum']) && $value < $schema['minimum'] ) {
                $errors[] = "Property '{$key}' must be at least {$schema['minimum']}.";
            }
            if ( isset($schema['maximum']) && $value > $schema['maximum'] ) {
                $errors[] = "Property '{$key}' must not exceed {$schema['maximum']}.";
            }
        }

        return $errors;
    }
}
