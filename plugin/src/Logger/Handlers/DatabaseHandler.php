<?php

declare(strict_types=1);

namespace WpAgent\Logger\Handlers;

use WpAgent\Logger\LogHandlerInterface;

/**
 * Database log handler.
 *
 * Persists log records to the wp_wpa_execution_log table.
 *
 * @package WpAgent\Logger\Handlers
 * @since   0.1.0
 */
final class DatabaseHandler implements LogHandlerInterface
{
    /**
     * @param array{level: string, message: string, context: array<string, mixed>, time: string} $record
     */
    public function handle(array $record): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'wpa_execution_log';

        // phpcs:disable WordPress.DB.DirectDatabaseQuery
        $wpdb->insert(
            $table,
            [
                'session_id'  => sanitize_text_field($record['context']['session_id'] ?? ''),
                'tool_name'   => sanitize_text_field($record['context']['tool'] ?? ''),
                'tool_input'  => wp_json_encode($record['context']),
                'tool_output' => null,
                'status'      => $record['level'],
                'user_id'     => (int) ( $record['context']['user_id'] ?? 0 ),
                'client_name' => sanitize_text_field($record['context']['client_name'] ?? ''),
                'duration_ms' => (int) ( $record['context']['duration_ms'] ?? 0 ),
                'created_at'  => $record['time'],
            ],
            ['%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%s']
        );
        // phpcs:enable
    }
}
