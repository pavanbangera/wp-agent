<?php

declare(strict_types=1);

namespace WpAgent\Tools\AI;

use WpAgent\Auth\Identity;
use WpAgent\Exceptions\ToolException;
use WpAgent\MCP\Protocol\ToolResult;
use WpAgent\Tools\AbstractTool;

/**
 * Tool: wordpress.file.write
 *
 * Writes a file to the WordPress filesystem (within wp-content/). Always reports
 * the exact number of bytes written so callers can verify the write was complete.
 *
 * Security: Path traversal is blocked — the resolved path must stay within WP_CONTENT_DIR.
 * Symlinks are resolved before the check so traversal via symlink is also blocked.
 *
 * Required scope: wp-agent:admin
 * Required capability: manage_options
 *
 * @package WpAgent\Tools\AI
 * @since   0.1.0
 */
final class FileWriteTool extends AbstractTool
{
    private const TOOL_NAME = 'wordpress.file.write';

    public function getName(): string
    {
        return self::TOOL_NAME;
    }

    public function getDescription(): string
    {
        return 'Writes a file to the WordPress filesystem inside wp-content/. '
            . 'The path must be relative to wp-content/ (e.g. "themes/my-theme/functions.php"). '
            . 'Always returns bytes_written as an integer — a null value means the write failed. '
            . 'Content can be a plain UTF-8 string or base64-encoded binary data. '
            . 'Parent directories are created automatically if they do not exist.';
    }

    public function getInputSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'path' => [
                    'type'        => 'string',
                    'description' => 'File path relative to wp-content/ (e.g. "themes/my-theme/style.css").',
                    'minLength'   => 1,
                    'maxLength'   => 512,
                ],
                'content' => [
                    'type'        => 'string',
                    'description' => 'File content to write. Use encoding="base64" for binary files.',
                    'minLength'   => 0,
                ],
                'encoding' => [
                    'type'        => 'string',
                    'enum'        => ['utf8', 'base64'],
                    'description' => 'Content encoding: "utf8" (default) for text files, "base64" for binary.',
                    'default'     => 'utf8',
                ],
                'append' => [
                    'type'        => 'boolean',
                    'description' => 'If true, append to an existing file instead of overwriting. Default: false.',
                    'default'     => false,
                ],
            ],
            'required'             => ['path', 'content'],
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
            'readOnlyHint'   => false,
            'destructiveHint' => true,
            'idempotentHint' => false,
        ];
    }

    protected function handle(array $args, Identity $identity): ToolResult
    {
        $this->requireCapability('manage_options', $identity);

        $relativePath = ltrim($args['path'], '/\\');
        $encoding     = $args['encoding'] ?? 'utf8';
        $append       = (bool) ($args['append'] ?? false);
        $rawContent   = $args['content'];

        // Resolve absolute path securely.
        $absPath = $this->resolveSecurePath($relativePath);

        // Decode content if base64.
        if ( $encoding === 'base64' ) {
            $decoded = base64_decode($rawContent, true);
            if ( false === $decoded ) {
                return ToolResult::error('Invalid base64 content payload. Ensure the file_content is a valid base64-encoded string.');
            }
            $content = $decoded;
        } else {
            $content = $rawContent;
        }

        // Create parent directories if needed.
        $dir = dirname($absPath);
        if ( ! is_dir($dir) ) {
            if ( ! wp_mkdir_p($dir) ) {
                return ToolResult::error("Failed to create directory: {$dir}");
            }
        }

        // Write the file — always capture the exact byte count.
        $flags        = $append ? FILE_APPEND | LOCK_EX : LOCK_EX;
        $bytesWritten = file_put_contents($absPath, $content, $flags);

        if ( false === $bytesWritten ) {
            return ToolResult::error("Failed to write file at: {$relativePath}. Check file permissions.");
        }

        // Build a public URL if the path is within the uploads or themes directory.
        $url = $this->buildUrl($absPath);

        return ToolResult::json([
            'success'       => true,
            'path'          => $relativePath,
            'absolute_path' => $absPath,
            'bytes_written' => $bytesWritten,
            'append_mode'   => $append,
            'url'           => $url,
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

        // Resolve the directory part only (file itself may not exist yet).
        $dir     = dirname($absPath);
        $realDir = realpath($dir);

        // If directory doesn't exist, check the closest ancestor.
        if ( false === $realDir ) {
            $parts = explode(DIRECTORY_SEPARATOR, $dir);
            while ( ! empty($parts) ) {
                $candidate = implode(DIRECTORY_SEPARATOR, $parts);
                $real      = realpath($candidate);
                if ( false !== $real ) {
                    // Verify this ancestor is within WP_CONTENT_DIR.
                    if ( ! str_starts_with($real . DIRECTORY_SEPARATOR, $contentDir . DIRECTORY_SEPARATOR) ) {
                        throw new ToolException(
                            "Path traversal detected: '{$relativePath}' resolves outside wp-content/.",
                            self::TOOL_NAME,
                            ToolException::FILE_PATH_TRAVERSAL
                        );
                    }
                    break;
                }
                array_pop($parts);
            }

            return $absPath;
        }

        // Verify the resolved directory is inside WP_CONTENT_DIR.
        $realContentDir = realpath($contentDir) ?: $contentDir;
        if ( ! str_starts_with($realDir . DIRECTORY_SEPARATOR, $realContentDir . DIRECTORY_SEPARATOR) ) {
            throw new ToolException(
                "Path traversal detected: '{$relativePath}' resolves outside wp-content/.",
                self::TOOL_NAME,
                ToolException::FILE_PATH_TRAVERSAL
            );
        }

        return $realDir . DIRECTORY_SEPARATOR . basename($absPath);
    }

    /**
     * Attempts to build a public URL for the written file.
     */
    private function buildUrl(string $absPath): string
    {
        $contentDir = rtrim(WP_CONTENT_DIR, '/\\');
        $contentUrl = rtrim(WP_CONTENT_URL, '/');

        if ( str_starts_with($absPath, $contentDir) ) {
            $relative = substr($absPath, strlen($contentDir));
            return $contentUrl . str_replace(DIRECTORY_SEPARATOR, '/', $relative);
        }

        return '';
    }
}
