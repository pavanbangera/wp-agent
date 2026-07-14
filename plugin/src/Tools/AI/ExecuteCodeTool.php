<?php

declare(strict_types=1);

namespace WpAgent\Tools\AI;

use WpAgent\Auth\Identity;
use WpAgent\MCP\Protocol\ToolResult;
use WpAgent\Services\CodeExecutionService;
use WpAgent\Tools\AbstractTool;

/**
 * Tool: wordpress.code.execute
 *
 * Runs custom sandboxed PHP code inside WordPress context.
 *
 * Required scope: wp-agent:admin
 * Required capability: manage_options (requires admin permissions)
 *
 * @package WpAgent\Tools\AI
 * @since   0.1.0
 */
final class ExecuteCodeTool extends AbstractTool
{
    public function __construct(
        private readonly CodeExecutionService $codeExecutionService,
    ) {}

    public function getName(): string
    {
        return 'wordpress.code.execute';
    }

    public function getDescription(): string
    {
        return 'Executes custom PHP code snippets within a sandboxed context and catches output/errors.';
    }

    public function getInputSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'code' => [
                    'type'        => 'string',
                    'description' => 'The raw PHP code to execute (without opening/closing php tags).',
                    'minLength'   => 1,
                ],
            ],
            'required'             => ['code'],
            'additionalProperties' => false,
        ];
    }

    public function getRequiredScopes(): array
    {
        return ['wp-agent:admin'];
    }

    protected function handle(array $args, Identity $identity): ToolResult
    {
        // Enforce administrative checks before executing arbitrary code.
        $this->requireCapability('manage_options', $identity);

        $result = $this->codeExecutionService->executePhp($args['code']);

        return ToolResult::json(array_merge(
            ['success' => true],
            $result
        ));
    }
}
