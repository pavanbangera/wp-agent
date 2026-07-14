<?php

declare(strict_types=1);

namespace WpAgent\Tools\Concerns;

/**
 * Formats WooCommerce models into clean MCP structures.
 *
 * @package WpAgent\Tools\Concerns
 * @since   0.1.0
 */
final class FormatsWoo
{
    /**
     * Formats a WC_Product object.
     *
     * @return array<string, mixed>
     */
    public static function formatProduct(\WC_Product $product): array
    {
        return [
            'id'            => $product->get_id(),
            'name'          => $product->get_name(),
            'sku'           => $product->get_sku(),
            'type'          => $product->get_type(),
            'status'        => $product->get_status(),
            'regular_price' => $product->get_regular_price(),
            'sale_price'    => $product->get_sale_price(),
            'price'         => $product->get_price(),
            'manage_stock'  => $product->get_manage_stock(),
            'stock_quantity'=> $product->get_stock_quantity(),
            'stock_status'  => $product->get_stock_status(),
            'category_ids'  => $product->get_category_ids(),
            'image_id'      => $product->get_image_id(),
            'permalink'     => $product->get_permalink(),
        ];
    }

    /**
     * Formats a WC_Order object.
     *
     * @return array<string, mixed>
     */
    public static function formatOrder(\WC_Order $order): array
    {
        return [
            'id'             => $order->get_id(),
            'order_number'   => $order->get_order_number(),
            'status'         => $order->get_status(),
            'total'          => $order->get_total(),
            'currency'       => $order->get_currency(),
            'customer_id'    => $order->get_customer_id(),
            'billing_email'  => $order->get_billing_email(),
            'billing_phone'  => $order->get_billing_phone(),
            'payment_method' => $order->get_payment_method(),
            'date_created'   => $order->get_date_created() ? $order->get_date_created()->date('Y-m-d H:i:s') : '',
        ];
    }

    /**
     * Formats a WC_Coupon object.
     *
     * @return array<string, mixed>
     */
    public static function formatCoupon(\WC_Coupon $coupon): array
    {
        return [
            'id'            => $coupon->get_id(),
            'code'          => $coupon->get_code(),
            'amount'        => $coupon->get_amount(),
            'discount_type' => $coupon->get_discount_type(),
            'expiry_date'   => $coupon->get_date_expires() ? $coupon->get_date_expires()->date('Y-m-d') : '',
            'usage_limit'   => $coupon->get_usage_limit(),
        ];
    }
}
