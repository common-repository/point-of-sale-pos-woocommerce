<?php

namespace ZPOS\API;

use Exception;
use WC_GC_REST_API_Order_Controller;
use WC_Order;
use WC_Order_Item_Product;
use WP_Error;
use WP_REST_Server, WP_REST_Request, WP_REST_Response;
use WC_GC_Gift_Card_Product;
use ZPOS\Admin\Woocommerce;
use const ZPOS\REST_NAMESPACE;

class GiftCards extends WC_GC_REST_API_Order_Controller
{
	protected $namespace = REST_NAMESPACE;
	protected $rest_base = 'gift-cards';

	public function __construct()
	{
		do_action(__METHOD__, $this, $this->namespace, $this->rest_base);
		parent::__construct();
	}

	private function hooks(): void
	{
	}

	public function register_routes(): void
	{
		do_action(__METHOD__, $this, $this->namespace, $this->rest_base);
		register_rest_route($this->namespace, '/' . $this->rest_base . '/apply', [
			'methods' => WP_REST_Server::CREATABLE,
			'callback' => [$this, 'apply'],
			'permission_callback' => [$this, 'check_permissions'],
		]);
		register_rest_route($this->namespace, '/' . $this->rest_base . '/remove', [
			'methods' => WP_REST_Server::CREATABLE,
			'callback' => [$this, 'remove'],
			'permission_callback' => [$this, 'check_permissions'],
		]);
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public function apply(WP_REST_Request $request)
	{
		try {
			if (empty($request['code']) || !is_string($request['code'])) {
				throw new Exception(__('Please enter your gift card code.', 'pos-wp-api'));
			} elseif (19 !== strlen($request['code'])) {
				throw new Exception(
					__(
						'Please enter a gift card code that follows the format XXXX-XXXX-XXXX-XXXX, where X can be any letter or number.',
						'pos-wp-api'
					)
				);
			}

			if (
				empty($request['order_id']) ||
				!is_numeric($request['order_id']) ||
				!($order = wc_get_order(intval($request['order_id'])))
			) {
				throw new Exception('Error: Missing or invalid parameter order_id.');
			}

			foreach ($order->get_items() as $order_item) {
				if (!$order_item instanceof WC_Order_Item_Product) {
					continue;
				}

				if (WC_GC_Gift_Card_Product::is_gift_card($order_item->get_product())) {
					throw new Exception(
						__('Gift cards cannot be purchased using other gift cards.', 'pos-wp-api')
					);
				}
			}

			$code = sanitize_text_field($request['code']);
			$card = Woocommerce\GiftCards::get_card_by_code($code);
			$balance = $card->get_balance();

			if (is_null($balance) || 0.0 === $balance) {
				throw new Exception(__('The gift card balance is empty.', 'pos-wp-api'));
			}

			$order_total = $order->get_total();
			$amount = min($balance, $order_total);

			if (!$this->update_card_data($order, $code, $amount)) {
				throw new Exception(__('Gift card was not removed.', 'pos-wp-api'));
			}

			return new WP_REST_Response([
				'code' => $code,
				'amount' => $amount,
			]);
		} catch (Exception $e) {
			return new WP_Error('gift_card_was_not_applied', $e->getMessage());
		}
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public function remove(WP_REST_Request $request)
	{
		try {
			if (empty($request['code']) || !is_string($request['code'])) {
				throw new Exception('Error: Missing or invalid parameter code.');
			}

			if (
				empty($request['order_id']) ||
				!is_numeric($request['order_id']) ||
				!($order = wc_get_order(intval($request['order_id'])))
			) {
				throw new Exception('Error: Missing or invalid parameter order_id.');
			}

			$code = sanitize_text_field($request['code']);
			$amount = 0.0;

			foreach (Woocommerce\GiftCards::get_order_cards($order) as $card_data) {
				if ($card_data['code'] !== $code) {
					continue;
				}

				$amount = $card_data['amount'];
				break;
			}

			if (!$this->update_card_data($order, $code, 0.0, true)) {
				throw new Exception(__('Gift card was not removed.', 'pos-wp-api'));
			}

			return new WP_REST_Response([
				'code' => $code,
				'amount' => $amount,
			]);
		} catch (Exception $e) {
			return new WP_Error('gift_card_was_not_removed', $e->getMessage());
		}
	}

	protected function update_card_data(
		WC_Order $order,
		string $code,
		float $amount,
		bool $delete = false
	): bool {
		$order_status = $order->get_status();

		if ('pending' !== $order_status && 'completed' !== $order_status) {
			$order->set_status('pending');
		}

		$card_data = [
			'code' => $code,
			'amount' => $amount,
		];

		if ($delete) {
			$card_data['delete'] = 'true';
		}

		if (!$this->update_order_field_value([$card_data], $order, 'gift_cards')) {
			return false;
		}

		return true;
	}

	public function check_permissions(): bool
	{
		return current_user_can('access_woocommerce_pos');
	}
}
