<?php

namespace ZPOS\API;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WC_REST_Customers_Controller;
use WP_User;
use ZPOS\Model;
use const ZPOS\REST_NAMESPACE;

class Customers extends WC_REST_Customers_Controller
{
	protected $namespace = REST_NAMESPACE;

	public function __construct()
	{
		do_action(__METHOD__, $this, $this->namespace, $this->rest_base);
		add_filter('woocommerce_rest_prepare_customer', [$this, 'prepare_billing_vat'], 10, 2);
		add_filter('woocommerce_rest_insert_customer', [$this, 'insert_billing_vat'], 10, 2);
	}

	public function register_routes(): void
	{
		parent::register_routes();
		do_action(__METHOD__, $this, $this->namespace, $this->rest_base);

		register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<email>[\D]+)', [
			'args' => [
				'email' => [
					'description' => __('Unique email identifier for the resource.', 'woocommerce'),
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
	}

	/**
	 * @param WP_REST_Request $request
	 * @return WP_Error|WP_REST_Response
	 */
	public function get_items($request)
	{
		add_filter('woocommerce_rest_customer_query', [$this, 'add_query_args_for_search'], 10, 2);
		add_filter('woocommerce_rest_customer_query', [$this, 'add_query_args_for_role'], 10, 2);

		$response = parent::get_items($request);

		remove_filter('woocommerce_rest_customer_query', [$this, 'add_query_args_for_role']);
		remove_filter('woocommerce_rest_customer_query', [$this, 'add_query_args_for_search']);

		return $response;
	}

	public function add_query_args_for_search(array $args, WP_REST_Request $request): array
	{
		if ($request['search']) {
			$search = esc_sql($request['search']);

			if (current_user_can('search_list_users')) {
				$compare = 'LIKE';
			} else {
				$compare = '=';
				$args['search'] = $search;
			}

			$search_args = [
				'search' => $args['search'],
			];

			$meta_query = array_map(
				function ($key) use ($search, $compare) {
					return [
						'key' => $key,
						'value' => $search,
						'compare' => $compare,
					];
				},
				['nickname', 'first_name', 'last_name', 'billing_company', 'billing_phone']
			);
			$meta_query['relation'] = 'OR';

			$fields_args = ['meta_query' => $meta_query];

			$search_args['fields'] = $fields_args['fields'] = 'ID';

			$query_fields = new \WP_User_Query($fields_args);
			$query_search = new \WP_User_Query($search_args);

			$include = array_unique(
				array_merge($query_fields->get_results(), $query_search->get_results())
			);
			if ($args['include']) {
				$include = array_values(array_intersect($args['include'], $include));
			}

			$args['include'] = $include ? $include : [0];

			unset($args['search']);
		}
		return $args;
	}

	public function add_query_args_for_role(array $prepared_args, WP_REST_Request $request): array
	{
		$role = $request['role'];
		if ($role === 'customer') {
			unset($prepared_args['role']);
			$prepared_args['role__in'] = [$role, 'subscriber'];
		} elseif ('all' !== $role) {
			$prepared_args['role'] = $role;
		}
		return $prepared_args;
	}

	/**
	 * @param WP_REST_Request $request
	 */
	public function get_items_permissions_check($request): bool
	{
		return current_user_can('list_users') ||
			current_user_can('search_list_users') ||
			current_user_can('search_match_list_users');
	}

	public function get_collection_params_ids(): array
	{
		$params = [];

		$params['role'] = [
			'description' => __('Limit result set to resources with a specific role.', 'woocommerce'),
			'type' => 'string',
			'default' => 'customer',
			'enum' => array_merge(['all'], $this->get_role_names()),
			'validate_callback' => 'rest_validate_request_arg',
		];
		return $params;
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_single_item(WP_REST_Request $request)
	{
		$email = $request['email'];
		$user_data = get_user_by('email', $email);

		if (empty($email) || empty($user_data->ID)) {
			return new WP_Error(
				'woocommerce_rest_invalid_email',
				__('Customer not find.', 'woocommerce'),
				['status' => 404]
			);
		}

		$customer = $this->prepare_item_for_response($user_data, $request);

		return rest_ensure_response($customer);
	}

	/**
	 * @param $response_data
	 * @param WP_REST_Request $request
	 * @return array
	 */
	public function add_additional_fields_to_object($response_data, $request): array
	{
		$response_data = parent::add_additional_fields_to_object($response_data, $request);
		$response_data['taxes'] = $this->get_taxes_field_value($response_data, $request);

		if (defined('\UAP_ACTIVE') && \UAP_ACTIVE && class_exists('\UAP\Integration\POS')) {
			$response_data['has_price_rules'] = \UAP\Integration\POS::user_has_price_rules(
				$response_data['id']
			);
		}

		return $response_data;
	}

	private function get_taxes_field_value($data, $request)
	{
		$pos = $request->get_header('X-POS');
		try {
			$customer = new \WC_Customer($data['id']);
			$slugs = \Wc_Tax::get_tax_class_slugs();
			$slugs[] = '';
			$taxes = array_map(function ($slug) use ($customer, $pos) {
				return [
					'slug' => $slug === '' ? 'standard' : $slug,
					'taxes' => array_values(Taxes::get_current_taxes_rates($customer, $slug, $pos)),
				];
			}, $slugs);
		} catch (\Exception $exception) {
			$taxes = [];
		}

		return $taxes;
	}

	public function get_single_item_permissions_check()
	{
		return current_user_can('read_woocommerce_pos_single_customers');
	}

	public function prepare_billing_vat(WP_REST_Response $response, WP_User $user): WP_REST_Response
	{
		$billingVat = new Model\BillingVat($user->ID);
		$response->data['billing']['tax_vat_type'] = $billingVat->get_type();
		$response->data['billing']['tax_vat_id'] = $billingVat->get_id();

		return $response;
	}

	public function insert_billing_vat(WP_User $user, WP_REST_Request $request): WP_User
	{
		$data = $request->get_json_params();
		$billingVat = new Model\BillingVat($user->ID);

		if (isset($data['billing']['tax_vat_type']['value'])) {
			$billingVat->add_type($data['billing']['tax_vat_type']['value']);
		}

		if (isset($data['billing']['tax_vat_id'])) {
			$billingVat->add_id($data['billing']['tax_vat_id']);
		}

		return $user;
	}
}
