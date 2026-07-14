<?php

declare(strict_types=1);

namespace WpAgent\Tools\WooCommerce;

use WpAgent\Auth\Identity;
use WpAgent\MCP\Protocol\ToolResult;
use WpAgent\Services\WooCommerceService;
use WpAgent\Tools\AbstractTool;
use WpAgent\Tools\Concerns\FormatsWoo;

/**
 * Tool: wordpress.woo.product.create
 *
 * Creates a new WooCommerce product.
 *
 * Required scope: wp-agent:write
 * Required capability: edit_products
 *
 * @package WpAgent\Tools\WooCommerce
 * @since   0.1.0
 */
final class CreateProductTool extends AbstractTool
{
    public function __construct(
        private readonly WooCommerceService $woocommerceService,
    ) {}

    public function getName(): string
    {
        return 'wordpress.woo.product.create';
    }

    public function getDescription(): string
    {
        return 'Creates a new WooCommerce product (simple or variable). '
            . 'Allows configuring SKU, prices, stock, descriptions, and category taxonomy IDs.';
    }

    public function getInputSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'name'              => [
                    'type'        => 'string',
                    'description' => 'The product name.',
                    'minLength'   => 1,
                ],
                'type'              => [
                    'type'        => 'string',
                    'enum'        => ['simple', 'variable'],
                    'default'     => 'simple',
                ],
                'status'            => [
                    'type'        => 'string',
                    'enum'        => ['draft', 'publish', 'private'],
                    'default'     => 'draft',
                ],
                'description'       => [
                    'type'        => 'string',
                    'description' => 'Product long description.',
                    'default'     => '',
                ],
                'short_description' => [
                    'type'        => 'string',
                    'description' => 'Product short description.',
                    'default'     => '',
                ],
                'sku'               => [
                    'type'        => 'string',
                    'description' => 'Unique product SKU identifier.',
                ],
                'regular_price'     => [
                    'type'        => 'number',
                    'description' => 'Product regular list price.',
                ],
                'sale_price'        => [
                    'type'        => 'number',
                    'description' => 'Product sale/discount price.',
                ],
                'manage_stock'      => [
                    'type'        => 'boolean',
                    'description' => 'Enable inventory stock management.',
                    'default'     => false,
                ],
                'stock_quantity'    => [
                    'type'        => 'integer',
                    'description' => 'Inventory stock levels.',
                ],
                'category_ids'      => [
                    'type'        => 'array',
                    'description' => 'Product category term IDs.',
                    'items'       => ['type' => 'integer'],
                ],
                'image_id'          => [
                    'type'        => 'integer',
                    'description' => 'Main featured image attachment ID.',
                ],
            ],
            'required'             => ['name'],
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

        $product = $this->woocommerceService->createProduct($args);

        return ToolResult::json(FormatsWoo::formatProduct($product));
    }
}
