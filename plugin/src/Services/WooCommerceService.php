<?php

declare(strict_types=1);

namespace WpAgent\Services;

use WpAgent\Exceptions\ToolException;

/**
 * WooCommerce store integration service.
 *
 * Interacts with WooCommerce CRUD data stores (products, orders, coupons, settings).
 *
 * @package WpAgent\Services
 * @since   0.1.0
 */
final class WooCommerceService
{
    private const TOOL_NAME = 'woocommerce_service';

    /**
     * Asserts that WooCommerce is installed and active.
     *
     * @throws ToolException
     */
    public function requireWooCommerce(): void
    {
        if ( ! class_exists('WooCommerce') ) {
            throw new ToolException(
                'WooCommerce plugin is not active. Please install and activate WooCommerce first.',
                self::TOOL_NAME,
                ToolException::OPERATION_FAILED
            );
        }
    }

    /**
     * Creates a new WooCommerce product.
     *
     * @param array<string, mixed> $data Product configuration parameters.
     *
     * @return \WC_Product
     *
     * @throws ToolException
     */
    public function createProduct(array $data): \WC_Product
    {
        $this->requireWooCommerce();

        $type = $data['type'] ?? 'simple';

        if ( $type === 'variable' ) {
            $product = new \WC_Product_Variable();
        } else {
            $product = new \WC_Product_Simple();
        }

        $product->set_name(sanitize_text_field($data['name']));
        $product->set_status($data['status'] ?? 'draft');
        $product->set_description(wp_kses_post($data['description'] ?? ''));
        $product->set_short_description(wp_kses_post($data['short_description'] ?? ''));

        if ( ! empty($data['sku']) ) {
            $product->set_sku(sanitize_text_field($data['sku']));
        }

        if ( isset($data['regular_price']) ) {
            $product->set_regular_price((string) $data['regular_price']);
        }

        if ( isset($data['sale_price']) ) {
            $product->set_sale_price((string) $data['sale_price']);
        }

        if ( isset($data['manage_stock']) ) {
            $product->set_manage_stock((bool) $data['manage_stock']);
            if ( isset($data['stock_quantity']) ) {
                $product->set_stock_quantity((int) $data['stock_quantity']);
            }
        }

        if ( ! empty($data['category_ids']) ) {
            $product->set_category_ids(array_map('intval', (array) $data['category_ids']));
        }

        if ( ! empty($data['image_id']) ) {
            $product->set_image_id((int) $data['image_id']);
        }

        $productId = $product->save();

        if ( 0 === $productId ) {
            throw new ToolException('Failed to create WooCommerce product.', self::TOOL_NAME);
        }

        do_action('wpa_woocommerce_product_created', $product);

        return $product;
    }

    /**
     * Updates an existing WooCommerce product.
     *
     * @throws ToolException
     */
    public function updateProduct(int $productId, array $data): \WC_Product
    {
        $this->requireWooCommerce();

        $product = wc_get_product($productId);
        if ( ! $product ) {
            throw ToolException::notFound(self::TOOL_NAME, 'Product', $productId);
        }

        if ( isset($data['name']) ) {
            $product->set_name(sanitize_text_field($data['name']));
        }

        if ( isset($data['status']) ) {
            $product->set_status($data['status']);
        }

        if ( isset($data['description']) ) {
            $product->set_description(wp_kses_post($data['description']));
        }

        if ( isset($data['short_description']) ) {
            $product->set_short_description(wp_kses_post($data['short_description']));
        }

        if ( isset($data['sku']) ) {
            $product->set_sku(sanitize_text_field($data['sku']));
        }

        if ( isset($data['regular_price']) ) {
            $product->set_regular_price((string) $data['regular_price']);
        }

        if ( isset($data['sale_price']) ) {
            $product->set_sale_price((string) $data['sale_price']);
        }

        if ( isset($data['manage_stock']) ) {
            $product->set_manage_stock((bool) $data['manage_stock']);
        }

        if ( isset($data['stock_quantity']) ) {
            $product->set_stock_quantity((int) $data['stock_quantity']);
        }

        if ( isset($data['category_ids']) ) {
            $product->set_category_ids(array_map('intval', (array) $data['category_ids']));
        }

        if ( isset($data['image_id']) ) {
            $product->set_image_id((int) $data['image_id']);
        }

        $product->save();

        do_action('wpa_woocommerce_product_updated', $product);

        return $product;
    }

    /**
     * Lists products matching filters.
     *
     * @return array{products: \WC_Product[], total: int, pages: int}
     */
    public function listProducts(array $filters): array
    {
        $this->requireWooCommerce();

        $args = [
            'status'   => $filters['status'] ?? 'any',
            'limit'    => isset($filters['per_page']) ? min((int) $filters['per_page'], 100) : 20,
            'page'     => max((int) ($filters['page'] ?? 1), 1),
            'paginate' => true,
        ];

        if ( ! empty($filters['category']) ) {
            $args['category'] = [sanitize_text_field($filters['category'])];
        }

        if ( ! empty($filters['search']) ) {
            $args['s'] = sanitize_text_field($filters['search']);
        }

        $results = wc_get_products($args);

        return [
            'products' => $results->products ?? [],
            'total'    => (int) ($results->total ?? 0),
            'pages'    => (int) ($results->max_num_pages ?? 1),
        ];
    }

