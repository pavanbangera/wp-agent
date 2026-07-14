<?php

declare(strict_types=1);

namespace WpAgent\Tools\Security;

use WpAgent\Auth\Identity;
use WpAgent\MCP\Protocol\ToolResult;
use WpAgent\Services\SecurityService;
use WpAgent\Tools\AbstractTool;

/**
 * Tool: wordpress.security.permissions.audit
 *
 * Audits critical file system directories permission octals.
 *
 * Required scope: wp-agent:read
 * Required capability: manage_options
 *
 * @package WpAgent\Tools\Security
 * @since   0.1.0
 */
final class AuditPermissionsTool extends AbstractTool
{
    public function __construct(
        private readonly SecurityService $securityService,
    ) {}

    public function getName(): string
    {
        return 'wordpress.security.permissions.audit';
    }

    public function getDescription(): string
    {
        return 'Audits permissions modes of critical files (wp-config.php, uploads, plugins directories).';
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
        return ['wp-agent:read'];
    }

    public function getAnnotations(): array
    {
        return ['readOnlyHint' => true, 'idempotentHint' => true];
    }

    protected function handle(array $args, Identity $identity): ToolResult
    {
        $this->requireCapability('manage_options', $identity);

        $audit = $this->securityService->auditPermissions();

        return ToolResult::json([
            'success' => true,
            'audit'   => $audit,
        ]);
    }
}
