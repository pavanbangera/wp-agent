<?php

declare(strict_types=1);

namespace WpAgent\Tools\Security;

use WpAgent\Auth\Identity;
use WpAgent\Exceptions\ToolException;
use WpAgent\MCP\Protocol\ToolResult;
use WpAgent\Tools\AbstractTool;

/**
 * Tool: wordpress.security.backup.restore
 *
 * Restores tables from a custom SQL file.
 *
 * Required scope: wp-agent:admin
 * Required capability: manage_options
 *
 * @package WpAgent\Tools\Security
 * @since   0.1.0
 */
final class RestoreBackupTool extends AbstractTool
{
    public function getName(): string
    {
        return 'wordpress.security.backup.restore';
    }

    public function getDescription(): string
    {
        return 'Restores database tables options/posts from an existing local SQL backup dump.';
    }

    public function getInputSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'sql_file_path' => [
                    'type'        => 'string',
                    'description' => 'The absolute path to the local .sql backup file.',
                    'minLength'   => 1,
                ],
            ],
            'required'             => ['sql_file_path'],
            'additionalProperties' => false,
        ];
    }

    public function getRequiredScopes(): array
    {
        return ['wp-agent:admin'];
    }

    protected function handle(array $args, Identity $identity): ToolResult
    {
        $this->requireCapability('manage_options', $identity);

        $path = $args['sql_file_path'];

        if ( ! file_exists($path) ) {
            throw new ToolException("Backup SQL file not found at path: {$path}", self::TOOL_NAME, ToolException::RESOURCE_NOT_FOUND);
        }

        $sql = file_get_contents($path);
        if ( empty($sql) ) {
            throw new ToolException("Backup SQL file is empty.", self::TOOL_NAME);
        }

        // Execute table restores queries line-by-line safely.
        global $wpdb;
        $queries = explode(";\n", $sql);

        $executed = 0;
        foreach ( $queries as $query ) {
            $query = trim($query);
            if ( ! empty($query) && stripos($query, 'INSERT INTO') === 0 ) {
                $wpdb->query($query);
                $executed++;
            }
        }

        return ToolResult::json([
            'success'           => true,
            'queries_executed'  => $executed,
            'message'           => "Database states restored successfully from {$path}.",
        ]);
    }
}
