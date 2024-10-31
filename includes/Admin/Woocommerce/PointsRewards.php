<?php

namespace ZPOS\Admin\Woocommerce;

use WC_Coupon;
use WC_Order;
use WC_Order_Item_Product;
use WC_Product;
use WC_Points_Rewards_Manager;
use WC_Points_Rewards_Product;
use WP_Error;

class PointsRewards
{
	public static function is_plugin_active(): bool
	{
		return class_exists('\WC_Points_Rewards');
	}

	public static function get_earned(WC_Order $order): int
	{
		$earned = 0;
		$prices_include_tax = wc_prices_include_tax();

		foreach ($order->get_items() as $item_key => $item) {
			if (!$item instanceof WC_Order_Item_Product) {
				continue;
			}

			$product = $item->get_product();

			if (!$product instanceof WC_Product) {
				continue;
			}

			$item_price = $order->get_item_subtotal($item, $prices_include_tax);

			$product->set_price($item_price);

			$earned +=
				apply_filters(
					'woocommerce_points_earned_for_order_item',
					WC_Points_Rewards_Product::get_points_earned_for_product_purchase(
						$product,
						$order,
						'edit'
					),
					$product,
					$item_key,
					$item,
					$order
				) * $item['qty'];
		}

		$discount = $order->get_total_discount($prices_include_tax);
		$earned -= min(WC_Points_Rewards_Manager::calculate_points($discount), $earned);
		$coupons = $order->get_coupon_codes();
		$earned = WC_Points_Rewards_Manager::calculate_points_modification_from_coupons(
			$earned,
			$coupons
		);
		$earned = WC_Points_Rewards_Manager::round_the_points($earned);

		return apply_filters('wc_points_rewards_points_earned_for_purchase', $earned, $order);
	}

	public static function get_balance(int $customer_id): int
	{
		if (0 === $customer_id) {
			return 0;
		}

		return WC_Points_Rewards_Manager::get_users_points($customer_id);
	}

	public static function get_balance_value(int $customer_id): float
	{
		if (0 === $customer_id) {
			return 0;
		}

		$points = floatval(WC_Points_Rewards_Manager::get_users_points_value($customer_id));

		return $points;
	}

	public static function get_min_points(): int
	{
		return WC_Points_Rewards_Manager::calculate_points_for_discount(
			floatval(get_option('wc_points_rewards_cart_min_discount', 0.0))
		);
	}

	/**
	 * @return bool|WP_Error
	 */
	public static function rollback_previous_redemption(WC_Order $order)
	{
		global $wc_points_rewards;

		$logged_redemption = $order->get_meta('_wc_points_logged_redemption');

		if (empty($logged_redemption)) {
			return false;
		}

		$discount_code = $logged_redemption['discount_code'] ?? '';
		$points = $logged_redemption['points'] ?? 0;

		if (!$order->remove_coupon($discount_code)) {
			return new WP_Error('unable_to_rollback', 'Error: Unable to rollback previous redemption.');
		}

		$coupon_id = wc_get_coupon_id_by_code($discount_code);

		if ($coupon_id) {
			wp_delete_post($coupon_id);
		}

		if (
			!WC_Points_Rewards_Manager::increase_points(
				$order->get_customer_id(),
				$points,
				'order-cancelled',
				null,
				$order->get_id()
			)
		) {
			return new WP_Error('unable_to_rollback', 'Error: Unable to rollback previous redemption.');
		}

		$order->delete_meta_data('_wc_points_redeemed');
		$order->delete_meta_data('_wc_points_logged_redemption');
		$order->save();
		$order->add_order_note(
			sprintf(
				__('%1$d %2$s were credited back as the discount was cancelled.', 'zpos-wp-api'),
				$points,
				$wc_points_rewards->get_points_label($points)
			)
		);

		return true;
	}

