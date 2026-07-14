<?php

declare(strict_types=1);

namespace WpAgent\Core\Providers;

use WpAgent\Auth\AuthManager;
use WpAgent\Auth\Drivers\ApplicationPasswordDriver;
use WpAgent\Auth\Drivers\JwtDriver;
use WpAgent\Core\ServiceProvider;

/**
 * Registers auth-related services into the DI container.
 *
 * @package WpAgent\Core\Providers
 * @since   0.1.0
 */
final class AuthProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->container->singleton(
            JwtDriver::class,
            fn ($c) => new JwtDriver($c->get(\WpAgent\Core\Config::class))
        );

        $this->container->singleton(
            ApplicationPasswordDriver::class,
            fn ($c) => new ApplicationPasswordDriver($c->get(\WpAgent\Core\Config::class))
        );

        $this->container->singleton(AuthManager::class, function ($c): AuthManager {
            $manager = new AuthManager($c->get(\WpAgent\Core\Config::class));

            // Register default drivers.
            $manager->extend('jwt', $c->get(JwtDriver::class));
            $manager->extend('application_password', $c->get(ApplicationPasswordDriver::class));

            return $manager;
        });
    }
}
