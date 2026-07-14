<?php

declare(strict_types=1);

namespace WpAgent\Auth\Drivers;

use WpAgent\Auth\Contracts\AuthDriverInterface;
use WpAgent\Auth\Identity;
use WpAgent\Core\Config;
use WpAgent\Exceptions\AuthException;

/**
 * Application Password authentication driver.
 *
 * Uses WordPress 5.6+ Application Passwords as the default (zero-config)
 * authentication method. Credentials transmitted via HTTP Basic Auth.
 *
 * Security:
 * - Requires HTTPS in production (WordPress enforces this via wp_is_application_passwords_available()).
 * - Credentials are never stored by WP Agent — validated on each request via WP core.
 * - Scopes are derived from the user's WordPress capabilities.
 *
 * @package WpAgent\Auth\Drivers
 * @since   0.1.0
 */
final class ApplicationPasswordDriver implements AuthDriverInterface
{
    public function __construct(private readonly Config $config) {}

    /**
     * {@inheritDoc}
     *
     * @throws AuthException
     */
    public function authenticate(\WP_REST_Request $request): Identity
    {
        // WordPress REST API already processes Basic Auth via the
        // Application Passwords feature; we just need to verify the
        // current_user is set correctly.
        $user = wp_get_current_user();

        if ( ! $user->exists() ) {
            // Attempt manual Basic Auth parsing for non-REST contexts.
            $user = $this->resolveFromBasicAuth($request);
        }

        if ( ! $user->exists() ) {
            throw AuthException::invalidCredentials();
        }

        if ( ! $user->has_cap('read') ) {
            throw AuthException::userDisabled();
        }

        $scopes = $this->resolveScopesForUser($user);

        return new Identity(
            user: $user,
            scopes: $scopes,
            driver: $this->getName(),
            sessionId: $this->generateSessionId($user),
            clientName: $this->extractClientName($request),
        );
    }

    /**
     * {@inheritDoc}
     */
    public function supports(\WP_REST_Request $request): bool
    {
        $authHeader = $request->get_header('Authorization');

        if ( ! empty($authHeader) && str_starts_with(strtolower($authHeader), 'basic ') ) {
            return true;
        }

        // PHP_AUTH_USER is set when WP processed Basic Auth credentials.
        return ! empty($_SERVER['PHP_AUTH_USER']);
    }

    public function getName(): string
    {
        return 'application_password';
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Attempts to resolve a user from a raw Basic Auth header.
     *
     * WordPress typically handles this automatically, but we fall back
     * here for edge cases (e.g., custom server configurations).
     */
    private function resolveFromBasicAuth(\WP_REST_Request $request): \WP_User
    {
        $authHeader = $request->get_header('Authorization') ?? '';

        if ( ! str_starts_with(strtolower($authHeader), 'basic ') ) {
            return new \WP_User(0);
        }

        $decoded = base64_decode(substr($authHeader, 6), true);
        if ( false === $decoded || ! str_contains($decoded, ':') ) {
            return new \WP_User(0);
        }

        [ $login, $password ] = explode(':', $decoded, 2);

        // wp_authenticate_application_password handles the actual verification.
        $authenticated = wp_authenticate_application_password(
            null,
            sanitize_user($login),
            sanitize_text_field($password),
        );

        if ( is_wp_error($authenticated) || ! ($authenticated instanceof \WP_User) ) {
            return new \WP_User(0);
        }

        return $authenticated;
    }

    /**
     * Derives MCP scopes from WordPress user capabilities.
     *
     * @return string[]
     */
    private function resolveScopesForUser(\WP_User $user): array
    {
        // Check for explicitly stored scopes on this user.
        $storedScopes = get_user_meta($user->ID, 'wpa_granted_scopes', true);
        if ( is_array($storedScopes) && ! empty($storedScopes) ) {
            return $storedScopes;
        }

        // Derive from WP role capabilities.
        $scopes = ['wp-agent:read']; // All authenticated users get read.

        if ( $user->has_cap('edit_posts') ) {
            $scopes[] = 'wp-agent:write';
        }

        if ( $user->has_cap('activate_plugins') ) {
            $scopes[] = 'wp-agent:admin';
        }

        if ( $user->has_cap('install_plugins') ) {
            $scopes[] = 'wp-agent:developer';
        }

        if ( $user->has_cap('manage_options') ) {
            $scopes[] = 'wp-agent:security';
        }

        if ( $user->has_cap('delete_themes') && $user->has_cap('delete_plugins') ) {
            $scopes[] = 'wp-agent:superadmin';
        }

        /**
         * Filters the resolved MCP scopes for a user.
         *
         * @param string[] $scopes  Resolved scopes.
         * @param \WP_User $user    WordPress user.
         * @param string   $driver  Auth driver name.
         *
         * @since 0.1.0
         */
        return (array) apply_filters('wpa_auth_user_scopes', $scopes, $user, $this->getName());
    }

    private function generateSessionId(\WP_User $user): string
    {
        return 'ap_' . wp_hash($user->ID . time() . wp_generate_uuid4(), 'auth');
    }

    private function extractClientName(\WP_REST_Request $request): string
    {
        $userAgent = $request->get_header('User-Agent') ?? '';
        return sanitize_text_field(substr($userAgent, 0, 64));
    }
}
