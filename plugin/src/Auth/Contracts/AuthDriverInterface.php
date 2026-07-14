<?php

declare(strict_types=1);

namespace WpAgent\Auth\Contracts;

use WpAgent\Auth\Identity;
use WpAgent\Exceptions\AuthException;

/**
 * Contract for authentication drivers.
 *
 * Each driver is responsible for extracting credentials from the request
 * and resolving them to an authenticated Identity.
 *
 * @package WpAgent\Auth\Contracts
 * @since   0.1.0
 */
interface AuthDriverInterface
{
    /**
     * Attempt to authenticate the given request.
     *
     * @param \WP_REST_Request $request The incoming REST request.
     *
     * @return Identity Resolved identity on success.
     *
     * @throws AuthException If credentials are missing, invalid, or expired.
     */
    public function authenticate(\WP_REST_Request $request): Identity;

    /**
     * Returns true if this driver can handle the given request.
     *
     * Used for driver auto-detection (e.g. "Authorization: Bearer" → JWT).
     *
     * @param \WP_REST_Request $request The incoming REST request.
     */
    public function supports(\WP_REST_Request $request): bool;

    /**
     * Returns the driver's unique identifier.
     *
     * Used for logging and configuration references.
     */
    public function getName(): string;
}
