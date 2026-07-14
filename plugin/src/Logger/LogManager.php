<?php

declare(strict_types=1);

namespace WpAgent\Logger;

use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;

/**
 * PSR-3 compliant log manager.
 *
 * Routes log messages to one or more handlers (Database, File).
 * Respects configured log level thresholds.
 *
 * @package WpAgent\Logger
 * @since   0.1.0
 */
final class LogManager extends AbstractLogger
{
    /** PSR-3 log level severity map (higher = more severe). */
    private const SEVERITY = [
        LogLevel::DEBUG     => 0,
        LogLevel::INFO      => 1,
        LogLevel::NOTICE    => 2,
        LogLevel::WARNING   => 3,
        LogLevel::ERROR     => 4,
        LogLevel::CRITICAL  => 5,
        LogLevel::ALERT     => 6,
        LogLevel::EMERGENCY => 7,
    ];

    /** @var LogHandlerInterface[] */
    private array $handlers = [];

    private string $minimumLevel;

    public function __construct(string $minimumLevel = LogLevel::INFO)
    {
        $this->minimumLevel = $minimumLevel;
    }

    /**
     * Adds a log handler.
     */
    public function addHandler(LogHandlerInterface $handler): void
    {
        $this->handlers[] = $handler;
    }

    /**
     * Adjusts the minimum log level at runtime (e.g. from logging/setLevel).
     */
    public function setLevel(string $level): void
    {
        if ( array_key_exists($level, self::SEVERITY) ) {
            $this->minimumLevel = $level;
        }
    }

    /**
     * {@inheritDoc}
     *
     * @param string               $level   PSR-3 log level.
     * @param string|\Stringable   $message Log message.
     * @param array<string, mixed> $context Context data.
     */
    public function log(mixed $level, string|\Stringable $message, array $context = []): void
    {
        $level = (string) $level;

        // Skip messages below minimum severity.
        if ( ! $this->isLoggable($level) ) {
            return;
        }

        $message = (string) $message;

        // Interpolate placeholders like {foo} from context.
        $message = $this->interpolate($message, $context);

        $record = [
            'level'   => $level,
            'message' => $message,
            'context' => $context,
            'time'    => current_time('mysql', true),
        ];

        foreach ( $this->handlers as $handler ) {
            try {
                $handler->handle($record);
            } catch ( \Throwable ) {
                // Never let a logger break the application.
            }
        }

        /**
         * Fires for every WP Agent log entry.
         *
         * @param array<string, mixed> $record Log record.
         *
         * @since 0.1.0
         */
        do_action('wpa_log', $record);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function isLoggable(string $level): bool
    {
        return ( self::SEVERITY[$level] ?? 0 ) >= ( self::SEVERITY[$this->minimumLevel] ?? 0 );
    }

    /**
     * Interpolates context values into message placeholders.
     *
     * @param array<string, mixed> $context
     */
    private function interpolate(string $message, array $context): string
    {
        $replace = [];

        foreach ( $context as $key => $value ) {
            if ( is_null($value) || is_scalar($value) || ( is_object($value) && method_exists($value, '__toString') ) ) {
                $replace['{' . $key . '}'] = (string) $value;
            }
        }

        return strtr($message, $replace);
    }
}


/**
 * Contract for log handlers.
 *
 * @package WpAgent\Logger
 * @since   0.1.0
 */
interface LogHandlerInterface
{
    /**
     * @param array{level: string, message: string, context: array<string, mixed>, time: string} $record
     */
    public function handle(array $record): void;
}
