<?php

declare(strict_types=1);

namespace WpAgent\Core;

use Psr\Container\ContainerInterface;
use WpAgent\Exceptions\WpAgentException;

/**
 * PSR-11 compliant Dependency Injection Container.
 *
 * Supports singleton bindings, factory bindings, and contextual resolution.
 * Designed to be immutable after boot — no re-registration after boot().
 *
 * @package WpAgent\Core
 * @since   0.1.0
 */
final class Container implements ContainerInterface
{
    /** @var array<string, callable(Container): mixed> */
    private array $bindings = [];

    /** @var array<string, mixed> */
    private array $instances = [];

    /** @var array<string, true> */
    private array $singletons = [];

    private bool $booted = false;

    /**
     * Bind a factory callable to an identifier.
     *
     * The factory receives this container as its only argument.
     *
     * @param string                         $id      Service identifier.
     * @param callable(Container): mixed     $factory Factory closure.
     * @param bool                           $shared  Whether to cache (singleton).
     *
     * @throws WpAgentException If container is already booted.
     */
    public function bind(string $id, callable $factory, bool $shared = false): void
    {
        $this->guardBooted();
        $this->bindings[$id]  = $factory;
        if ( $shared ) {
            $this->singletons[$id] = true;
        }
    }

    /**
     * Bind a singleton factory — resolved only once and cached.
     *
     * @param string                     $id      Service identifier.
     * @param callable(Container): mixed $factory Factory closure.
     */
    public function singleton(string $id, callable $factory): void
    {
        $this->bind($id, $factory, true);
    }

    /**
     * Register a pre-built instance as a singleton.
     *
     * @param string $id       Service identifier.
     * @param mixed  $instance The instance to register.
     */
    public function instance(string $id, mixed $instance): void
    {
        $this->guardBooted();
        $this->instances[$id]  = $instance;
        $this->singletons[$id] = true;
    }

    /**
     * Resolves a service by identifier.
     *
     * @param string $id Service identifier.
     *
     * @return mixed
     *
     * @throws \Psr\Container\NotFoundExceptionInterface If not registered.
     */
    public function get(string $id): mixed
    {
        // Return cached singleton instance.
        if ( array_key_exists($id, $this->instances) ) {
            return $this->instances[$id];
        }

        if ( ! isset( $this->bindings[$id] ) ) {
            throw new class( "Service '{$id}' is not registered in the WP Agent container." )
                extends \RuntimeException
                implements \Psr\Container\NotFoundExceptionInterface {};
        }

        $value = ( $this->bindings[$id] )($this);

        // Cache if singleton.
        if ( isset( $this->singletons[$id] ) ) {
            $this->instances[$id] = $value;
        }

        return $value;
    }

    /**
     * @param string $id Service identifier.
     */
    public function has(string $id): bool
    {
        return isset( $this->bindings[$id] ) || array_key_exists($id, $this->instances);
    }

    /**
     * Called once after all providers have registered bindings.
     * Prevents further registrations (immutability after boot).
     */
    public function boot(): void
    {
        $this->booted = true;
    }

    /**
     * Convenience alias for get() with a typed return.
     *
     * @template T
     * @param string   $id    Service identifier.
     * @param class-string<T> $type  Expected class — used for IDE inference only.
     * @return T
     */
    public function make(string $id, string $type = ''): mixed // @phpstan-ignore-line
    {
        return $this->get($id);
    }

    private function guardBooted(): void
    {
        if ( $this->booted ) {
            throw new WpAgentException(
                'Cannot register bindings on a booted container. Register bindings in a ServiceProvider.'
            );
        }
    }
}
