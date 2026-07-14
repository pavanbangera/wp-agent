<?php

declare(strict_types=1);

namespace WpAgent\Tools\WooCommerce;

use WpAgent\Auth\Identity;
use WpAgent\MCP\Protocol\ToolResult;
use WpAgent\Services\WooCommerceService;
use WpAgent\Tools\AbstractTool;
use WpAgent\Tools\Concerns\FormatsWoo;

/**
 * Tool: wordpress.woo.orders.list
 *
 * Lists WooCommerce orders.
 *
 * Required scope: wp-agent:read
 * Required capability: edit_shop_orders (or standard admin capability)
 *
 * @package WpAgent\Tools\WooCommerce
 * @since   0.1.0
 */
final class ListOrdersTool extends AbstractTool
{
    public function __construct(
        private readonly WooCommerceService $woocommerceService,
    ) {}

    public function getName(): string
    {
        return 'wordpress.woo.orders.list';
    }

    public function getDescription(): string
    {
        return 'Lists orders placed in the WooCommerce store with status filtering.';
    }

    public function getInputSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'status'      => [
                    'type'        => 'string',
                    'description' => 'Order status (e.g. "processing", "completed", "pending").',
                    'default'     => 'any',
                ],
                'customer_id' => [
                    'type'        => 'integer',
                    'description' => 'Filter by customer user ID.',
                    'minimum'     => 1,
                ],
                'page'        => [
                    'type'        => 'integer',
                    'minimum'     => 1,
                    'default'     => 1,
                ],
                'per_page'    => [
                    'type'        => 'integer',
                    'minimum'     => 1,
                    'maximum'     => 100,
                    'default'     => 20,
                ],
            ],
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

        $results = $this->woocommerceService->listOrders($args);

        return ToolResult::json([
            'orders'       => array_map(
                static fn (\WC_Order $o): array => FormatsWoo::formatOrder($o),
                $results['orders']
            ),
            'total'        => $results['total'],
            'total_pages'  => $results['pages'],
            'per_page'     => $args['per_page'] ?? 20,
            'current_page' => $args['page'] ?? 1,
        ]);
    }
}
