<?php

declare(strict_types=1);

namespace WpAgent\Tools\WooCommerce;

use WpAgent\Auth\Identity;
use WpAgent\MCP\Protocol\ToolResult;
use WpAgent\Services\WooCommerceService;
use WpAgent\Tools\AbstractTool;
use WpAgent\Tools\Concerns\FormatsWoo;

/**
 * Tool: wordpress.woo.product.update
 *
 * Updates an existing WooCommerce product by ID.
 *
 * Required scope: wp-agent:write
 * Required capability: edit_products
 *
 * @package WpAgent\Tools\WooCommerce
 * @since   0.1.0
 */
final class UpdateProductTool extends AbstractTool
{
    public function __construct(
        private readonly WooCommerceService $woocommerceService,
    ) {}

    public function getName(): string
    {
        return 'wordpress.woo.product.update';
    }

    public function getDescription(): string
    {
        return 'Updates an existing WooCommerce product by ID. Only provided fields are updated.';
    }

    public function getInputSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'product_id'        => [
                    'type'        => 'integer',
                    'description' => 'The product ID.',
                    'minimum'     => 1,
                ],
                'name'              => ['type' => 'string'],
                'status'            => ['type' => 'string', 'enum' => ['draft', 'publish', 'private']],
                'description'       => ['type' => 'string'],
                'short_description' => ['type' => 'string'],
                'sku'               => ['type' => 'string'],
                'regular_price'     => ['type' => 'number'],
                'sale_price'        => ['type' => 'number'],
                'manage_stock'      => ['type' => 'boolean'],
                'stock_quantity'    => ['type' => 'integer'],
                'category_ids'      => ['type' => 'array', 'items' => ['type' => 'integer']],
                'image_id'          => ['type' => 'integer'],
            ],
            'required'             => ['product_id'],
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

        $productId = (int) $args['product_id'];
        unset($args['product_id']);

        $product = $this->woocommerceService->updateProduct($productId, $args);

        return ToolResult::json(FormatsWoo::formatProduct($product));
    }
}
