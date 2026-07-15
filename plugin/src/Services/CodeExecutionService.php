<?php

declare(strict_types=1);

namespace WpAgent\Services;

use WpAgent\Exceptions\ToolException;

/**
 * Sandboxed PHP code execution service.
 *
 * Runs scripts inside a controlled output buffer trap.
 * Captures PHP errors (warnings, notices, deprecated) via set_error_handler.
 *
 * @package WpAgent\Services
 * @since   0.1.0
 */
final class CodeExecutionService
{
    private const TOOL_NAME = 'code_execution_service';

    /**
     * Executes arbitrary PHP code and returns output, return value, and any PHP errors.
     *
     * @return array{output: string, returned: mixed, php_errors: array<int, array<string, mixed>>, had_errors: bool}
     *
     * @throws ToolException
     */
    public function executePhp(string $code): array
    {
        // Safety pre-checks (restrict dangerous commands if needed).
        $blacklist = ['system', 'exec', 'passthru', 'shell_exec', 'popen', 'proc_open'];
        foreach ( $blacklist as $func ) {
            if ( preg_match('/\b' . $func . '\b/i', $code) ) {
                throw new ToolException("Function '{$func}' is blacklisted in this execution context.", self::TOOL_NAME, ToolException::INVALID_PARAMS);
            }
        }

        // Clean opening php tags.
        $code = preg_replace('/^<\?(php)?/i', '', trim($code));

        // Collect PHP errors (warnings, notices, deprecated) without silencing them.
        /** @var array<int, array<string, mixed>> $phpErrors */
        $phpErrors = [];

        set_error_handler(
            static function (int $errno, string $errstr, string $errfile, int $errline) use (&$phpErrors): bool {
                $phpErrors[] = [
                    'type'    => self::phpErrorTypeName($errno),
                    'code'    => $errno,
                    'message' => $errstr,
                    'file'    => $errfile,
                    'line'    => $errline,
                ];

                // Return false to also let the default handler run (keeps logs intact).
                return false;
            }
        );

        ob_start();
        $returned = null;

        try {
            // Evaluate PHP. Errors caught by the custom handler above.
            $returned = eval($code); // phpcs:ignore Squiz.PHP.Eval
        } catch ( \Throwable $e ) {
            ob_end_clean();
            restore_error_handler();

            $phpErrors[] = [
                'type'    => get_class($e),
                'code'    => $e->getCode(),
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ];

            throw new ToolException(
                'Execution Error: ' . $e->getMessage(),
                self::TOOL_NAME,
                ToolException::OPERATION_FAILED,
                ['php_errors' => $phpErrors],
                $e
            );
        } finally {
            restore_error_handler();
        }

        $output = ob_get_clean();

        // Check if PHP itself recorded a fatal after execution.
        $lastError = error_get_last();
        if ( $lastError && in_array($lastError['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true) ) {
            $phpErrors[] = [
                'type'    => self::phpErrorTypeName($lastError['type']),
                'code'    => $lastError['type'],
                'message' => $lastError['message'],
                'file'    => $lastError['file'],
                'line'    => $lastError['line'],
            ];
        }

        return [
            'output'     => $output ?: '',
            'returned'   => $returned,
            'php_errors' => $phpErrors,
            'had_errors' => ! empty($phpErrors),
        ];
    }

    /**
     * Converts a PHP error bitmask integer to a human-readable type name.
     */
    private static function phpErrorTypeName(int $errno): string
    {
        return match ($errno) {
            E_ERROR             => 'E_ERROR',
            E_WARNING           => 'E_WARNING',
            E_PARSE             => 'E_PARSE',
            E_NOTICE            => 'E_NOTICE',
            E_CORE_ERROR        => 'E_CORE_ERROR',
            E_CORE_WARNING      => 'E_CORE_WARNING',
            E_COMPILE_ERROR     => 'E_COMPILE_ERROR',
            E_COMPILE_WARNING   => 'E_COMPILE_WARNING',
            E_USER_ERROR        => 'E_USER_ERROR',
            E_USER_WARNING      => 'E_USER_WARNING',
            E_USER_NOTICE       => 'E_USER_NOTICE',
            E_STRICT            => 'E_STRICT',
            E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
            E_DEPRECATED        => 'E_DEPRECATED',
            E_USER_DEPRECATED   => 'E_USER_DEPRECATED',
            default             => "UNKNOWN({$errno})",
        };
    }
}
