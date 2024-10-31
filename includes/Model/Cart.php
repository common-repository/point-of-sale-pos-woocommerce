<?php

namespace ZPOS\Model;

use ZPOS\Admin\Setting\PostTab;
use ZPOS\Station;

class Cart
{
	const CART_ACTION_HOOK_NAME = 'pos_restore_product_quantity';

	public function __construct()
	{
		add_action(self::CART_ACTION_HOOK_NAME, [self::class, 'restore_product_quantity'], 10, 2);
	}

	public static function restore_product_quantity($params)
	{
		$cart_id = $params['cart_id'];
		$station_id = $params['station_id'];
		$products = get_option(self::get_option_name($cart_id));
		foreach ($products as $product) {
			self::update_product_quantity($product, $station_id, 'remove');
		}
	}

	public static function add_item($cart_id, $product, $station_id)
	{
		$cart_products = self::get_cart_items($cart_id);
		$hold_stock = PostTab::getValue('pos_hold_stock', $station_id);
		$expire = null;

		list($managed_product_id, $changed_quantity, $success) = self::update_product_quantity(
			$product,
			$station_id
		);

		if (!$success) {
			return ['stockQuantity' => $changed_quantity, 'success' => false];
		}

		if (sizeof($cart_products)) {
			wp_clear_scheduled_hook(self::CART_ACTION_HOOK_NAME, [
				['cart_id' => $cart_id, 'station_id' => $station_id],
			]);
		}
		array_push($cart_products, $product);
		if ($hold_stock) {
			$expire = self::create_cart_cron_job($cart_id, $station_id);
		}
		update_option(self::get_option_name($cart_id), $cart_products);
		return [
			'stockQuantity' => $changed_quantity,
			'success' => true,
			'expire' => $expire,
			'ids' => self::get_related_ids($managed_product_id),
		];
	}

	public static function change_product_quantity($cart_id, $cart_item_id, $quantity, $station_id)
	{
		$cart_products = self::get_cart_items($cart_id);
		$hold_stock = PostTab::getValue('pos_hold_stock', $station_id);
		$expire = null;
		$managed_product_id = null;
		$product_quantity = null;
		if ($hold_stock) {
			wp_clear_scheduled_hook(self::CART_ACTION_HOOK_NAME, [
				['cart_id' => $cart_id, 'station_id' => $station_id],
			]);
			$expire = self::create_cart_cron_job($cart_id, $station_id);
		}
		foreach ($cart_products as &$product) {
			if ($product['cart_item_id'] !== $cart_item_id) {
				continue;
			}

			list($managed_product_id, $changed_quantity, $success) = self::update_product_quantity(
				$product,
				$station_id,
				'edit',
				$quantity
			);

			if (!$success) {
				return ['id' => $product['id'], 'stockQuantity' => $changed_quantity, 'success' => false];
			}

			$product_quantity = $changed_quantity;
			$product['quantity'] = $quantity;
			break;
		}
		update_option(self::get_option_name($cart_id), $cart_products);
		return [
			'stockQuantity' => $product_quantity,
			'success' => true,
			'expire' => $expire,
			'ids' => self::get_related_ids($managed_product_id),
		];
	}

	public static function remove_from_cart($cart_id, $cart_item_id, $station_id)
	{
		$cart_products = self::get_cart_items($cart_id);
		$hold_stock = PostTab::getValue('pos_hold_stock', $station_id);
		$managed_product_id = null;
		$expire = null;
		if ($hold_stock) {
			wp_clear_scheduled_hook(self::CART_ACTION_HOOK_NAME, [
				['cart_id' => $cart_id, 'station_id' => $station_id],
			]);
		}
		foreach ($cart_products as $key => $product) {
			if ($product['cart_item_id'] !== $cart_item_id) {
				continue;
			}
			$managed_product_id = self::update_product_quantity($product, $station_id, 'remove')[0];
			array_splice($cart_products, $key, 1);
			break;
		}
		if ($hold_stock && !empty($cart_products)) {
			$expire = self::create_cart_cron_job($cart_id, $station_id);
		}
		update_option(self::get_option_name($cart_id), $cart_products);
		return [
			'ids' => self::get_related_ids($managed_product_id),
			'expire' => $expire,
		];
	}

