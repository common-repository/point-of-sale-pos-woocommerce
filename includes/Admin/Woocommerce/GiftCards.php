<?php

namespace ZPOS\Admin\Woocommerce;

use Exception;
use WC_GC_Gift_Card;
use WC_GC_Order_Item_Gift_Card;
use WC_Order;

class GiftCards
{
	public static function is_plugin_active(): bool
	{
		return class_exists('\WC_Gift_Cards');
	}

	/**
	 * @param WC_Order|int $order
	 */
	public static function get_order_cards($order): array
	{
		if (!$order instanceof WC_Order) {
			$order = wc_get_order($order);
		}

		$cards = $order->get_items('gift_card');

		return array_values(
			array_map(function (WC_GC_Order_Item_Gift_Card $card_item): array {
				$code = $card_item->get_code();

				try {
					$card_obj = static::get_card_by_code($code);
				} catch (Exception $exception) {
					return [
						'code' => $code,
						'amount' => 0.0,
						'balance' => 0.0,
					];
				}

				return [
					'code' => $code,
					'amount' => $card_item->get_amount(),
					'balance' => $card_obj->get_balance(),
				];
			}, $cards)
		);
	}

	/**
	 * @throws Exception
	 */
	public static function get_card_by_code(string $code): WC_GC_Gift_Card
	{
		$card_results = WC_GC()->db->giftcards->query([
			'return' => 'objects',
			'code' => $code,
			'limit' => 1,
		]);
		$card_data = count($card_results) ? array_shift($card_results) : false;

		if (!$card_data) {
			throw new Exception(__('Gift card not found.', 'pos-wp-api'));
		}

		return new WC_GC_Gift_Card($card_data);
	}
}