	/**
	 * @return array|WP_Error
	 */
	public static function redeem(WC_Order $order)
	{
		global $wc_points_rewards;

		$order_statuses = apply_filters('wc_points_rewards_redeem_points_order_statuses', [
			'processing',
			'completed',
		]);

		if (in_array($order->get_status(), $order_statuses)) {
			return new WP_Error('invalid_order_status', 'Error: Invalid order status.');
		}

		$balance_value = static::get_balance_value($order->get_customer_id());
		$points_to_redemption = static::get_points_to_redemption($order, $balance_value);

		if (0 >= $points_to_redemption) {
			return new WP_Error('invalid_points', 'Error: Invalid points.');
		}

		$customer_id = $order->get_customer_id();
		$current_balance = static::get_balance($customer_id);

		if ($points_to_redemption > $current_balance) {
			return new WP_Error('insufficient_points', 'Error: Insufficient points.');
		}

		$discount_code = sprintf(
			'points_redemption_%s_%s',
			$customer_id,
			date('Y_m_d_h_i', current_time('timestamp'))
		);
		$value_to_redemption = static::get_value_to_redemption($order, $balance_value);
		$coupon_id = wp_insert_post([
			'post_title' => $discount_code,
			'post_content' => '',
			'post_status' => 'publish',
			'post_author' => get_current_user_id(),
			'post_type' => 'shop_coupon',
		]);

		update_post_meta($coupon_id, 'discount_type', 'fixed_cart');
		update_post_meta($coupon_id, 'coupon_amount', $value_to_redemption);
		update_post_meta($coupon_id, 'individual_use', 'no');
		update_post_meta($coupon_id, 'product_ids', '');
		update_post_meta($coupon_id, 'exclude_product_ids', '');
		update_post_meta($coupon_id, 'usage_limit', '1');
		update_post_meta($coupon_id, 'expiry_date', '');
		update_post_meta($coupon_id, 'apply_before_tax', 'yes');
		update_post_meta($coupon_id, 'free_shipping', 'no');

		$coupon_result = $order->apply_coupon(new WC_Coupon($discount_code));

		if ($coupon_result instanceof WP_Error) {
			return $coupon_result;
		}

		if (
			!WC_Points_Rewards_Manager::decrease_points(
				$order->get_customer_id(),
				$points_to_redemption,
				'order-redeem',
				[
					'discount_code' => $discount_code,
					'discount_amount' => $value_to_redemption,
				],
				$order->get_id()
			)
		) {
			return new WP_Error('unable_to_redeem', 'Error: Unable to redeem points.');
		}

		$points_redeemed = intval($order->get_meta('_wc_points_redeemed')) + $points_to_redemption;

		$order->update_meta_data('_wc_points_redeemed', $points_redeemed);
		$order->update_meta_data('_wc_points_logged_redemption', [
			'points' => $points_redeemed,
			'amount' => $value_to_redemption,
			'discount_code' => $discount_code,
		]);
		$order->save();
		$order->add_order_note(
			sprintf(
				__('%1$d %2$s redeemed for a %3$s discount.', 'woocommerce-points-and-rewards'),
				$points_to_redemption,
				$wc_points_rewards->get_points_label($points_to_redemption),
				wc_price($value_to_redemption)
			)
		);

		return ['success' => true];
	}

	public static function get_points_to_redemption(WC_Order $order, float $balance_value): int
	{
		return WC_Points_Rewards_Manager::calculate_points_for_discount(
			static::get_value_to_redemption($order, $balance_value)
		);
	}

	public static function get_value_to_redemption(WC_Order $order, float $balance_value): float
	{
		if (0 >= $balance_value) {
			return 0.0;
		}

		$min_discount = floatval(get_option('wc_points_rewards_cart_min_discount', 0.0));

		if ($min_discount > $balance_value) {
			return 0.0;
		}

		$value_to_redemption = 0.0;

		foreach ($order->get_items() as $item) {
			if (!$item instanceof WC_Order_Item_Product) {
				continue;
			}

			$discount = static::get_discount_for_product($item);
			$discount = $balance_value <= $discount ? $balance_value : $discount;
			$value_to_redemption += $discount;
			$balance_value -= $discount;
		}

		$existing_discount_amounts = WC_Points_Rewards_Manager::calculate_points_value(
			intval($order->get_meta('_wc_points_redeemed'))
		);
		$existing_discount_amounts = floatval($existing_discount_amounts);
		$order_subtotal = $order->get_subtotal();
		$max_discount = get_option('wc_points_rewards_cart_max_discount', '');

		if (false !== strpos($max_discount, '%')) {
			$max_discount = static::calculate_discount_modifier($max_discount, $order_subtotal);
		}

		$max_discount = floatval($max_discount);
		$max_discount = max(0, min($max_discount, $max_discount - $existing_discount_amounts));

		if ($max_discount && $max_discount < $value_to_redemption) {
			$value_to_redemption = $max_discount;
		}

		return floatval($value_to_redemption);
	}

	protected static function get_discount_for_product(WC_Order_Item_Product $item): float
	{
		$product = $item->get_product();
		$quantity = $item->get_quantity();

		if (!$product instanceof WC_Product) {
			return 0.0;
		}

		$max_discount = WC_Points_Rewards_Product::get_maximum_points_discount_for_product($product);

		if (is_numeric($max_discount)) {
			return $max_discount * $quantity;
		} else {
			$tax_application = get_option(
				'wc_points_rewards_points_tax_application',
				wc_prices_include_tax() ? 'inclusive' : 'exclusive'
			);

			if ('exclusive' === $tax_application) {
				if (function_exists('wc_get_price_excluding_tax')) {
					$max_discount = wc_get_price_excluding_tax($product, ['qty' => $quantity]);
				} elseif (method_exists($product, 'get_price_excluding_tax')) {
					$max_discount = $product->get_price_excluding_tax($quantity);
				} else {
					$max_discount = $product->get_price('edit') * $quantity;
				}
			} else {
				if (function_exists('wc_get_price_including_tax')) {
					$max_discount = wc_get_price_including_tax($product, ['qty' => $quantity]);
				} elseif (method_exists($product, 'get_price_including_tax')) {
					$max_discount = $product->get_price_including_tax($quantity);
				} else {
					$max_discount = $product->get_price('edit') * $quantity;
				}
			}

			return $max_discount;
		}
	}

	protected static function calculate_discount_modifier(string $percentage, float $subtotal): float
	{
		$percentage = intval(str_replace('%', '', $percentage)) / 100;

		return $percentage * $subtotal;
	}
}