	public static function clear_cart($cart_id, $restore_products, $station_id)
	{
		$cart_products = self::get_cart_items($cart_id);
		$hold_stock = PostTab::getValue('pos_hold_stock', $station_id);
		$is_expired =
			$hold_stock &&
			$restore_products &&
			$cart_products &&
			self::is_cron_job_expired($cart_id, $station_id);
		if ($hold_stock) {
			wp_clear_scheduled_hook(self::CART_ACTION_HOOK_NAME, [
				['cart_id' => $cart_id, 'station_id' => $station_id],
			]);
		}
		foreach ($cart_products as $product) {
			if ($restore_products && !$is_expired) {
				self::update_product_quantity($product, $station_id, 'remove');
			}
		}
		$restored_products_ids = array_map(function ($product) {
			$managed_product_id = self::get_management_related_product_id($product['id']);
			return self::get_related_ids($managed_product_id);
		}, $cart_products);
		$restored_products_ids = array_values(
			call_user_func_array('array_merge', $restored_products_ids)
		);
		delete_option(self::get_option_name($cart_id));
		return ['ids' => array_unique($restored_products_ids)];
	}

	public static function restore_cart($cart_id, $station_id)
	{
		$cart_products = self::get_cart_items($cart_id);
		$cart_available = true;
		if (!self::is_cron_job_expired($cart_id, $station_id)) {
			$success = wp_clear_scheduled_hook(self::CART_ACTION_HOOK_NAME, [
				['cart_id' => $cart_id, 'station_id' => $station_id],
			]);
			if (!$success) {
				return ['success' => false];
			}
			self::restore_product_quantity(['cart_id' => $cart_id]);
		}
		foreach ($cart_products as $product) {
			$managed_product_id = self::get_management_related_product_id($product['id']);
			$old_quantity = get_post_meta($managed_product_id, '_stock', true);
			$changed_quantity = $old_quantity - $product['quantity'];
			if ($changed_quantity < 0) {
				$cart_available = false;
				break;
			}
		}

		if (!$cart_available) {
			return ['available' => false];
		}

		foreach ($cart_products as &$product) {
			self::update_product_quantity($product, $station_id);
			$product['ids'] = self::get_related_ids($product['id']);
		}
		$expire = self::create_cart_cron_job($cart_id, $station_id);
		update_option(self::get_option_name($cart_id), $cart_products);
		return [
			'available' => true,
			'expire' => $expire,
			'success' => true,
		];
	}

	public static function update_product_quantity(
		array $product,
		int $station_id,
		string $action = 'add',
		?int $quantity = null
	): array {
		$managed_product_id = self::get_management_related_product_id($product['id']);
		$product_inventory = new ProductInventory(
			wc_get_product($managed_product_id),
			new Station($station_id)
		);
		$old_quantity = $product_inventory->get_location_quantity();
		switch ($action) {
			case 'edit':
				$changed_quantity = $old_quantity + $product['quantity'] - $quantity;
				break;
			case 'remove':
				$changed_quantity = $old_quantity + $product['quantity'];
				break;
			default:
				$changed_quantity = $old_quantity - $product['quantity'];
		}

		if ($changed_quantity < 0) {
			return [$product['id'], $old_quantity, false];
		}
		$product_inventory->update_location_quantity($changed_quantity);
		return [$managed_product_id, $changed_quantity, true];
	}

	public static function delete_scheduled_hook($cart_id, $station_id)
	{
		if (!$cart_id) {
			return;
		}
		wp_clear_scheduled_hook(self::CART_ACTION_HOOK_NAME, [
			['cart_id' => $cart_id, 'station_id' => $station_id],
		]);
	}

	public static function get_management_related_product_id($id)
	{
		$product_item = wc_get_product($id);
		return $product_item->managing_stock() ? $id : $product_item->get_parent_id();
	}

	public static function get_cart_items($cart_id): array
	{
		return get_option(self::get_option_name($cart_id), []);
	}

	public static function create_cart_cron_job($cart_id, $station_id)
	{
		wp_schedule_single_event(
			time() + MINUTE_IN_SECONDS * PostTab::getValue('pos_hold_stock', $station_id),
			self::CART_ACTION_HOOK_NAME,
			[['cart_id' => $cart_id, 'station_id' => $station_id]]
		);
		return wp_next_scheduled(self::CART_ACTION_HOOK_NAME, [
			['cart_id' => $cart_id, 'station_id' => $station_id],
		]);
	}

	public static function get_related_ids($id)
	{
		$product = wc_get_product($id);
		if (!$product->is_type('variable')) {
			return [$id];
		}
		$filtered = array_values(
			array_filter($product->get_available_variations(), function ($product) {
				$product_item = wc_get_product($product['variation_id']);
				return $product_item->managing_stock() === 'parent';
			})
		);
		$ids = array_map(function ($product) {
			return $product['variation_id'];
		}, $filtered);
		array_push($ids, $id);
		return $ids;
	}

	public static function get_option_name($cart_id): string
	{
		return '_cart_items_' . $cart_id;
	}

	public static function is_cron_job_expired($cart_id, $station_id)
	{
		return !wp_get_scheduled_event(self::CART_ACTION_HOOK_NAME, [
			['cart_id' => $cart_id, 'station_id' => $station_id],
		]);
	}
}
