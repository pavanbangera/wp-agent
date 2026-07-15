<?php

declare(strict_types=1);

namespace WpAgent\Tools\AI;

use WpAgent\Auth\Identity;
use WpAgent\Exceptions\ToolException;
use WpAgent\MCP\Protocol\ToolResult;
use WpAgent\Tools\AbstractTool;

/**
 * Tool: wordpress.file.read
 *
 * Reads a file from the WordPress filesystem (within wp-content/). Supports optional
 * start_line / end_line parameters to efficiently read a slice of large files without
 * returning the entire content (which wastes tokens for large PHP files like functions.php).
 *
 * Required scope: wp-agent:admin
 * Required capability: manage_options
 *
 * @package WpAgent\Tools\AI
 * @since   0.1.0
 */
final class FileReadTool extends AbstractTool
{
    private const TOOL_NAME = 'wordpress.file.read';

    public function getName(): string
    {
        return self::TOOL_NAME;
    }

    public function getDescription(): string
    {
        return 'Reads a file from within the WordPress wp-content/ directory. '
            . 'The path must be relative to wp-content/ (e.g. "themes/my-theme/functions.php"). '
            . 'Use start_line and end_line to read only a portion of large files — '
            . 'lines are 1-indexed and inclusive. Omit both to read the entire file. '
            . 'Returns the raw file content as a UTF-8 string, plus total line count and byte size.';
    }

    public function getInputSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'path' => [
                    'type'        => 'string',
                    'description' => 'File path relative to wp-content/ (e.g. "themes/my-theme/functions.php").',
                    'minLength'   => 1,
                    'maxLength'   => 512,
                ],
                'start_line' => [
                    'type'        => 'integer',
                    'description' => 'First line to return (1-indexed, inclusive). Defaults to 1.',
                    'minimum'     => 1,
                ],
                'end_line' => [
                    'type'        => 'integer',
                    'description' => 'Last line to return (1-indexed, inclusive). Defaults to last line of file.',
                    'minimum'     => 1,
                ],
            ],
            'required'             => ['path'],
            'additionalProperties' => false,
        ];
    }

    public function getRequiredScopes(): array
    {
        return ['wp-agent:admin'];
    }

    public function getAnnotations(): array
    {
        return [
            'readOnlyHint'   => true,
            'destructiveHint' => false,
            'idempotentHint' => true,
        ];
    }

    protected function handle(array $args, Identity $identity): ToolResult
    {
        $this->requireCapability('manage_options', $identity);

        $relativePath = ltrim($args['path'], '/\\');

        // Resolve and validate path security.
        $absPath = $this->resolveSecurePath($relativePath);

        if ( ! file_exists($absPath) ) {
            return ToolResult::error("File not found: wp-content/{$relativePath}");
        }

        if ( ! is_readable($absPath) ) {
            return ToolResult::error("File is not readable: wp-content/{$relativePath}");
        }

        $raw = file_get_contents($absPath);

        if ( false === $raw ) {
            return ToolResult::error("Failed to read file: wp-content/{$relativePath}");
        }

        $lines      = explode("\n", $raw);
        $totalLines = count($lines);
        $startLine  = isset($args['start_line']) ? max(1, (int) $args['start_line']) : 1;
        $endLine    = isset($args['end_line'])   ? min($totalLines, (int) $args['end_line']) : $totalLines;

        if ( $startLine > $endLine ) {
            return ToolResult::error("start_line ({$startLine}) must be less than or equal to end_line ({$endLine}).");
        }

        // Slice the requested lines (lines array is 0-indexed).
        $slicedLines   = array_slice($lines, $startLine - 1, $endLine - $startLine + 1);
        $content       = implode("\n", $slicedLines);

        return ToolResult::json([
            'success'      => true,
            'path'         => $relativePath,
            'content'      => $content,
            'start_line'   => $startLine,
            'end_line'     => $endLine,
            'total_lines'  => $totalLines,
            'bytes_read'   => strlen($content),
            'total_bytes'  => strlen($raw),
            'is_truncated' => ($startLine > 1 || $endLine < $totalLines),
        ]);
    }

    /**
     * Resolves and validates that the target path stays within WP_CONTENT_DIR.
     *
     * @throws ToolException on path traversal attempt.
     */
    private function resolveSecurePath(string $relativePath): string
    {
        $contentDir = rtrim(WP_CONTENT_DIR, '/\\');
        $absPath    = $contentDir . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath);
        $realPath   = realpath($absPath);

        if ( false === $realPath ) {
            // File may not exist yet — validate the directory.
            $realDir        = realpath(dirname($absPath));
            $realContentDir = realpath($contentDir) ?: $contentDir;

            if ( $realDir !== false && ! str_starts_with($realDir . DIRECTORY_SEPARATOR, $realContentDir . DIRECTORY_SEPARATOR) ) {
                throw new ToolException(
                    "Path traversal detected: '{$relativePath}' resolves outside wp-content/.",
                    self::TOOL_NAME,
                    ToolException::FILE_PATH_TRAVERSAL
                );
            }

            return $absPath;
        }

        $realContentDir = realpath($contentDir) ?: $contentDir;
        if ( ! str_starts_with($realPath, $realContentDir . DIRECTORY_SEPARATOR) ) {
            throw new ToolException(
                "Path traversal detected: '{$relativePath}' resolves outside wp-content/.",
                self::TOOL_NAME,
                ToolException::FILE_PATH_TRAVERSAL
            );
        }

        return $realPath;
    }
}
