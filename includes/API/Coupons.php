<?php

namespace ZPOS\API;

use WC_Coupon;
use WC_Data;
use WP_Error;
use WP_Post;
use WP_Query;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server, WC_REST_Coupons_Controller;
use const ZPOS\REST_NAMESPACE;

class Coupons extends WC_REST_Coupons_Controller
{
	protected $namespace = REST_NAMESPACE;
	protected $rest_base = 'coupons';

	public function __construct()
	{
		do_action(__METHOD__, $this, $this->namespace, $this->rest_base);
	}

	public function register_routes(): void
	{
		parent::register_routes();
		do_action(__METHOD__, $this, $this->namespace, $this->rest_base);
		register_rest_route($this->namespace, '/' . $this->rest_base . '/ids', [
			'methods' => WP_REST_Server::READABLE,
			'callback' => [$this, 'get_all_ids'],
			'permission_callback' => [$this, 'get_items_permissions_check'],
		]);

		register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<code>[\w]+)', [
			'args' => [
				'code' => [
					'description' => __('Unique identifier for the resource.', 'woocommerce'),
					'type' => 'string',
				],
			],
			[
				'methods' => WP_REST_Server::READABLE,
				'callback' => [$this, 'get_single_item'],
				'permission_callback' => [$this, 'get_single_item_permissions_check'],
				'args' => [
					'context' => $this->get_context_param(['default' => 'view']),
				],
			],
		]);

		register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)/validate', [
			'args' => [
				'user_id' => [
					'type' => 'number',
				],
			],
			[
				'methods' => WP_REST_Server::READABLE,
				'callback' => [$this, 'validate_coupon'],
				'permission_callback' => '__return_true',
			],
		]);
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_all_ids()
	{
		$args = [
			'post_type' => [$this->post_type],
			'post_status' => ['publish'],
			'posts_per_page' => -1,
			'fields' => 'ids',
			'order' => $filter['order'] ?? 'ASC',
			'orderby' => $filter['orderby'] ?? 'title',
		];

		if (isset($filter['updated_at_min'])) {
			$args['date_query'][] = [
				'column' => 'post_modified',
				'after' => $filter['updated_at_min'],
				'inclusive' => false,
			];
		}

		$query = new WP_Query($args);

		return rest_ensure_response($query->posts);
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_single_item(WP_REST_Request $request)
	{
		$object = $this->get_object($request['code']);

		if (!$object || 0 === $object->get_id()) {
			return new WP_Error(
				"woocommerce_rest_{$this->post_type}_invalid_code",
				__('Invalid Coupon Code.', 'woocommerce'),
				['status' => 404]
			);
		}

		$data = $this->prepare_object_for_response($object, $request);
		$response = rest_ensure_response($data);

		if ($this->public) {
			$response->link_header('alternate', $this->get_permalink($object), [
				'type' => 'text/html',
			]);
		}

		return $response;
	}

	/**
	 * @param WC_Data $object
	 * @param WP_REST_Request $request
	 */
	public function prepare_object_for_response($object, $request): WP_REST_Response
	{
		$data = parent::prepare_object_for_response($object, $request);
		$coupon_data = $data->get_data();
		$coupon = new WC_Coupon($coupon_data['id']);
		$data->data['amount'] = wc_format_decimal($coupon->get_amount(), '');

		return $data;
	}

	public function get_single_item_permissions_check(): bool
	{
		return current_user_can('read_woocommerce_pos_single_coupons');
	}

	/**
	 * @param WP_REST_Request $request
	 */
	public function prepare_objects_query($request): array
	{
		$args = parent::prepare_objects_query($request);

		$meta_query = [];
		if (isset($request['pos'])) {
			$meta_query[] = [
				'key' => '_pos',
				'value' => 'true',
			];
		}

		if (isset($request['type'])) {
			$meta_query[] = [
				'key' => 'discount_type',
				'value' => $request['type'],
			];
		}

		if (isset($request['amount'])) {
			$meta_query[] = [
				'key' => 'coupon_amount',
				'value' => $request['amount'],
			];
		}

		if (count($meta_query) > 0) {
			$args['meta_query'] = $meta_query;
		}

		return $args;
	}

	public function validate_coupon(WP_REST_Request $request): array
	{
		$coupon_id = $request['id'];
		$user_id = $request['user_id'];
		$coupon = new WC_Coupon($coupon_id);

		if (
			$coupon &&
			$user_id &&
			apply_filters(
				'woocommerce_coupon_validate_user_usage_limit',
				$coupon->get_usage_limit_per_user() > 0,
				$user_id,
				$coupon,
				$this
			)
		) {
			$data_store = $coupon->get_data_store();
			$usage_count = $data_store->get_usage_by_user_id($coupon, $user_id);
			if ($usage_count >= $coupon->get_usage_limit_per_user()) {
				return ['isValid' => false];
			}
		}

		return ['isValid' => true];
	}

	/**
	 * @param WP_REST_Request $request
	 * @return WP_Error|WP_REST_Response
	 */
	public function get_items($request)
	{
		$search = $request->get_param('search');

		if (empty($search) || current_user_can('read_private_shop_coupons')) {
			return parent::get_items($request);
		}

		add_filter('posts_where', [$this, 'add_title_search_exact'], 10, 2);

		$query = new WP_Query([
			'post_type' => 'shop_coupon',
			'post_status' => 'publish',
			'search_title' => sanitize_text_field(wp_unslash($search)),
		]);
		$coupons = array_map(function (WP_Post $coupon_post) use ($request) {
			return $this->prepare_object_for_response(
				new WC_Coupon($coupon_post->ID),
				$request
			)->get_data();
		}, $query->get_posts());

		wp_reset_postdata();
		remove_filter('posts_where', [$this, 'add_title_search_exact'], 10, 2);

		return new WP_REST_Response($coupons);
	}

	public function add_title_search_exact(string $where, WP_Query $wp_query): string
	{
		global $wpdb;

		if ($search_term = $wp_query->get('search_title')) {
			$where .=
				' AND ' .
				$wpdb->posts .
				'.post_title LIKE \'' .
				esc_sql($wpdb->esc_like($search_term)) .
				'\'';
		}

		return $where;
	}

	/**
	 * @param WP_REST_Request $request
	 */
	public function get_items_permissions_check($request): bool
	{
		return current_user_can('read_private_shop_coupons') || $request->get_param('search');
	}
}
