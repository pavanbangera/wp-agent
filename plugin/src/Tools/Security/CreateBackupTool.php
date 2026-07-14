<?php

declare(strict_types=1);

namespace WpAgent\Tools\Security;

use WpAgent\Auth\Identity;
use WpAgent\MCP\Protocol\ToolResult;
use WpAgent\Services\SecurityService;
use WpAgent\Tools\AbstractTool;

/**
 * Tool: wordpress.security.backup.create
 *
 * Creates a zip copy of files and custom SQL dumps.
 *
 * Required scope: wp-agent:admin
 * Required capability: manage_options
 *
 * @package WpAgent\Tools\Security
 * @since   0.1.0
 */
final class CreateBackupTool extends AbstractTool
{
    public function __construct(
        private readonly SecurityService $securityService,
    ) {}

    public function getName(): string
    {
        return 'wordpress.security.backup.create';
    }

    public function getDescription(): string
    {
        return 'Generates a backup archive containing database options/posts tables SQL and uploads folder.';
    }

    public function getInputSchema(): array
    {
        return [
            'type'                 => 'object',
            'properties'           => [],
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

        $backup = $this->securityService->createBackup();

        return ToolResult::json(array_merge(
            ['success' => true],
            $backup
        ));
    }
}
