<?php

namespace ZPOS\API;

use WP_Error;
use WP_REST_Request;
use WC_REST_Order_Notes_Controller;
use ZPOS\Structure\OrderPermissions;
use const ZPOS\REST_NAMESPACE;

class OrderNotes extends WC_REST_Order_Notes_Controller
{
	use OrderPermissions;

	protected $namespace = REST_NAMESPACE;

	public function __construct()
	{
		do_action(__METHOD__, $this, $this->namespace, $this->rest_base);
		add_filter('user_has_cap', [$this, 'add_extended_permissions_to_order'], 1, 3);
	}

	/**
	 * @param $request WP_REST_Request
	 * @return WP_Error|bool
	 */
	public function get_items_permissions_check($request)
	{
		if (
			isset($request['order_id']) &&
			current_user_can('read_shop_order', intval(wp_unslash($request['order_id'])))
		) {
			return true;
		}

		return parent::get_items_permissions_check($request);
	}

	/**
	 * @param $request WP_REST_Request
	 * @return WP_Error|bool
	 */
	public function create_item_permissions_check($request)
	{
		if (
			isset($request['order_id']) &&
			current_user_can('edit_shop_order', intval(wp_unslash($request['order_id'])))
		) {
			return true;
		}

		return parent::create_item_permissions_check($request);
	}

	/**
	 * @param $request WP_REST_Request
	 * @return WP_Error|bool
	 */
	public function get_item_permissions_check($request)
	{
		if (
			isset($request['order_id']) &&
			current_user_can('read_shop_order', intval(wp_unslash($request['order_id'])))
		) {
			return true;
		}

		return parent::get_item_permissions_check($request);
	}

	/**
	 * @param $request WP_REST_Request
	 * @return WP_Error|bool
	 */
	public function delete_item_permissions_check($request)
	{
		if (
			isset($request['order_id']) &&
			current_user_can('edit_shop_order', intval(wp_unslash($request['order_id'])))
		) {
			return true;
		}

		return parent::delete_item_permissions_check($request);
	}
}
