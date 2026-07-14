<?php

declare(strict_types=1);

namespace WpAgent\Exceptions;

/**
 * Thrown when authentication or authorization fails.
 *
 * @package WpAgent\Exceptions
 * @since   0.1.0
 */
final class AuthException extends WpAgentException
{
    public const INVALID_CREDENTIALS = 1001;
    public const TOKEN_EXPIRED       = 1002;
    public const TOKEN_INVALID       = 1003;
    public const INSUFFICIENT_SCOPE  = 1004;
    public const DRIVER_NOT_FOUND    = 1005;
    public const USER_DISABLED       = 1006;

    public static function invalidCredentials(): self
    {
        return new self( 'Invalid credentials provided.', [], self::INVALID_CREDENTIALS );
    }

    public static function tokenExpired(): self
    {
        return new self( 'Authentication token has expired.', [], self::TOKEN_EXPIRED );
    }

    public static function tokenInvalid(string $reason = ''): self
    {
        return new self(
            'Authentication token is invalid.' . ( $reason ? " {$reason}" : '' ),
            [],
            self::TOKEN_INVALID
        );
    }

    /**
     * @param string[] $required Required scopes.
     * @param string[] $provided Provided scopes.
     */
    public static function insufficientScope(array $required, array $provided): self
    {
        return new self(
            'Insufficient permissions for this operation.',
            ['required' => $required, 'provided' => $provided],
            self::INSUFFICIENT_SCOPE,
        );
    }

    public static function userDisabled(): self
    {
        return new self( 'User account is disabled.', [], self::USER_DISABLED );
    }
}
