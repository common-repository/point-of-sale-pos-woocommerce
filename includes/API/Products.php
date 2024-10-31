<?php

namespace ZPOS\API;

use WC_REST_Products_Controller;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Term;
use ZPOS\Structure\AddDefaultImage;
use ZPOS\Structure\ProductIds;
use ZPOS\Structure\ProductResponse;
use ZPOS\Admin\Woocommerce;
use const ZPOS\REST_NAMESPACE;

class Products extends WC_REST_Products_Controller
{
	use AddDefaultImage, ProductResponse, ProductIds;

	protected $namespace = REST_NAMESPACE;
	protected $rest_base = 'products';

	public function __construct()
	{
		parent::__construct();
		do_action(__METHOD__, $this, $this->namespace, $this->rest_base);
		add_filter(
			'woocommerce_rest_check_permissions',
			function ($permission, $context, $object_id, $post_type) {
				if (is_array($post_type)) {
					$permissions = array_map(function ($type) use ($context, $object_id) {
						return wc_rest_check_post_permissions($type, $context, $object_id);
					}, $post_type);

					$permission = array_reduce(
						$permissions,
						function ($a, $b) {
							return $a && $b;
						},
						true
					);
				}
				return $permission;
			},
			10,
			4
		);
		add_filter(
			"woocommerce_rest_{$this->post_type}_object_query",
			[$this, 'add_meta_query_args'],
			1000
		);
		add_filter(
			"woocommerce_rest_prepare_{$this->post_type}_object",
			[$this, 'prepare_visibility'],
			1000
		);
		add_filter(
			"woocommerce_rest_prepare_{$this->post_type}_object",
			[$this, 'prepare_stock_quantity'],
			1000,
			3
		);
		add_filter(
			"woocommerce_rest_prepare_{$this->post_type}_object",
			[$this, 'add_default_images'],
			1000
		);
		add_filter("woocommerce_rest_prepare_{$this->post_type}_object", [
			$this,
			'normalize_category_slug',
		]);
		add_filter(
			"woocommerce_rest_prepare_{$this->post_type}_object",
			[Woocommerce\Subscriptions::class, 'prepare_subscription_product'],
			1000,
			3
		);
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

		if (defined('\UAP_ACTIVE') && \UAP_ACTIVE && class_exists('\UAP\Integration\POS')) {
			register_rest_route($this->namespace, '/' . $this->rest_base . '/rewritten_price', [
				'methods' => WP_REST_Server::READABLE,
				'callback' => [$this, 'get_rewritten_price'],
				'permission_callback' => [$this, 'get_items_permissions_check'],
			]);
		}
	}

	public function get_collection_params(): array
	{
		$params = parent::get_collection_params();

		$params['orderby']['enum'][] = 'parent_id';

		return $params;
	}

	/**
	 * @param WP_REST_Request $request
	 * @return WP_Error|WP_REST_Response
	 */
	public function get_items($request)
	{
		$response = parent::get_items($request);

		$count_query = new \WP_Query();
		$count_query->query([
			'post_type' => ['product_variation', 'product'],
			'meta_query' => $this->get_meta_query_args(),
		]);
		$total = $count_query->found_posts;

		$response->header('X-WP-Total-All', $total);

		return $response;
	}

	public function add_meta_query_args(array $args): array
	{
		$args['meta_query'] = $this->get_meta_query_args();

		return $args;
	}

	protected function get_meta_query_args(): array
	{
		return [
			'relation' => 'OR',
			['key' => Woocommerce\Products::VISIBILITY_META_NAME, 'compare' => 'NOT EXISTS'],
			['key' => Woocommerce\Products::VISIBILITY_META_NAME, 'value' => 'visible'],
			[
				'key' => Woocommerce\Products::VISIBILITY_META_NAME,
				'value' => Woocommerce\Products::VISIBILITY_OPTION_NAME,
			],
		];
	}

	public function prepare_visibility(WP_REST_Response $response): WP_REST_Response
	{
		$response->data['pos_visibility'] = Woocommerce\Products::get_visibility($response->data['id']);

		return $response;
	}

	public function get_rewritten_price(WP_REST_Request $request): float
	{
		$user_id = (int) $request['user_id'];
		$product_id = (int) $request['product_id'];

		return \UAP\Integration\POS::get_rewritten_price($product_id, $user_id);
	}

	public function normalize_category_slug(WP_REST_Response $response): WP_REST_Response
	{
		if (isset($response->data['categories']) && is_array($response->data['categories'])) {
			$response->data['categories'] = array_map(function ($category) {
				$category['slug'] = Woocommerce\Categories::normalize_slug($category['slug']);

				return $category;
			}, $response->data['categories']);
		}

		return $response;
	}

	public static function get_prepared_categories(int $product_id): array
	{
		return array_map(
			function (WP_Term $category): array {
				return [
					'id' => $category->term_id,
					'name' => $category->name,
					'slug' => Woocommerce\Categories::normalize_slug($category->slug),
				];
			},
			get_the_terms($product_id, 'product_cat') ?: []
		);
	}
}
