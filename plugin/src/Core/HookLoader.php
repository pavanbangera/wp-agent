<?php

declare(strict_types=1);

namespace WpAgent\Core;

/**
 * Deferred WordPress action/filter hook registrar.
 *
 * Collects hook registrations from service providers and registers
 * them all at once, allowing for clean provider code.
 *
 * @package WpAgent\Core
 * @since   0.1.0
 */
final class HookLoader
{
    /**
     * @var array<int, array{type: 'action'|'filter', hook: string, callback: callable, priority: int, args: int}>
     */
    private array $queue = [];

    /**
     * Queue a WordPress action registration.
     *
     * @param string   $hook     Action hook name.
     * @param callable $callback Callback to execute.
     * @param int      $priority Hook priority.
     * @param int      $args     Number of accepted arguments.
     */
    public function addAction(
        string $hook,
        callable $callback,
        int $priority = 10,
        int $args = 1,
    ): void {
        $this->queue[] = [
            'type'     => 'action',
            'hook'     => $hook,
            'callback' => $callback,
            'priority' => $priority,
            'args'     => $args,
        ];
    }

    /**
     * Queue a WordPress filter registration.
     *
     * @param string   $hook     Filter hook name.
     * @param callable $callback Callback to execute.
     * @param int      $priority Hook priority.
     * @param int      $args     Number of accepted arguments.
     */
    public function addFilter(
        string $hook,
        callable $callback,
        int $priority = 10,
        int $args = 1,
    ): void {
        $this->queue[] = [
            'type'     => 'filter',
            'hook'     => $hook,
            'callback' => $callback,
            'priority' => $priority,
            'args'     => $args,
        ];
    }

    /**
     * Flush all queued hooks into WordPress.
     *
     * Called once by Plugin::boot() after all providers have registered.
     */
    public function run(): void
    {
        foreach ( $this->queue as $entry ) {
            if ( 'action' === $entry['type'] ) {
                add_action($entry['hook'], $entry['callback'], $entry['priority'], $entry['args']);
            } else {
                add_filter($entry['hook'], $entry['callback'], $entry['priority'], $entry['args']);
            }
        }

        // Allow hook registration to be extended via WordPress action.
        do_action('wpa_hooks_loaded');

        $this->queue = [];
    }
}
