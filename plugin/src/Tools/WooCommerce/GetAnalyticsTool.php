<?php

declare(strict_types=1);

namespace WpAgent\Tools\WooCommerce;

use WpAgent\Auth\Identity;
use WpAgent\MCP\Protocol\ToolResult;
use WpAgent\Services\WooCommerceService;
use WpAgent\Tools\AbstractTool;

/**
 * Tool: wordpress.woo.analytics.get
 *
 * Fetches basic WooCommerce analytics report metrics.
 *
 * Required scope: wp-agent:read
 * Required capability: view_woocommerce_reports (or standard admin capability)
 *
 * @package WpAgent\Tools\WooCommerce
 * @since   0.1.0
 */
final class GetAnalyticsTool extends AbstractTool
{
    public function __construct(
        private readonly WooCommerceService $woocommerceService,
    ) {}

    public function getName(): string
    {
        return 'wordpress.woo.analytics.get';
    }

    public function getDescription(): string
    {
        return 'Retrieves key WooCommerce store performance metrics (total revenue, completed order counts, average order sizes).';
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

        $analytics = $this->woocommerceService->getAnalytics();

        return ToolResult::json(array_merge(
            ['success' => true],
            $analytics
        ));
    }
}
