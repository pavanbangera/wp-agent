<?php

declare(strict_types=1);

namespace WpAgent\Tools\AI;

use WpAgent\Auth\Identity;
use WpAgent\MCP\Protocol\ToolResult;
use WpAgent\Services\AiPlannerService;
use WpAgent\Tools\AbstractTool;

/**
 * Tool: wordpress.ai.plan
 *
 * Sequence planner.
 *
 * Required scope: wp-agent:read
 * Required capability: read
 *
 * @package WpAgent\Tools\AI
 * @since   0.1.0
 */
final class PlanGoalTool extends AbstractTool
{
    public function __construct(
        private readonly AiPlannerService $plannerService,
    ) {}

    public function getName(): string
    {
        return 'wordpress.ai.plan';
    }

    public function getDescription(): string
    {
        return 'Generates a sequenced plan of specific WP Agent tool invocations to achieve complex goals.';
    }

    public function getInputSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'goal' => [
                    'type'        => 'string',
                    'description' => 'What you want to build or configure on this WordPress site (e.g. "Build a shop with landing page").',
                    'minLength'   => 1,
                ],
            ],
            'required'             => ['goal'],
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

        $plan = $this->plannerService->planGoal($args['goal']);

        return ToolResult::json($plan);
    }
}