    /**
     * Lists WooCommerce orders.
     *
     * @return array{orders: \WC_Order[], total: int, pages: int}
     */
    public function listOrders(array $filters): array
    {
        $this->requireWooCommerce();

        $args = [
            'status'   => $filters['status'] ?? 'any',
            'limit'    => isset($filters['per_page']) ? min((int) $filters['per_page'], 100) : 20,
            'page'     => max((int) ($filters['page'] ?? 1), 1),
            'paginate' => true,
        ];

        if ( ! empty($filters['customer_id']) ) {
            $args['customer_id'] = (int) $filters['customer_id'];
        }

        $results = wc_get_orders($args);

        return [
            'orders' => $results->orders ?? [],
            'total'  => (int) ($results->total ?? 0),
            'pages'  => (int) ($results->max_num_pages ?? 1),
        ];
    }

    /**
     * Creates a WooCommerce discount coupon.
     *
     * @throws ToolException
     */
    public function createCoupon(array $data): \WC_Coupon
    {
        $this->requireWooCommerce();

        $code = sanitize_text_field($data['code']);

        $coupon = new \WC_Coupon();
        $coupon->set_code($code);
        $coupon->set_amount((string) ($data['amount'] ?? 0));
        $coupon->set_discount_type($data['discount_type'] ?? 'fixed_cart');

        if ( ! empty($data['expiry_date']) ) {
            $coupon->set_date_expires(strtotime($data['expiry_date']));
        }

        if ( isset($data['usage_limit']) ) {
            $coupon->set_usage_limit((int) $data['usage_limit']);
        }

        $couponId = $coupon->save();

        if ( 0 === $couponId ) {
            throw new ToolException("Failed to create WooCommerce coupon '{$code}'.", self::TOOL_NAME);
        }

        do_action('wpa_woocommerce_coupon_created', $coupon);

        return $coupon;
    }

    /**
     * Safely updates product inventory levels.
     *
     * @throws ToolException
     */
    public function manageInventory(int $productId, int $stockQuantity, string $stockStatus = 'instock'): bool
    {
        $this->requireWooCommerce();

        $product = wc_get_product($productId);
        if ( ! $product ) {
            throw ToolException::notFound(self::TOOL_NAME, 'Product', $productId);
        }

        $product->set_manage_stock(true);
        $product->set_stock_quantity($stockQuantity);
        $product->set_stock_status($stockStatus);
        $product->save();

        do_action('wpa_woocommerce_inventory_updated', $product, $stockQuantity, $stockStatus);

        return true;
    }

    /**
     * Registers a custom tax rate.
     *
     * @throws ToolException
     */
    public function configureTax(array $data): int
    {
        $this->requireWooCommerce();

        $rateData = [
            'tax_rate_country'  => sanitize_text_field($data['country'] ?? ''),
            'tax_rate_state'    => sanitize_text_field($data['state'] ?? ''),
            'tax_rate'          => (string) ($data['rate'] ?? 0.0),
            'tax_rate_name'     => sanitize_text_field($data['name'] ?? 'Tax'),
            'tax_rate_priority' => (int) ($data['priority'] ?? 1),
            'tax_rate_compound' => (int) ($data['compound'] ?? 0),
            'tax_rate_shipping' => (int) ($data['shipping'] ?? 1),
            'tax_rate_class'    => sanitize_text_field($data['class'] ?? ''),
        ];

        $rateId = \WC_Tax::_insert_tax_rate($rateData);

        if ( is_wp_error($rateId) || 0 === $rateId ) {
            throw new ToolException('Failed to configure tax rules rate.', self::TOOL_NAME);
        }

        return $rateId;
    }

    /**
     * Computes quick store sales analytics.
     *
     * @return array<string, mixed>
     */
    public function getAnalytics(): array
    {
        $this->requireWooCommerce();

        // Calculate total sales and orders from completed/processing orders.
        $orders = wc_get_orders([
            'status' => ['completed', 'processing'],
            'limit'  => -1,
        ]);

        $totalRevenue = 0.0;
        $orderCount   = count($orders);

        foreach ( $orders as $order ) {
            $totalRevenue += (float) $order->get_total();
        }

        return [
            'total_sales'   => $totalRevenue,
            'orders_count'  => $orderCount,
            'average_value' => $orderCount > 0 ? ($totalRevenue / $orderCount) : 0.0,
            'currency'      => get_woocommerce_currency(),
        ];
    }
}
