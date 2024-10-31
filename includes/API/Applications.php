<?php

namespace ZPOS\API;

use WC_REST_Controller;
use WP_Post;
use WP_REST_Request;
use WP_REST_Server;
use ZPOS\Admin\Setting\PostTab;
use ZPOS\Admin\Stations\Tabs\General;
use ZPOS\Admin\Woocommerce;
use ZPOS\Model;
use const ZPOS\REST_NAMESPACE;

class Applications extends WC_REST_Controller
{
	protected $namespace = REST_NAMESPACE;
	protected $rest_base = 'applications/(?P<id>[\w-]+)';

	public function __construct()
	{
		do_action(__METHOD__, $this, $this->namespace, $this->rest_base);
	}

	public function register_routes(): void
	{
		do_action(__METHOD__, $this, $this->namespace, $this->rest_base);

		register_rest_route($this->namespace, '/' . $this->rest_base . '/settings', [
			[
				'methods' => WP_REST_Server::READABLE,
				'callback' => [$this, 'settings'],
				'permission_callback' => [$this, 'get_items_permissions_check'],
			],
		]);
	}

	public function settings(WP_REST_Request $request): array
	{
		$pos = +$request['id'];
		$settings = [];
		$settings[] = [
			'id' => 'pos_notifications',
			'label' => 'Notifications',
			'type' => 'object',
			'option_key' => 'pos_notifications',
			'default' => General::getNotificationsDefaultValues(),
		];

		$settings[] = [
			'id' => 'pos_top_bar_functions',
			'label' => 'Top Bar Functions',
			'type' => 'radio',
			'option_key' => 'pos_top_bar_functions',
			'default' => 'expanded',
		];

		$settings[] = [
			'id' => 'pos_tax_enabled',
			'label' => 'Tax Calculation',
			'type' => 'select',
			'option_key' => 'pos_tax_enabled',
			'default' => 'on',
		];

		$settings[] = [
			'id' => 'pos_tax_based_on_order',
			'label' => 'Calculate Tax Based on',
			'type' => 'checkbox',
			'option_key' => 'pos_tax_based_on_order',
			'default' => ['shipping', 'billing', 'pos', 'wc'],
		];

		$settings[] = [
			'id' => 'pos_all_customer_fields_required',
			'label' => 'All Customer Fields are Required to Add a Customer',
			'type' => 'checkbox',
			'option_key' => 'pos_all_customer_fields_required',
			'default' => 'off',
		];

		$settings[] = [
			'id' => 'pos_cart_customer',
			'label' => 'Required Customer',
			'type' => 'checkbox',
			'option_key' => 'pos_cart_customer',
			'default' => 'off',
		];

		$settings[] = [
			'id' => 'pos_cart_menu_display',
			'label' => 'Menu Display',
			'type' => 'checkbox',
			'option_key' => 'pos_cart_menu_display',
			'default' => 'off',
		];

		$settings[] = [
			'id' => 'pos_coupons_manual',
			'label' => 'Enable Manual Coupons',
			'type' => 'checkbox',
			'option_key' => 'pos_coupons_manual',
			'default' => 'off',
		];

		$settings[] = [
			'id' => 'pos_enable_add_product',
			'label' => 'Enable Add a Product Functionality',
			'type' => 'checkbox',
			'option_key' => 'pos_enable_add_product',
			'default' => 'on',
		];

		$settings[] = [
			'id' => 'pos_enable_save_product_by_default',
			'label' => 'Enable Save by Default',
			'type' => 'checkbox',
			'option_key' => 'pos_enable_save_product_by_default',
			'default' => 'off',
		];

		$settings[] = [
			'id' => 'pos_inventory_location',
			'label' => 'Inventory Location',
			'type' => 'select',
			'option_key' => 'pos_inventory_location',
			'default' => '0',
		];
		$settings[] = [
			'id' => 'pos_inventory_management',
			'label' => 'Inventory Management',
			'type' => 'radio',
			'option_key' => 'pos_inventory_management',
			'default' => 'block',
		];
		$settings[] = [
			'id' => 'pos_hold_stock',
			'label' => 'Hold stock (minutes)',
			'type' => 'number',
			'option_key' => 'pos_hold_stock',
			'default' => '0',
		];
		$settings[] = [
			'id' => 'pos_hide_out_of_stock_products',
			'label' => 'Hide out of stock products from POS product list',
			'type' => 'checkbox',
			'option_key' => 'pos_hide_out_of_stock_products',
			'default' => 'off',
		];

		$settings[] = [
			'id' => 'pos_show_photo_in_tile',
			'label' => 'Show Photo in Tile',
			'type' => 'checkbox',
			'option_key' => 'pos_show_photo_in_tile',
			'default' => 'off',
		];

		$settings[] = [
			'id' => 'pos_display_prices_include_tax_in_shop',
			'label' => 'Prices in the shop include tax',
			'type' => 'checkbox',
			'option_key' => 'pos_display_prices_include_tax_in_shop',
			'default' => 'yes',
		];

		$settings[] = [
			'id' => 'pos_display_prices_include_tax_in_cart',
			'label' => 'Prices in the cart include tax',
			'type' => 'checkbox',
			'option_key' => 'pos_display_prices_include_tax_in_cart',
			'default' => 'yes',
		];

		if (Woocommerce\PointsRewards::is_plugin_active()) {
			$settings[] = [
				'id' => 'pos_points_rewards',
				'label' => 'Points and Rewards Enabled',
				'type' => 'checkbox',
				'option_key' => 'pos_points_rewards',
				'default' => 'no',
			];

			$settings[] = [
				'id' => 'pos_points_rewards_after_tips',
				'label' => 'Display Points and Rewards after Tips',
				'type' => 'checkbox',
				'option_key' => 'pos_points_rewards_after_tips',
				'default' => 'no',
			];

			$settings[] = [
				'id' => 'pos_points_rewards_guest_behavior',
				'label' => 'Point and Rewards behavior for guest customer',
				'type' => 'select',
				'option_key' => 'pos_points_rewards_guest_behavior',
				'default' => 'skip',
			];
		}

		$settings[] = [
			'id' => 'pos_tips',
			'label' => 'Tips Enabled',
			'type' => 'checkbox',
			'option_key' => 'pos_tips',
			'default' => 'no',
		];

		$settings[] = [
			'id' => 'pos_gateway_fee',
			'label' => 'Gateway Transaction Fee Enabled',
			'type' => 'checkbox',
			'option_key' => 'pos_gateway_fee',
			'default' => 'no',
		];

		$settings[] = [
			'id' => 'pos_cart_sorting',
			'label' => 'Sort products in cart by',
			'type' => 'select',
			'option_key' => 'pos_cart_sorting',
			'default' => 'price_desc',
		];

		$settings[] = [
			'id' => 'pos_products_sorting',
			'label' => 'Sort products in tabs by',
			'type' => 'select',
			'option_key' => 'pos_products_sorting',
			'default' => 'price_desc',
		];

		$settings[] = [
			'id' => 'pos_default_display_style',
			'label' => 'Default Display Style',
			'type' => 'select',
			'option_key' => 'pos_default_display_style',
			'default' => 'tiles',
		];

		$settings[] = [
			'id' => 'pos_auto_logout',
			'label' => 'User Auto Logout Action',
			'type' => 'select',
			'option_key' => 'pos_auto_logout',
			'default' => '0',
		];

		$settings[] = [
			'id' => 'pos_tax_vat_number',
			'label' => 'Enable Tax/VAT ID Type Settings',
			'type' => 'checkbox',
			'option_key' => 'pos_tax_vat_number',
			'default' => 'no',
		];

		$settings[] = [
			'id' => 'pos_tax_vat_behavior',
			'label' => 'Tax/VAT ID Behavior',
			'type' => 'select',
			'option_key' => 'pos_tax_vat_behavior',
			'default' => 'default',
		];

		$settings[] = [
			'id' => 'pos_barcode_automatically_add_to_cart',
			'label' => 'Barcode scan automatically adds item to cart',
			'type' => 'checkbox',
			'option_key' => 'pos_barcode_automatically_add_to_cart',
			'default' => 'off',
		];

		$settings[] = [
			'id' => 'pos_barcode_repeat_barcode_scans',
			'label' => 'Enable repeat Barcode scans functionality',
			'type' => 'checkbox',
			'option_key' => 'pos_barcode_repeat_barcode_scans',
			'default' => 'off',
		];

		$settings[] = [
			'id' => 'pos_product_name_color',
			'label' => 'Product Name',
			'type' => 'color',
			'option_key' => 'pos_product_name_color',
			'default' => '#1E1E1E',
		];

		$settings[] = [
			'id' => 'pos_product_sub_text_color',
			'label' => 'Product Sub Text',
			'type' => 'color',
			'option_key' => 'pos_product_sub_text_color',
			'default' => 'off',
		];

		$settings[] = [
			'id' => 'pos_hide_sub_text',
			'label' => 'Hide Sub Text',
			'type' => 'checkbox',
			'option_key' => 'pos_hide_sub_text',
			'default' => '#737373',
		];

		$settings[] = [
			'id' => 'pos_product_price_color',
			'label' => 'Product Price',
			'type' => 'color',
			'option_key' => 'pos_product_price_color',
			'default' => '#1E1E1E',
		];

		$settings[] = [
			'id' => 'pos_hide_product_price',
			'label' => 'Hide Product Price',
			'type' => 'checkbox',
			'option_key' => 'pos_hide_product_price',
			'default' => 'off',
		];

		$settings[] = [
			'id' => 'pos_out_of_stock_text_color',
			'label' => 'Out Of Stock Text',
			'type' => 'color',
			'option_key' => 'pos_out_of_stock_text_color',
			'default' => '#FF4221',
		];

		$settings[] = [
			'id' => 'pos_tile_background_color',
			'label' => 'Tile background',
			'type' => 'color',
			'option_key' => 'pos_tile_background_color',
			'default' => '#FFFFFF',
		];

		$settings[] = [
			'id' => 'pos_category_name_color',
			'label' => 'Category Name',
			'type' => 'color',
			'option_key' => 'pos_category_name_color',
			'default' => '#1E1E1E',
		];

		$settings[] = [
			'id' => 'pos_category_count_color',
			'label' => 'Category Count',
			'type' => 'color',
			'option_key' => 'pos_category_count_color',
			'default' => '#737373',
		];

		$settings[] = [
			'id' => 'pos_category_tile_background_color',
			'label' => 'Tile background',
			'type' => 'color',
			'option_key' => 'pos_category_tile_background_color',
			'default' => '#FFFFFF',
		];

		$settings[] = [
			'id' => 'pos_default_product_search',
			'label' => 'Product Search Default',
			'type' => 'radio',
			'option_key' => 'pos_default_product_search',
			'default' => 'name',
		];

		$settings = array_map(function ($setting) use ($pos) {
			$value = array_merge($setting, [
				'value' => PostTab::getValue($setting['option_key'], $pos),
			]);
			unset($value['option_key']);
			return $value;
		}, $settings);

		$settings[] = [
			'id' => 'pos_flags_path',
			'value' => Model\VatControl::get_flags_path(),
		];

		$settings[] = [
			'id' => 'pos_user_rights',
			'value' => wp_get_current_user()->get_role_caps(),
		];

		$settings[] = [
			'id' => 'pos_gift_card_addon_active',
			'value' => Woocommerce\GiftCards::is_plugin_active() ? 'yes' : 'no',
		];

		$settings[] = [
			'id' => 'pos_points_rewards_addon_active',
			'value' => Woocommerce\PointsRewards::is_plugin_active() ? 'yes' : 'no',
		];

		$inventory_location = PostTab::getValue('pos_inventory_location', $pos);
		$station_post = get_post($inventory_location);
		if ($station_post instanceof WP_Post) {
			$settings[] = [
				'id' => 'pos_inventory_location_name',
				'value' =>
					0 < $inventory_location ? $station_post->post_title : __('Shop base', 'zpos-wp-api'),
			];
		}

		return $settings;
	}

	/**
	 * @param WP_REST_Request $request
	 */
	public function get_items_permissions_check($request): bool
	{
		return current_user_can('read_woocommerce_pos_setting');
	}
}
