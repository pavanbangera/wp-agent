<?php

declare(strict_types=1);

namespace WpAgent\Auth;

/**
 * Authenticated identity value object.
 *
 * Carries the resolved WordPress user and their granted MCP scopes.
 * Immutable after construction.
 *
 * @package WpAgent\Auth
 * @since   0.1.0
 */
final class Identity
{
    /**
     * @param \WP_User $user         Authenticated WordPress user.
     * @param string[] $scopes       Granted MCP permission scopes.
     * @param string   $driver       Auth driver used (for logging).
     * @param string   $sessionId    MCP session identifier.
     * @param string   $clientName   Connecting AI client name.
     */
    public function __construct(
        private readonly \WP_User $user,
        private readonly array $scopes,
        private readonly string $driver,
        private readonly string $sessionId = '',
        private readonly string $clientName = '',
    ) {}

    public function getUser(): \WP_User
    {
        return $this->user;
    }

    public function getUserId(): int
    {
        return (int) $this->user->ID;
    }

    public function getLogin(): string
    {
        return (string) $this->user->user_login;
    }

    /**
     * Returns granted MCP scopes.
     *
     * @return string[]
     */
    public function getScopes(): array
    {
        return $this->scopes;
    }

    /**
     * Checks if this identity has a given scope.
     */
    public function hasScope(string $scope): bool
    {
        // 'wp-agent:superadmin' grants everything.
        if ( in_array('wp-agent:superadmin', $this->scopes, true) ) {
            return true;
        }

        return in_array($scope, $this->scopes, true);
    }

    /**
     * Checks if this identity holds all the specified scopes.
     *
     * @param string[] $scopes
     */
    public function hasAllScopes(array $scopes): bool
    {
        foreach ( $scopes as $scope ) {
            if ( ! $this->hasScope($scope) ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Checks if the underlying WP user has a WordPress capability.
     *
     * Always performs WP capability check — not scope-only.
     */
    public function can(string $capability): bool
    {
        return user_can($this->user, $capability);
    }

    public function getDriver(): string
    {
        return $this->driver;
    }

    public function getSessionId(): string
    {
        return $this->sessionId;
    }

    public function getClientName(): string
    {
        return $this->clientName;
    }

    /**
     * Serializes to an array for logging (no sensitive data).
     *
     * @return array<string, mixed>
     */
    public function toLogArray(): array
    {
        return [
            'user_id'     => $this->getUserId(),
            'user_login'  => $this->getLogin(),
            'scopes'      => $this->scopes,
            'driver'      => $this->driver,
            'session_id'  => $this->sessionId,
            'client_name' => $this->clientName,
        ];
    }
}
