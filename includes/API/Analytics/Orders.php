<?php

namespace ZPOS\API\Analytics;

use WC_Data;
use WC_Order;
use WP_Post;
use WP_REST_Request;
use WP_REST_Response;
use ZPOS\Admin;

class Orders
{
	public function __construct()
	{
		add_filter('woocommerce_rest_orders_prepare_object_query', [$this, 'add_query_filters'], 10, 2);

		add_filter(
			'woocommerce_rest_prepare_shop_order_object',
			[$this, 'add_extra_data_to_response'],
			10,
			3
		);

		add_filter('woocommerce_analytics_orders_query_args', [$this, 'apply_arg']);
		add_filter('woocommerce_analytics_orders_stats_query_args', [$this, 'apply_arg']);

		add_filter('woocommerce_analytics_clauses_join_orders_subquery', [$this, 'add_join_subquery']);
		add_filter('woocommerce_analytics_clauses_join_orders_stats_total', [
			$this,
			'add_join_subquery',
		]);
		add_filter('woocommerce_analytics_clauses_join_orders_stats_interval', [
			$this,
			'add_join_subquery',
		]);

		add_filter('woocommerce_analytics_clauses_where_orders_subquery', [
			$this,
			'add_where_subquery',
		]);
		add_filter('woocommerce_analytics_clauses_where_orders_stats_total', [
			$this,
			'add_where_subquery',
		]);
		add_filter('woocommerce_analytics_clauses_where_orders_stats_interval', [
			$this,
			'add_where_subquery',
		]);
	}

	public function add_query_filters(array $args, WP_REST_Request $request): array
	{
		if (isset($request['pos_station'])) {
			return $this->add_query_filter_by_station($args, $request['pos_station']);
		} elseif (isset($request['pos_gateway'])) {
			return $this->add_query_filter_by_gateway($args, $request['pos_gateway']);
		} elseif (isset($request['pos_user'])) {
			return $this->add_query_filter_by_user($args, $request['pos_user']);
		}

		return $args;
	}

	protected function add_query_filter_by_station(array $args, /* mixed */ $value): array
	{
		$args['meta_key'] = '_pos_by';

		if ('-1' === $value) {
			return $args;
		}

		$args['meta_value'] = intval($value);

		return $args;
	}

	/**
	 * @param mixed $value
	 */
	protected function add_query_filter_by_gateway(array $args, $value): array
	{
		if ('-1' === $value) {
			return $args;
		}

		if (Admin\Orders::is_custom_table_enabled()) {
			$args['payment_method'] = strval($value);
		} else {
			$args['meta_key'] = '_payment_method';
			$args['meta_value'] = strval($value);
		}

		return $args;
	}

	/**
	 * @param mixed $value
	 */
	protected function add_query_filter_by_user(array $args, $value): array
	{
		$args['author'] = intval($value);

		return $args;
	}

	public function add_extra_data_to_response(
		WP_REST_Response $response,
		WC_Data $object,
		WP_REST_Request $request
	): WP_REST_Response {
		if (empty($response->data['id'])) {
			return $response;
		}

		if (isset($request['with_pos_station']) && '1' === $request['with_pos_station']) {
			$response = $this->add_station_to_response($response);
		}

		if (isset($request['with_pos_user']) && '1' === $request['with_pos_user']) {
			$response = $this->add_user_to_response($response);
		}

		return $response;
	}

	protected function add_station_to_response(WP_REST_Response $response): WP_REST_Response
	{
		$order = new WC_Order(intval($response->data['id']));
		$station_id = $order->get_meta('_pos_by');

		if (empty($station_id)) {
			$response->data['pos_station'] = [
				'posID' => '-1',
				'name' => __('Web order', 'zpos-wp-api'),
			];

			return $response;
		}

		$station = get_post(intval($station_id));

		if (!$station instanceof WP_Post) {
			$response->data['pos_station'] = [
				'posID' => '-1',
				'name' => __('Deleted station', 'zpos-wp-api'),
			];

			return $response;
		}

		$response->data['pos_station'] = [
			'posID' => $station->ID,
			'name' => $station->post_title,
		];

		return $response;
	}

	protected function add_user_to_response(WP_REST_Response $response): WP_REST_Response
	{
		$order = new WC_Order(intval($response->data['id']));
		$user_id = intval($order->get_meta('_pos_user'));
		$user_name = get_the_author_meta('display_name', $user_id);

		$response->data['pos_user'] = [
			'id' => $user_id,
			'name' => $user_name,
		];

		return $response;
	}

	public function apply_arg(array $args): array
	{
		if (isset($_GET['filter'])) {
			$args['filter'] = sanitize_text_field(wp_unslash($_GET['filter']));
		}

		return $args;
	}

	public function add_join_subquery(array $clauses): array
	{
		if (empty($_GET['filter'])) {
			return $clauses;
		}

		$filter = sanitize_text_field(wp_unslash($_GET['filter']));

		if ('pos-online' !== $filter && 'pos-stations' !== $filter) {
			return $clauses;
		}

		global $wpdb;

		if (Admin\Orders::is_custom_table_enabled()) {
			$clauses[] = "INNER JOIN {$wpdb->prefix}wc_orders_meta order_meta ON {$wpdb->prefix}wc_order_stats.order_id = order_meta.order_id";
		} else {
			$clauses[] = "INNER JOIN {$wpdb->postmeta} order_meta ON {$wpdb->prefix}wc_order_stats.order_id = order_meta.post_id";
		}

		return $clauses;
	}

	public function add_where_subquery(array $clauses): array
	{
		if (empty($_GET['filter'])) {
			return $clauses;
		}

		$filter = sanitize_text_field(wp_unslash($_GET['filter']));

		if ('pos-online' === $filter) {
			$clauses[] =
				"AND order_meta.meta_key = '_created_via' AND order_meta.meta_value = 'checkout'";
		}

		if ('pos-stations' === $filter) {
			$clauses[] = "AND order_meta.meta_key = '_pos_by'";
		}

		return $clauses;
	}
}
