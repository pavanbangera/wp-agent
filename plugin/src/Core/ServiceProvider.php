<?php

declare(strict_types=1);

namespace WpAgent\Core;

/**
 * Abstract base for service providers.
 *
 * Service providers are the primary way to register bindings into
 * the DI container and queue WordPress hooks. Each module ships
 * its own provider.
 *
 * @package WpAgent\Core
 * @since   0.1.0
 */
abstract class ServiceProvider
{
    public function __construct(
        protected readonly Container  $container,
        protected readonly HookLoader $hooks,
        protected readonly Config     $config,
    ) {}

    /**
     * Register bindings into the container.
     *
     * Called before boot(). Do NOT resolve services here — only bind.
     */
    abstract public function register(): void;

    /**
     * Boot provider logic.
     *
     * Called after all providers have registered. Safe to resolve
     * dependencies and queue WordPress hooks here.
     */
    public function boot(): void
    {
        // Default: no boot logic. Override in concrete providers.
    }
}
