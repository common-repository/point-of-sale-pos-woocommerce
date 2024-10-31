<?php

namespace ZPOS\API;

use WP_REST_Server, WP_REST_Request, WP_REST_Response, WP_Error;
use WP_REST_Controller;
use WC_Order;
use ZPOS\Admin\Woocommerce;
use const ZPOS\REST_NAMESPACE;

class PointsRewards extends WP_REST_Controller
{
	protected $namespace = REST_NAMESPACE;
	protected $rest_base = 'points-rewards';

	public function __construct()
	{
		do_action(__METHOD__, $this, $this->namespace, $this->rest_base);
	}

	public function register_routes(): void
	{
		do_action(__METHOD__, $this, $this->namespace, $this->rest_base);
		register_rest_route($this->namespace, '/' . $this->rest_base . '/calculate', [
			'methods' => WP_REST_Server::READABLE,
			'callback' => [$this, 'calculate'],
			'permission_callback' => [$this, 'check_permissions'],
		]);
		register_rest_route($this->namespace, '/' . $this->rest_base . '/redeem', [
			'methods' => WP_REST_Server::CREATABLE,
			'callback' => [$this, 'redeem'],
			'permission_callback' => [$this, 'check_permissions'],
		]);
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public function calculate(WP_REST_Request $request)
	{
		$order = $this->get_order_from_request($request);

		if ($order instanceof WP_Error) {
			return $order;
		}

		$rollback_result = Woocommerce\PointsRewards::rollback_previous_redemption($order);

		if ($rollback_result instanceof WP_Error) {
			return $rollback_result;
		}

		$customer_id = $order->get_customer_id();
		$balance_value = Woocommerce\PointsRewards::get_balance_value($customer_id);

		return new WP_REST_Response([
			'earned' => Woocommerce\PointsRewards::get_earned($order),
			'balance' => Woocommerce\PointsRewards::get_balance($customer_id),
			'balance_value' => $balance_value,
			'to_redemption' => Woocommerce\PointsRewards::get_points_to_redemption(
				$order,
				$balance_value
			),
			'min_points' => Woocommerce\PointsRewards::get_min_points(),
			'was_rollback' => $rollback_result,
		]);
	}

	public function check_permissions(): bool
	{
		return current_user_can('access_woocommerce_pos');
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public function redeem(WP_REST_Request $request)
	{
		$order = $this->get_order_from_request($request);

		if ($order instanceof WP_Error) {
			return $order;
		}

		$result = Woocommerce\PointsRewards::redeem($order);

		if ($result instanceof WP_Error) {
			return $result;
		}

		return new WP_REST_Response($result);
	}

	/**
	 * @return WC_Order|WP_Error
	 */
	protected function get_order_from_request(WP_REST_Request $request)
	{
		if (!isset($request['order_id']) || !is_numeric($request['order_id'])) {
			return new WP_Error('missing_parameter', 'Error: Missing or invalid parameter order_id.');
		}

		$order = wc_get_order(intval($request['order_id']));

		if ($order instanceof WC_Order) {
			return $order;
		}

		return new WP_Error('invalid_order', 'Error: Invalid order.');
	}
}
