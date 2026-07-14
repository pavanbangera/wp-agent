<?php

declare(strict_types=1);

namespace WpAgent\Services;

use WpAgent\Exceptions\ToolException;

/**
 * Sandboxed PHP code execution service.
 *
 * Runs scripts inside a controlled output buffer trap.
 *
 * @package WpAgent\Services
 * @since   0.1.0
 */
final class CodeExecutionService
{
    private const TOOL_NAME = 'code_execution_service';

    /**
     * Executes arbitrary PHP code and returns output and returned structures.
     *
     * @return array{output: string, returned: mixed}
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

        ob_start();
        $returned = null;

        try {
            // Evaluate PHP.
            $returned = eval($code);
        } catch ( \Throwable $e ) {
            ob_end_clean();
            throw new ToolException("Execution Error: " . $e->getMessage(), self::TOOL_NAME, ToolException::OPERATION_FAILED, $e);
        }

        $output = ob_get_clean();

        return [
            'output'   => $output ?: '',
            'returned' => $returned,
        ];
    }
}
