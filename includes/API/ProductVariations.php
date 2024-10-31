<?php

namespace ZPOS\API;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use ZPOS\Model\ProductInventory;
use ZPOS\Station;
use ZPOS\Structure\ProductIds;
use const ZPOS\REST_NAMESPACE;
use ZPOS\Structure\AddDefaultImage;
use ZPOS\Structure\ProductResponse;
use ZPOS\Admin\Woocommerce;

class ProductVariations extends \WC_REST_Product_Variations_Controller
{
	use AddDefaultImage, ProductResponse, ProductIds;

	protected $namespace = REST_NAMESPACE;
	protected $rest_base = 'products/(?P<product_id>[\d]+)/variations';
	protected $post_type = 'product_variation';

	public function __construct()
	{
		parent::__construct();
		do_action(__METHOD__, $this, $this->namespace, $this->rest_base);
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
		register_rest_route($this->namespace, '/products/ids/variations', [
			'methods' => WP_REST_Server::READABLE,
			'callback' => [$this, 'get_all_ids'],
			'permission_callback' => [$this, 'get_items_permissions_check'],
		]);
		register_rest_route($this->namespace, '/products/variations', [
			'methods' => WP_REST_Server::READABLE,
			'callback' => [$this, 'get_items_by_id'],
			'permission_callback' => [$this, 'get_items_permissions_check'],
		]);
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_items_by_id(WP_REST_Request $request)
	{
		$params = $request->get_params();
		$ids = array_map('intval', explode(',', $params['ids']));
		$variations = [];

		foreach ($ids as $id) {
			$product = wc_get_product($id);

			if ($product && $product->exists() && $product->is_type('variation')) {
				$parent_id = $product->get_parent_id();
				$parent = wc_get_product($parent_id);
				$parent_status = $parent->get_status();
				$parent_visibility = \ZPOS\Admin\Woocommerce\Products::get_visibility($parent_id);

				if (
					'trash' !== $parent_status &&
					('visible' === $parent_visibility || 'pos' === $parent_visibility)
				) {
					$data = $this->prepare_object_for_response($product, $request)->get_data();
					$variations[] = (new ProductInventory(
						$product,
						new Station(intval(wp_unslash($params['station_id'])))
					))->apply_data_to_response($data);
				}
			}
		}

		return new WP_REST_Response($variations);
	}

	/**
	 * @param WP_REST_Request $request
	 * @return WP_Error|WP_REST_Response
	 */
	public function get_items($request)
	{
		$response = parent::get_items($request);
		$response = $this->apply_categories_to_items($response);

		$count_query = new \WP_Query();
		$count_query->query(['post_type' => ['product_variation', 'product']]);
		$total = $count_query->found_posts;

		$response->header('X-WP-Total-All', $total);

		return $response;
	}

	private function apply_categories_to_items(WP_REST_Response $response): WP_REST_Response
	{
		$items = $response->get_data();

		foreach ($items as $item_key => $item) {
			$items[$item_key]['categories'] = Products::get_prepared_categories($item['parent_id']);
		}

		$response->data = $items;

		return $response;
	}
}
