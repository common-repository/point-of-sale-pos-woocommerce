<?php

namespace ZPOS\API;

use WP_REST_Server,
	WC_REST_Payment_Gateways_Controller,
	WP_REST_Request,
	WP_REST_Response,
	WP_Error;
use WC_Payment_Gateway;
use ZPOS\Model;
use const ZPOS\PLUGIN_NAME;
use const ZPOS\REST_NAMESPACE;

class Gateways extends WC_REST_Payment_Gateways_Controller
{
	protected $namespace = REST_NAMESPACE;

	public function __construct()
	{
		do_action(__METHOD__, $this, $this->namespace, $this->rest_base);

		new Gateways\StripeConnect($this, $this->namespace, $this->rest_base);
	}

	public function register_routes(): void
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
	 * @return WP_Error|WP_REST_Response
	 */
	public function get_items($request)
	{
		$payment_gateways = Model\Gateway::get_available_gateways();
		$response = [];
		foreach ($payment_gateways as $payment_gateway_id => $payment_gateway) {
			$payment_gateway->id = $payment_gateway_id;
			$gateway = $this->prepare_item_for_response($payment_gateway, $request);
			$gateway = $this->prepare_response_for_collection($gateway);
			unset($gateway['_links']);
			$response[] = $gateway;
		}

		return rest_ensure_response($response);
	}

	/**
	 * @param WC_Payment_Gateway $gateway
	 * @param WP_REST_Request $request
	 */
	public function prepare_item_for_response($gateway, $request): WP_REST_Response
	{
		$response = parent::prepare_item_for_response($gateway, $request);
		$response->data['order'] = Model\Gateway::get_sort_order($gateway->id);
		$response->data['pos'] = Model\Gateway::isGatewayEnabled($gateway->id);
		$response->data['order_status'] = Model\Gateway::getGatewayOrderStatus($gateway->id);
		$response->data['fee_settings'] = Model\Gateway::get_fee_settings($gateway);
		$response->data['kiosk'] = $gateway->supports('kiosk');
		$response->data['default'] = get_option('pos_gateways_default') === $gateway->id;
		$response->data['values'] = [];

		if (method_exists($gateway, 'rest_filter_settings')) {
			$response->data['settings'] = $gateway->rest_filter_settings($response->data['settings']);
		} else {
			unset($response->data['settings']);
		}

		if (
			method_exists($gateway, 'getPublicValues') &&
			Model\Gateway::isGatewayEnabled($gateway->id)
		) {
			try {
				$response->data['values'] = $gateway->getPublicValues();
			} catch (\Exception $e) {
				wc_get_logger()->error('Failing retrieving gateway public value ' . $gateway->id, [
					'source' => PLUGIN_NAME,
				]);
				$response->data['values'] = [];
			}
		}

		return $response;
	}

	/**
	 * @param WP_REST_Request $request
	 * @return WP_Error|bool
	 */
	public function get_items_permissions_check($request)
	{
		if (!current_user_can('access_woocommerce_pos')) {
			return new \WP_Error(
				'woocommerce_rest_cannot_view',
				__('Sorry, you cannot view this resource.', 'woocommerce'),
				['status' => rest_authorization_required_code()]
			);
		}

		if (current_user_can('read_woocommerce_pos_gateways')) {
			return true;
		}

		return parent::get_items_permissions_check($request);
	}
}
