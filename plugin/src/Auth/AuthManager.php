<?php

declare(strict_types=1);

namespace WpAgent\Auth;

use WpAgent\Auth\Contracts\AuthDriverInterface;
use WpAgent\Auth\Drivers\ApplicationPasswordDriver;
use WpAgent\Auth\Drivers\JwtDriver;
use WpAgent\Core\Config;
use WpAgent\Exceptions\AuthException;

/**
 * Authentication manager — driver registry and request authenticator.
 *
 * Resolves the appropriate driver for an incoming request via auto-detection
 * (drivers advertise whether they support a given request via supports()).
 *
 * @package WpAgent\Auth
 * @since   0.1.0
 */
final class AuthManager
{
    /** @var array<string, AuthDriverInterface> */
    private array $drivers = [];

    public function __construct(private readonly Config $config) {}

    /**
     * Register an authentication driver.
     */
    public function extend(string $name, AuthDriverInterface $driver): void
    {
        $this->drivers[$name] = $driver;
    }

    /**
     * Resolve a driver by name.
     *
     * @throws AuthException If driver is not registered.
     */
    public function driver(string $name): AuthDriverInterface
    {
        if ( ! isset( $this->drivers[$name] ) ) {
            throw new AuthException(
                sprintf('Auth driver "%s" is not registered.', $name),
                [],
                AuthException::DRIVER_NOT_FOUND,
            );
        }

        return $this->drivers[$name];
    }

    /**
     * Authenticate an incoming request.
     *
     * Auto-detects the driver from the request; falls back to the
     * configured default driver if no driver claims the request.
     *
     * @throws AuthException If authentication fails.
     */
    public function authenticate(\WP_REST_Request $request): Identity
    {
        $driver = $this->detectDriver($request);
        return $driver->authenticate($request);
    }

    /**
     * Authorize an identity against required scopes.
     *
     * @param string[] $requiredScopes
     *
     * @throws AuthException If the identity lacks required scopes.
     */
    public function authorize(Identity $identity, array $requiredScopes): void
    {
        if ( empty($requiredScopes) ) {
            return;
        }

        if ( ! $identity->hasAllScopes($requiredScopes) ) {
            throw AuthException::insufficientScope($requiredScopes, $identity->getScopes());
        }
    }

    /**
     * Returns all registered driver names.
     *
     * @return string[]
     */
    public function getDriverNames(): array
    {
        return array_keys($this->drivers);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function detectDriver(\WP_REST_Request $request): AuthDriverInterface
    {
        // Priority order: specific drivers first.
        $prioritized = ['jwt', 'api_key', 'application_password'];

        foreach ( $prioritized as $name ) {
            if ( isset($this->drivers[$name]) && $this->drivers[$name]->supports($request) ) {
                return $this->drivers[$name];
            }
        }

        // Fall back to any driver that claims the request.
        foreach ( $this->drivers as $driver ) {
            if ( $driver->supports($request) ) {
                return $driver;
            }
        }

        // Last resort: use configured default driver.
        $default = $this->config->string('auth.default_driver', 'application_password');

        return $this->driver($default);
    }
}
