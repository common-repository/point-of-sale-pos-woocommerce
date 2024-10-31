<?php

namespace ZPOS\API\Analytics;

use WC_REST_Controller, WP_REST_Server, WP_REST_Request, WP_REST_Response;
use WC_Payment_Gateway;
use ZPOS\Model;

class Gateways extends WC_REST_Controller
{
	protected $namespace = Setup::REST_NAMESPACE;
	protected $rest_base = 'gateways';

	public function __construct()
	{
		do_action(__METHOD__, $this, $this->namespace, $this->rest_base);
	}

	public function register_routes()
	{
		do_action(__METHOD__, $this, $this->namespace, $this->rest_base);

		register_rest_route($this->namespace, '/' . $this->rest_base, [
			[
				'methods' => WP_REST_Server::READABLE,
				'callback' => [$this, 'get_items'],
				'permission_callback' => [$this, 'get_items_permissions_check'],
			],
		]);
	}

	/**
	 * @param WP_REST_Request $request
	 */
	public function get_items($request): WP_REST_Response
	{
		return new WP_REST_Response(
			array_map(function (WC_Payment_Gateway $gateway) {
				return [
					'label' => 'POS ' . $gateway->method_title,
					'value' => $gateway->id,
				];
			}, array_values(Model\Gateway::get_available_gateways()))
		);
	}

	/**
	 * @param WP_REST_Request $request
	 */
	public function get_items_permissions_check($request): bool
	{
		return current_user_can('read_woocommerce_pos_gateways');
	}
}
