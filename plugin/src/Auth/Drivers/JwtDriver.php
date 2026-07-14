<?php

declare(strict_types=1);

namespace WpAgent\Auth\Drivers;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;
use WpAgent\Auth\Contracts\AuthDriverInterface;
use WpAgent\Auth\Identity;
use WpAgent\Core\Config;
use WpAgent\Exceptions\AuthException;

/**
 * JWT Bearer Token authentication driver.
 *
 * Tokens are issued by WP Agent's token endpoint and signed with
 * a configurable secret (HS256 by default, RS256 supported via config).
 *
 * Token payload:
 * {
 *   "iss": "https://site.com",
 *   "aud": "wp-agent",
 *   "iat": 1234567890,
 *   "exp": 1234568790,
 *   "sub": "42",            // WordPress user ID
 *   "scp": ["wp-agent:read", "wp-agent:write"],
 *   "cln": "cursor",        // client name
 *   "jti": "uuid-v4"        // unique token ID
 * }
 *
 * @package WpAgent\Auth\Drivers
 * @since   0.1.0
 */
final class JwtDriver implements AuthDriverInterface
{
    public function __construct(private readonly Config $config) {}

    /**
     * {@inheritDoc}
     *
     * @throws AuthException
     */
    public function authenticate(\WP_REST_Request $request): Identity
    {
        $token = $this->extractToken($request);

        if ( empty($token) ) {
            throw AuthException::tokenInvalid('No Bearer token provided.');
        }

        $claims = $this->decode($token);

        $userId = (int) ( $claims->sub ?? 0 );
        if ( $userId <= 0 ) {
            throw AuthException::tokenInvalid('Token subject (sub) is missing or invalid.');
        }

        $user = get_user_by('id', $userId);
        if ( false === $user ) {
            throw AuthException::invalidCredentials();
        }

        if ( ! $user->exists() ) {
            throw AuthException::userDisabled();
        }

        // Validate audience.
        $audience = $claims->aud ?? '';
        if ( $audience !== 'wp-agent' ) {
            throw AuthException::tokenInvalid('Token audience (aud) is not "wp-agent".');
        }

        // Validate issuer.
        $issuer = $claims->iss ?? '';
        if ( $issuer !== get_bloginfo('url') ) {
            throw AuthException::tokenInvalid('Token issuer (iss) does not match site URL.');
        }

        /** @var string[] $scopes */
        $scopes = (array) ( $claims->scp ?? ['wp-agent:read'] );

        return new Identity(
            user: $user,
            scopes: $scopes,
            driver: $this->getName(),
            sessionId: (string) ( $claims->jti ?? '' ),
            clientName: sanitize_text_field((string) ( $claims->cln ?? '' )),
        );
    }

    /**
     * {@inheritDoc}
     */
    public function supports(\WP_REST_Request $request): bool
    {
        $authHeader = $request->get_header('Authorization') ?? '';
        return str_starts_with(strtolower($authHeader), 'bearer ');
    }

    public function getName(): string
    {
        return 'jwt';
    }

    // -------------------------------------------------------------------------
    // Token issuance (used by the token endpoint)
    // -------------------------------------------------------------------------

    /**
     * Issues a signed JWT for a given WordPress user.
     *
     * @param \WP_User $user   The user to issue the token for.
     * @param string[] $scopes MCP scopes to embed in the token.
     *
     * @return array{access_token: string, token_type: string, expires_in: int, scopes: string[]}
     *
     * @throws AuthException If JWT secret is not configured.
     */
    public function issue(\WP_User $user, array $scopes): array
    {
        $secret = $this->getSecret();
        $expiry = $this->config->int('auth.jwt.expiry', 900);
        $now    = time();

        $payload = [
            'iss' => get_bloginfo('url'),
            'aud' => 'wp-agent',
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + $expiry,
            'sub' => (string) $user->ID,
            'scp' => $scopes,
            'cln' => '',
            'jti' => wp_generate_uuid4(),
        ];

        /**
         * Filters the JWT payload before signing.
         *
         * @param array<string, mixed> $payload JWT claims.
         * @param \WP_User             $user    WordPress user.
         *
         * @since 0.1.0
         */
        $payload = (array) apply_filters('wpa_jwt_payload', $payload, $user);

        $algorithm = $this->config->string('auth.jwt.algorithm', 'HS256');
        $token     = JWT::encode($payload, $secret, $algorithm);

        return [
            'access_token' => $token,
            'token_type'   => 'Bearer',
            'expires_in'   => $expiry,
            'scopes'       => $scopes,
        ];
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function extractToken(\WP_REST_Request $request): string
    {
        $authHeader = $request->get_header('Authorization') ?? '';

        if ( str_starts_with(strtolower($authHeader), 'bearer ') ) {
            return trim(substr($authHeader, 7));
        }

        return '';
    }

    /**
     * Decodes and verifies the JWT.
     *
     * @throws AuthException
     */
    private function decode(string $token): object
    {
        $secret    = $this->getSecret();
        $algorithm = $this->config->string('auth.jwt.algorithm', 'HS256');

        try {
            return JWT::decode($token, new Key($secret, $algorithm));
        } catch ( ExpiredException $e ) {
            throw AuthException::tokenExpired();
        } catch ( SignatureInvalidException $e ) {
            throw AuthException::tokenInvalid('Signature verification failed.');
        } catch ( \Exception $e ) {
            throw AuthException::tokenInvalid($e->getMessage());
        }
    }

    /**
     * @throws AuthException
     */
    private function getSecret(): string
    {
        $secret = $this->config->string('auth.jwt.secret');

        if ( empty($secret) ) {
            // Fallback: use WordPress auth key (available without config).
            $secret = wp_salt('auth');
        }

        if ( strlen($secret) < 32 ) {
            throw new AuthException(
                'JWT secret must be at least 32 characters. Configure "auth.jwt.secret" in WP Agent settings.',
                [],
                0,
            );
        }

        return $secret;
    }
}
