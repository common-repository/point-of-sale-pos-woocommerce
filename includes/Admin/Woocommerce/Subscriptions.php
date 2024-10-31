<?php

namespace ZPOS\Admin\Woocommerce;

use WC_Order;
use WC_Product;
use WC_Tax;
use WP_REST_Request;
use WP_Error;
use WP_REST_Response;
use WC_REST_Subscriptions_Controller;
use WC_Subscriptions_Product;
use ZPOS\API\Taxes;
use ZPOS\Station;
use ZPOS\Structure\EmptyEntity;

class Subscriptions
{
	/**
	 * @return bool|array|WP_Error
	 */
	public static function create_from_order(WC_Order $order, WP_REST_Request $request)
	{
		if (
			!static::is_plugin_active() ||
			!static::is_order_contains_subscription_products($order) ||
			wcs_order_contains_subscription($order, 'any')
		) {
			return false;
		}

		return (new WC_REST_Subscriptions_Controller())->create_subscriptions_from_order($request);
	}

	public static function prepare_subscription_product(
		WP_REST_Response $response,
		WC_Product $product,
		$request
	): WP_REST_Response {
		if (!static::is_plugin_active()) {
			return $response;
		}

		$product_id = $response->data['id'];
		$product = wc_get_product($product_id);

		if (!WC_Subscriptions_Product::is_subscription($product)) {
			return $response;
		}

		$station_id = $request->get_header('X-POS');
		$station = is_numeric($station_id) ? new Station($station_id) : false;
		$tax_calculation =
			$station instanceof Station
				? ('yes' === $station->getData('pos_display_prices_include_tax_in_shop')
					? 'incl'
					: 'excl')
				: get_option('woocommerce_tax_display_shop');
		$empty_entity = new EmptyEntity();
		$sign_up_fee = (float) WC_Subscriptions_Product::get_sign_up_fee($product);
		$sign_up_fee_without_taxes = $sign_up_fee;

		if ('incl' === $tax_calculation) {
			$tax_rates = Taxes::get_current_taxes_rates(
				$empty_entity,
				$product->get_tax_class(),
				$station_id
			);
			$taxes = WC_Tax::calc_tax($sign_up_fee, $tax_rates);
			$sign_up_fee += array_reduce(
				$taxes,
				function (float $result, float $tax): float {
					return $result + $tax;
				},
				0.0
			);
		}

		$response->data['subscription'] = [
			'sign_up_fee' => $sign_up_fee,
			'sign_up_fee_without_taxes' => $sign_up_fee_without_taxes,
			'trial_length' => WC_Subscriptions_Product::get_trial_length($product),
			'period_string' => WC_Subscriptions_Product::get_price_string($product, [
				'subscription_price' => false,
				'subscription_length' => false,
				'sign_up_fee' => false,
				'trial_length' => false,
			]),
			'additional_string' => WC_Subscriptions_Product::get_price_string($product, [
				'subscription_price' => false,
				'subscription_period' => false,
				'sign_up_fee' => $sign_up_fee,
			]),
			'$station' => $station,
		];

		return $response;
	}

	public static function add_sign_up_fee_to_line_items(array $order_items): void
	{
		if (!static::is_plugin_active()) {
			return;
		}

		foreach ($order_items as $order_item) {
			if ('line_item' !== $order_item->get_type()) {
				continue;
			}

			$product = $order_item->get_product();
			$qty = $order_item->get_quantity();
			$pos_qty = is_numeric($order_item->get_meta('_pos_qty'))
				? (int) $order_item->get_meta('_pos_qty')
				: 0;

			if ($qty === $pos_qty || !WC_Subscriptions_Product::is_subscription($product)) {
				continue;
			}

			$sign_up_fee = WC_Subscriptions_Product::get_sign_up_fee($product);
			$sign_up_fee = is_numeric($sign_up_fee) ? (float) $sign_up_fee : 0;

			if (0 === $sign_up_fee) {
				continue;
			}

			$trial_length = WC_Subscriptions_Product::get_trial_length($product);

			if (0 < $trial_length) {
				$price = $sign_up_fee;
				$pos_subtotal = (float) $sign_up_fee * $qty;
				$pos_total = (float) $sign_up_fee * $qty;
				$pos_price = $sign_up_fee;
			} else {
				$price = (float) $product->get_price() + $sign_up_fee;
				$pos_subtotal = (float) $order_item->get_meta('_pos_subtotal') + $sign_up_fee * $qty;
				$pos_total = (float) $order_item->get_meta('_pos_total') + $sign_up_fee * $qty;
				$pos_price = (float) $order_item->get_meta('_pos_price') + $sign_up_fee;
			}

			$total = wc_get_price_excluding_tax($product, ['qty' => $qty, 'price' => $price]);

			$order_item->set_total($total);
			$order_item->set_subtotal($total);
			$order_item->update_meta_data('_pos_subtotal', $pos_subtotal);
			$order_item->update_meta_data('_pos_total', $pos_total);
			$order_item->update_meta_data('_pos_price', $pos_price);
			$order_item->update_meta_data('_pos_qty', $qty);
		}
	}

	public static function is_order_contains_subscription_products(WC_Order $order): bool
	{
		foreach ($order->get_items() as $order_item) {
			if ('line_item' !== $order_item->get_type()) {
				continue;
			}

			$product = $order_item->get_product();

			if (!WC_Subscriptions_Product::is_subscription($product)) {
				continue;
			}

			return true;
		}

		return false;
	}

	protected static function is_plugin_active(): bool
	{
		return class_exists('\WC_Subscriptions_Product');
	}
}
