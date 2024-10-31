<?php

namespace ZPOS\API;

use WC_Order_Item_Product;
use WC_Payment_Gateway;
use WP_Error, WP_REST_Server, WP_REST_Request, WP_REST_Response;
use WC_REST_Orders_Controller;
use WC_Order;
use WC_Order_Item;
use WC_Order_Item_Shipping;
use WC_Product;
use WC_Tax;
use WP_Post;
use WP_User;
use WC_Coupon;
use ZPOS\Admin\Setting\PostTab;
use ZPOS\Admin\Woocommerce;
use ZPOS\Admin\Woocommerce\Subscriptions;
use ZPOS\Model\Gateway;
use ZPOS\Gateway\SplitPayment;
use ZPOS\Model\BillingVat;
use ZPOS\Model\Cart;
use ZPOS\Model\ProductInventory;
use ZPOS\Station;
use ZPOS\StationException;
use ZPOS\Structure\OrderPermissions;
use const ZPOS\REST_NAMESPACE;

class Orders extends WC_REST_Orders_Controller
{
	use OrderPermissions;

	protected $namespace = REST_NAMESPACE;

	/* hack to get $order object for calculate taxes (see store_order and unstore_order methods), used in calculate_taxes */
	protected $current_order = null;

	public function __construct()
	{
		do_action(__METHOD__, $this, $this->namespace, $this->rest_base);
		add_filter('user_has_cap', [$this, 'add_extended_permissions_to_order'], 1, 3);
		add_filter(
			'woocommerce_rest_check_permissions',
			[$this, 'check_extended_permissions_to_get_order_object'],
			10,
			4
		);
		add_filter(
			"woocommerce_rest_pre_insert_{$this->post_type}_object",
			[$this, 'insert_order'],
			10,
			2
		);
		add_filter(
			"woocommerce_rest_insert_{$this->post_type}_object",
			[$this, 'payment_order'],
			10,
			2
		);
		add_filter("woocommerce_rest_insert_{$this->post_type}_object", [$this, 'insert_author_order']);

		add_action('woocommerce_order_before_calculate_totals', [$this, 'store_order'], 10, 2);
		add_action('woocommerce_order_after_calculate_totals', [$this, 'unstore_order']);
		add_action('woocommerce_order_item_after_calculate_taxes', [$this, 'calculate_taxes']);
		add_action('woocommerce_order_item_shipping_after_calculate_taxes', [
			$this,
			'calculate_shipping_taxes',
		]);
		add_action(
			'woocommerce_order_after_calculate_totals',
			function ($and_taxes, $order) {
				if ($tip = $order->get_meta('pos-tip')) {
					$order->set_total($tip + $order->get_total());
				}
			},
			10,
			2
		);
		add_filter(
			"woocommerce_rest_prepare_{$this->post_type}_object",
			[$this, 'prepare_order'],
			10,
			2
		);
	}

