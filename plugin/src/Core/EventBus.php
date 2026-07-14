<?php

declare(strict_types=1);

namespace WpAgent\Core;

/**
 * Internal synchronous event bus.
 *
 * Allows decoupled modules to communicate without tight coupling.
 * Wraps WordPress action/filter hooks but provides a typed interface.
 *
 * @package WpAgent\Core
 * @since   0.1.0
 */
final class EventBus
{
    private const HOOK_PREFIX = 'wpa_event_';

    /**
     * Subscribe to an event.
     *
     * @param string   $event    Event name (e.g. 'tool.executed').
     * @param callable $listener Listener callable.
     * @param int      $priority WordPress hook priority.
     */
    public function on(string $event, callable $listener, int $priority = 10): void
    {
        add_action(self::HOOK_PREFIX . $event, $listener, $priority, PHP_INT_MAX);
    }

    /**
     * Subscribe once — automatically removes itself after first fire.
     *
     * @param string   $event    Event name.
     * @param callable $listener Listener callable.
     */
    public function once(string $event, callable $listener): void
    {
        $wrapper = null;
        $wrapper = function () use ($event, $listener, &$wrapper): void {
            if ( is_callable($wrapper) ) {
                remove_action(self::HOOK_PREFIX . $event, $wrapper, 10);
            }
            $listener(...func_get_args());
        };

        $this->on($event, $wrapper);
    }

    /**
     * Emit an event with optional payload.
     *
     * @param string               $event   Event name.
     * @param array<string, mixed> $payload Data passed to listeners.
     */
    public function emit(string $event, array $payload = []): void
    {
        do_action(self::HOOK_PREFIX . $event, $payload);
    }

    /**
     * Remove a specific listener.
     *
     * @param string   $event    Event name.
     * @param callable $listener The listener to remove.
     * @param int      $priority Hook priority used when registering.
     */
    public function off(string $event, callable $listener, int $priority = 10): void
    {
        remove_action(self::HOOK_PREFIX . $event, $listener, $priority);
    }
}
