<?php

declare(strict_types=1);

namespace WpAgent\Tools\Security;

use WpAgent\Auth\Identity;
use WpAgent\MCP\Protocol\ToolResult;
use WpAgent\Services\SecurityService;
use WpAgent\Tools\AbstractTool;

/**
 * Tool: wordpress.security.headers.check
 *
 * Checks HTTP response headers configurations.
 *
 * Required scope: wp-agent:read
 * Required capability: read
 *
 * @package WpAgent\Tools\Security
 * @since   0.1.0
 */
final class CheckHeadersTool extends AbstractTool
{
    public function __construct(
        private readonly SecurityService $securityService,
    ) {}

    public function getName(): string
    {
        return 'wordpress.security.headers.check';
    }

    public function getDescription(): string
    {
        return 'Audits active HTTP response headers for missing web security mechanisms (CSP, X-Frame-Options, STS).';
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
        $this->requireCapability('read', $identity);

        $headers = $this->securityService->checkHeaders();

        return ToolResult::json([
            'success' => true,
            'headers' => $headers,
        ]);
    }
}