	public function register_routes(): void
	{
		parent::register_routes();
		do_action(__METHOD__, $this, $this->namespace, $this->rest_base);

		register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)/email', [
			'args' => [
				'id' => [
					'description' => __('Unique identifier for the resource.', 'woocommerce'),
					'type' => 'integer',
					'required' => true,
				],
			],
			[
				'methods' => WP_REST_Server::CREATABLE,
				'callback' => [$this, 'send_email'],
				'permission_callback' => [$this, 'permission_check'],
				'args' => [
					'email' => [
						'type' => 'email',
						'required' => true,
					],
					'update_billing_email' => [
						'type' => 'boolean',
						'required' => false,
					],
				],
			],
		]);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)/base_payment_page',
			[
				'args' => [
					'id' => [
						'description' => __('Unique identifier for the resource.', 'woocommerce'),
						'type' => 'integer',
						'required' => true,
					],
				],
				[
					'methods' => WP_REST_Server::READABLE,
					'callback' => [$this, 'get_base_payment_page_url'],
					'permission_callback' => '__return_true',
				],
			]
		);

		if (class_exists('\Zprint\Model\Location')) {
			register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)/print', [
				'args' => [
					'id' => [
						'description' => __('Unique identifier for the resource.', 'woocommerce'),
						'type' => 'integer',
						'required' => true,
					],
				],
				[
					'methods' => WP_REST_Server::CREATABLE,
					'callback' => [$this, 'print_order'],
					'permission_callback' => [$this, 'permission_check'],
					'args' => [
						'location' => [
							'type' => 'array',
							'required' => true,
						],
					],
				],
			]);
		}
	}

	/**
	 * @return WP_Error|array
	 */
	public function send_email(WP_REST_Request $request)
	{
		$url_params = $request->get_url_params();
		$json_params = $request->get_json_params();

		$order = $url_params['id'];
		$email = $json_params['email'];
		$update_billing_email = filter_var(
			$json_params['update_billing_email'],
			FILTER_VALIDATE_BOOLEAN
		);

		$order = new WC_Order($order);

		if (0 === $order->get_id()) {
			return new WP_Error(
				"woocommerce_rest_{$this->post_type}_invalid_id",
				__('Invalid ID.', 'woocommerce'),
				['status' => 400]
			);
		}
		if (!is_email($email)) {
			return new WP_Error(
				"woocommerce_rest_{$this->post_type}_invalid_email",
				__('Invalid Email.', 'woocommerce'),
				['status' => 400]
			);
		}

		if ($update_billing_email) {
			$order->update_meta_data('_billing_email', $email);
			$order->save();
		}

		$status = apply_filters('zpos_receipt_email', $order, $email);

		return ['success' => $status];
	}

	public function get_base_payment_page_url(WP_REST_Request $request): array
	{
		$url_params = $request->get_url_params();
		$order = wc_get_order($url_params['id']);

		return [
			'url' => add_query_arg(['pay_for_order' => 'true'], $order->get_checkout_payment_url(true)),
		];
	}

	public function insert_author_order(WC_Order $order): void
	{
		if ($order->get_meta('_pos_by')) {
			$current_user = wp_get_current_user();
			self::setUser($order, $current_user);
		}
	}

	public static function setUser(WC_Order $order, WP_User $user): void
	{
		wp_update_post([
			'ID' => $order->get_id(),
			'post_author' => $user->ID,
		]);
		$order->update_meta_data('_pos_user', $user->ID);
		$order->update_meta_data('_pos_user_name', $user->user_firstname . ' ' . $user->user_lastname);
	}

	/**
	 * @return WC_Order|WP_Error
	 */
	public function insert_order(WC_Order $order, WP_REST_Request $request)
	{
		$json_params = $request->get_json_params();
		$station_id = $order->get_meta('_pos_by');

		if (
			isset($json_params['cart_id']) &&
			'block' === PostTab::getValue('pos_inventory_management', $station_id)
		) {
			Cart::delete_scheduled_hook($json_params['cart_id'], $station_id);
		}

		if (isset($json_params['status']) && $json_params['status']) {
			$order->set_status($json_params['status']);
		}

		Subscriptions::add_sign_up_fee_to_line_items($order->get_items());

		if (isset($json_params['set_paid']) && $json_params['set_paid']) {
			$order->set_date_paid(current_time('mysql'));

			$subscriptions = Subscriptions::create_from_order($order, $request);

			if ($subscriptions instanceof WP_Error) {
				return $subscriptions;
			}
		}

		if (!$order->has_status('completed')) {
			$item_ids_from_request =
				isset($request['line_items']) && is_array($request['line_items'])
					? array_map(function (array $item): int {
						return $item['id'] ?? 0;
					}, $request['line_items'])
					: [];

			foreach ($order->get_items() as $item) {
				if (!$item instanceof WC_Order_Item_Product) {
					continue;
				}

				$item_id = $item->get_id();

				if (!in_array($item_id, $item_ids_from_request, true)) {
					$order->remove_item($item_id);
				}
			}

			if (class_exists('\ZAddons\Product')) {
				array_map(function (WC_Order_Item $item) {
					$this->add_zaddon_meta($item);
				}, $order->get_items());
			}

			return $order;
		}

		return $order;
	}

	public function payment_order(WC_Order $order, WP_REST_Request $request): WC_Order
	{
		$json_params = $request->get_json_params();

		if (
			(SplitPayment::is_split_payment($json_params) &&
				SplitPayment::is_pending_split_payment($order)) ||
			!(
				isset($json_params['set_paid']) &&
				$json_params['set_paid'] &&
				isset($json_params['status']) &&
				$json_params['status'] === 'completed'
			)
		) {
			$gateways = Gateway::get_available_gateways();

			$gateway = array_reduce($gateways, function ($result, WC_Payment_Gateway $gateway) use (
				$order
			) {
				return $order->get_payment_method() === $gateway->id ? $gateway : $result;
			});

			if ($gateway) {
				$gateway->process_payment($order);
			}
		}

		return $order;
	}

	public function add_zaddon_meta(WC_Order_Item $item): void
	{
		\ZAddons\Product::add_meta_to_item(
			$item->get_product_id(),
			$item->get_meta('_zaddon_values'),
			(int) $item->get_meta('_zaddon_additional'),
			$item
		);
	}

	/**
	 * @return WP_Error|array
	 */
	public function print_order(WP_REST_Request $request)
	{
		$url_params = $request->get_url_params();
		$json_params = $request->get_json_params();

		$order = $url_params['id'];
		$order = new WC_Order($order);

		if (0 === $order->get_id()) {
			return new WP_Error(
				"woocommerce_rest_{$this->post_type}_invalid_id",
				__('Invalid ID.', 'woocommerce'),
				['status' => 400]
			);
		}

		$location = array_map('intval', $json_params['location']);
		try {
			$location = array_map(function ($id) {
				return new \Zprint\Model\Location($id);
			}, $location);
		} catch (\Zprint\Exception\DB $exception) {
			return new WP_Error(
				"woocommerce_rest_{$this->post_type}_invalid_location",
				__('Invalid Locations.', 'woocommerce'),
				['status' => 400]
			);
		}

		\Zprint\Printer::reprintOrder($order, $location);

		return ['success' => true];
	}

	public function calculate_taxes(WC_Order_Item $order_item): void
	{
		/**
		 * @var $order WC_Order
		 */
		$order = $this->current_order;

		$tax_status = $order_item->get_meta('_pos_tax_status');
		$order_item->delete_meta_data('_pos_tax_status');

		$pos = $order->get_meta('_pos_by');

		if ($pos === null) {
			return;
		}

		$taxes_enabled = $order->get_meta('_pos-taxes-enabled');

		if (
			get_option('pos_tax_enabled') === 'off' ||
			'none' === $tax_status ||
			('' !== $taxes_enabled && !$taxes_enabled)
		) {
			$order_item->set_taxes([]);

			return;
		}

		if (!$this->is_taxable($order_item)) {
			return;
		}

		$tax_rates = Taxes::get_current_taxes_rates($order, $order_item->get_tax_class(), $pos);

		$taxes = WC_Tax::calc_tax($order_item->get_total(), $tax_rates, false);

		if (method_exists($order_item, 'get_subtotal')) {
			$subtotal_taxes = WC_Tax::calc_tax($order_item->get_subtotal(), $tax_rates, false);
			$order_item->set_taxes([
				'total' => $taxes,
				'subtotal' => $subtotal_taxes,
			]);
		} else {
			$order_item->set_taxes(['total' => $taxes]);
		}
	}

	public function calculate_shipping_taxes(WC_Order_Item_Shipping $order_item): void
	{
		/**
		 * @var $order WC_Order
		 */
		$order = $this->current_order;

		$shipping_tax_status = $order_item->get_meta('_pos_shipping_tax_status');
		$order_item->delete_meta_data('_pos_shipping_tax_status');

		$pos = $order->get_meta('_pos_by');

		if ($pos === null) {
			return;
		}

		$taxes_enabled = $order->get_meta('_pos-taxes-enabled');

		if (get_option('pos_tax_enabled') === 'off' || ('' !== $taxes_enabled && !$taxes_enabled)) {
			$order_item->set_taxes([]);

			return;
		}

		if (!$this->is_taxable($order_item)) {
			return;
		}

		if ('none' === $shipping_tax_status) {
			$order_item->set_taxes([]);
		}
	}

	public function store_order(bool $and_taxes, WC_Order $order): void
	{
		$this->current_order = $order;
	}

	public function unstore_order(): void
	{
		$this->current_order = null;
	}

	protected function is_taxable(WC_Order_Item $order_item): bool
	{
		return '0' !== $order_item->get_tax_class() &&
			'taxable' === $order_item->get_tax_status() &&
			wc_tax_enabled();
	}

	public function check_extended_permissions_to_get_order_object(
		bool $permission,
		string $context,
		int $object_id,
		string $post_type
	): bool {
		if ($permission || 'read' !== $context || $this->post_type !== $post_type) {
			return $permission;
		}

		if (empty($_REQUEST['search'])) {
			return false;
		}

		if (current_user_can('search_match_private_shop_orders')) {
			$search = intval(wp_unslash($_REQUEST['search']));
			$order_post = get_post($object_id);

			if ($order_post instanceof WP_Post && $order_post->ID === $search) {
				return true;
			}
		}

		return current_user_can('search_private_shop_orders');
	}

	public function permission_check(): bool
	{
		return is_user_logged_in();
	}

	/**
	 * @param WP_REST_Request $request
	 * @return WP_Error|bool
	 */
	public function get_items_permissions_check($request)
	{
		if (
			current_user_can('read_private_shop_orders') ||
			current_user_can('search_private_shop_orders') ||
			current_user_can('search_match_private_shop_orders')
		) {
			return true;
		}

		return parent::get_items_permissions_check($request);
	}

	/**
	 * @param WP_REST_Request $request
	 * @return WP_Error|bool
	 */
	public function get_item_permissions_check($request)
	{
		if (isset($request['id']) && current_user_can('read_shop_order', $request['id'])) {
			return true;
		}

		return parent::get_items_permissions_check($request);
	}

	public function prepare_order(WP_REST_Response $response, WC_Order $order): WP_REST_Response
	{
		if (empty($response->data)) {
			return $response;
		}

		$order_data = $this->maybe_set_zero_status($response->get_data(), $order);
		$order_data['coupon_lines'] =
			isset($order_data['coupon_lines']) && is_array($order_data['coupon_lines'])
				? $this->prepare_coupons($order_data['coupon_lines'])
				: [];
		$customer_id = isset($order_data['customer_id']) ? intval($order_data['customer_id']) : 0;
		$user = get_userdata($customer_id);
		$order_data['customer_full_name'] = $user
			? $user->first_name . ' ' . $user->last_name
			: __('Guest', 'zpos-wp-api');
		$station_id = $order->get_meta('_pos_by');
		try {
			$station = is_numeric($station_id) ? new Station($station_id) : null;
		} catch (StationException $e) {
			$station = null;
		}

		$order_data['line_items'] =
			isset($order_data['line_items']) && is_array($order_data['line_items'])
				? $this->prepare_products($order_data['line_items'], $station)
				: [];
		$customer_billing_vat = new BillingVat($customer_id);
		$order_billing_vat = new BillingVat($order);
		$order_data['billing']['tax_vat_id'] = $customer_billing_vat->get_id();
		$order_data['billing']['tax_vat_type'] = $customer_billing_vat->get_type();

		$order_billing_vat->add_id($customer_billing_vat->get_id());
		$order_billing_vat->add_type($customer_billing_vat->get_type());

		if (Woocommerce\GiftCards::is_plugin_active()) {
			$order_data['gift_cards'] = Woocommerce\GiftCards::get_order_cards($order);
		}

		$response->data = $order_data;

		return $response;
	}

	protected function maybe_set_zero_status(array $order_data, WC_Order $order): array
	{
		if (0 != $order_data['total'] || !empty($order_data['gift_cards'])) {
			return $order_data;
		}

		$station_id = $order->get_meta('_pos_by');

		if (!is_numeric($station_id)) {
			return $order_data;
		}

		try {
			$station = new Station($station_id);
		} catch (StationException $e) {
			$station = null;
		}

		if (is_null($station)) {
			return $order_data;
		}

		$zero_status = $station->getData('pos_zero_order_status');
		$order->update_status($zero_status);
		$order_data['status'] = $zero_status;

		return $order_data;
	}

	protected function prepare_coupons(array $coupon_lines): array
	{
		return array_map(function (array $coupon_data): array {
			$coupon = new WC_Coupon($coupon_data['id']);
			$coupon_object_data = $coupon->get_data();
			$coupon_data['amount'] = $coupon_data['amount'] ?? strval($coupon_data['nominal_amount']);
			$coupon_data['date_expires'] =
				$coupon_data['date_expires'] ??
				wc_rest_prepare_date_response($coupon_object_data['date_expires'], false);
			$coupon_data['date_expires_gmt'] =
				$coupon_data['date_expires_gmt'] ??
				wc_rest_prepare_date_response($coupon_object_data['date_expires']);
			$coupon_data['minimum_amount'] =
				$coupon_data['minimum_amount'] ?? $coupon_object_data['minimum_amount'];
			$coupon_data['maximum_amount'] =
				$coupon_data['maximum_amount'] ?? $coupon_object_data['maximum_amount'];
			$coupon_data['exclude_sale_items'] =
				$coupon_data['exclude_sale_items'] ?? $coupon_object_data['exclude_sale_items'];
			$coupon_data['product_ids'] =
				$coupon_data['product_ids'] ?? $coupon_object_data['product_ids'];
			$coupon_data['excluded_product_ids'] =
				$coupon_data['excluded_product_ids'] ?? $coupon_object_data['excluded_product_ids'];
			$coupon_data['product_categories'] =
				$coupon_data['product_categories'] ?? $coupon_object_data['product_categories'];
			$coupon_data['excluded_product_categories'] =
				$coupon_data['excluded_product_categories'] ??
				$coupon_object_data['excluded_product_categories'];
			$coupon_data['usage_count'] =
				$coupon_data['usage_count'] ?? $coupon_object_data['usage_count'];
			$coupon_data['individual_use'] =
				$coupon_data['individual_use'] ?? $coupon_object_data['individual_use'];
			$coupon_data['usage_limit'] =
				$coupon_data['usage_limit'] ?? $coupon_object_data['usage_limit'];
			$coupon_data['usage_limit_per_user'] =
				$coupon_data['usage_limit_per_user'] ?? $coupon_object_data['usage_limit_per_user'];
			$coupon_data['limit_usage_to_x_items'] =
				$coupon_data['limit_usage_to_x_items'] ?? $coupon_object_data['limit_usage_to_x_items'];
			$coupon_data['email_restrictions'] =
				$coupon_data['email_restrictions'] ?? $coupon_object_data['email_restrictions'];
			$coupon_data['used_by'] = $coupon_data['used_by'] ?? $coupon_object_data['used_by'];

			return $coupon_data;
		}, $coupon_lines);
	}

	protected function prepare_products(array $line_items, ?Station $station): array
	{
		foreach ($line_items as $key => $item) {
			if (!isset($item['product_id']) && !isset($item['variation_id'])) {
				continue;
			}

			$product = wc_get_product($item['variation_id'] ?? $item['product_id']);

			if (!$product instanceof WC_Product) {
				continue;
			}

			$line_items[$key]['stock_quantity'] = is_null($station)
				? $product->get_stock_quantity()
				: (new ProductInventory($product, $station))->get_location_quantity();
			$line_items[$key]['_categories'] = Products::get_prepared_categories($item['product_id']);
		}

		return $line_items;
	}
}
