<?php

declare(strict_types=1);

namespace WpAgent\Tools\WooCommerce;

use WpAgent\Auth\Identity;
use WpAgent\MCP\Protocol\ToolResult;
use WpAgent\Services\WooCommerceService;
use WpAgent\Tools\AbstractTool;

/**
 * Tool: wordpress.woo.inventory.manage
 *
 * Updates product stock quantity and stock status.
 *
 * Required scope: wp-agent:write
 * Required capability: edit_products
 *
 * @package WpAgent\Tools\WooCommerce
 * @since   0.1.0
 */
final class ManageInventoryTool extends AbstractTool
{
    public function __construct(
        private readonly WooCommerceService $woocommerceService,
    ) {}

    public function getName(): string
    {
        return 'wordpress.woo.inventory.manage';
    }

    public function getDescription(): string
    {
        return 'Updates the stock level and inventory status of a WooCommerce product by ID.';
    }

    public function getInputSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'product_id'     => [
                    'type'        => 'integer',
                    'description' => 'The product ID to update.',
                    'minimum'     => 1,
                ],
                'stock_quantity' => [
                    'type'        => 'integer',
                    'description' => 'Target inventory stock quantity.',
                    'minimum'     => 0,
                ],
                'stock_status'   => [
                    'type'        => 'string',
                    'enum'        => ['instock', 'outofstock', 'onbackorder'],
                    'default'     => 'instock',
                ],
            ],
            'required'             => ['product_id', 'stock_quantity'],
            'additionalProperties' => false,
        ];
    }

    public function getRequiredScopes(): array
    {
        return ['wp-agent:write'];
    }

    protected function handle(array $args, Identity $identity): ToolResult
    {
        $this->requireCapability('edit_products', $identity);

        $productId     = (int) $args['product_id'];
        $stockQuantity = (int) $args['stock_quantity'];
        $stockStatus   = $args['stock_status'] ?? 'instock';

        $this->woocommerceService->manageInventory($productId, $stockQuantity, $stockStatus);

        return ToolResult::json([
            'success'        => true,
            'product_id'     => $productId,
            'stock_quantity' => $stockQuantity,
            'stock_status'   => $stockStatus,
            'message'        => "Inventory stock for product #{$productId} set to {$stockQuantity} ({$stockStatus}).",
        ]);
    }
}
