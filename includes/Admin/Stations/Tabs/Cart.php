<?php

namespace ZPOS\Admin\Stations\Tabs;

use ZPOS\Admin\Setting\Box;
use ZPOS\Admin\Setting\Input\Checkbox;
use ZPOS\Admin\Setting\Input\Select;
use ZPOS\Admin\Setting\PostTab;
use ZPOS\Admin\Setting\Sanitize\Boolean;
use ZPOS\Admin\Woocommerce;

class Cart extends PostTab
{
	use Boolean;

	public $name;
	public $path = '/cart';

	public function __construct()
	{
		parent::__construct();
		$this->name = __('Cart', 'zpos-wp-api');
	}

	public function getBoxes()
	{
		$points_rewards_active = Woocommerce\PointsRewards::is_plugin_active();

		return [
			new Box(
				__('Cart', 'zpos-wp-api'),
				null,
				new Checkbox(
					__('Customer', 'zpos-wp-api'),
					'pos_all_customer_fields_required',
					$this->getValue('pos_all_customer_fields_required'),
					__('All Customer Fields are Required to Add a Customer', 'zpos-wp-api'),
					['sanitize' => [$this, 'sanitizeBoolean']]
				),
				new Checkbox(
					'',
					'pos_cart_customer',
					$this->getValue('pos_cart_customer'),
					__('Customer is Required to be Added to the Order for Checkout', 'zpos-wp-api'),
					['sanitize' => [$this, 'sanitizeBoolean']]
				),
				new Checkbox(
					__('Menu Display', 'zpos-wp-api'),
					'pos_cart_menu_display',
					$this->getValue('pos_cart_menu_display'),
					__('Show the Cart menu expanded open by default', 'zpos-wp-api'),
					['sanitize' => [$this, 'sanitizeBoolean']]
				),
				$points_rewards_active
					? new Checkbox(
						__('Points and Rewards', 'zpos-wp-api'),
						'pos_points_rewards',
						$this->getValue('pos_points_rewards'),
						__('Enable Points and Rewards on Checkout', 'zpos-wp-api')
					)
					: null,
				$points_rewards_active
					? new Checkbox(
						__('Order of displaying Points and Rewards', 'zpos-wp-api'),
						'pos_points_rewards_after_tips',
						$this->getValue('pos_points_rewards_after_tips'),
						__('Display Points and Rewards after Tips', 'zpos-wp-api')
					)
					: null,
				$points_rewards_active
					? new Select(
						__('Point and Rewards behavior for guest customer', 'zpos-wp-api'),
						'pos_points_rewards_guest_behavior',
						$this->getValue('pos_points_rewards_guest_behavior'),
						[
							[
								'value' => 'skip',
								'label' => __('Skip displaying', 'zpos-wp-api'),
							],
							[
								'value' => 'select',
								'label' => __('Offer to select customer', 'zpos-wp-api'),
							],
						]
					)
					: null,
				new Checkbox(
					__('Customer Tips', 'zpos-wp-api'),
					'pos_tips',
					$this->getValue('pos_tips'),
					__('Enable Tip on Checkout', 'zpos-wp-api')
				),
				new Checkbox(
					__('Gateway Transaction Fee', 'zpos-wp-api'),
					'pos_gateway_fee',
					$this->getValue('pos_gateway_fee'),
					__('Enable Gateway Transaction Fee', 'zpos-wp-api')
				),
				new Select(
					__('Cart values of zero assigned order status', 'zpos-wp-api'),
					'pos_zero_order_status',
					$this->getValue('pos_zero_order_status'),
					self::get_order_statuses()
				),
				new Select(
					__('Sort products in cart by', 'zpos-wp-api'),
					'pos_cart_sorting',
					$this->getValue('pos_cart_sorting'),
					self::get_sort_values()
				),
				new Checkbox(
					__('Barcode Scanning Cart Action', 'zpos-wp-api'),
					'pos_barcode_automatically_add_to_cart',
					$this->getValue('pos_barcode_automatically_add_to_cart'),
					__('Barcode scan automatically adds item to cart', 'zpos-wp-api'),
					['sanitize' => [$this, 'sanitizeBoolean']]
				),
				new Checkbox(
					'',
					'pos_barcode_repeat_barcode_scans',
					$this->getValue('pos_barcode_repeat_barcode_scans'),
					__('Enable repeat Barcode scans functionality', 'zpos-wp-api'),
					['sanitize' => [$this, 'sanitizeBoolean']]
				)
			),
		];
	}

	public static function getDefaultValue($value, $post, $name)
	{
		switch ($name) {
			case 'pos_zero_order_status':
				return 'completed';
			case 'pos_cart_sorting':
				$keys = array_map(function ($option) {
					return $option['value'];
				}, self::get_sort_values());
				return $keys[0];
			case 'pos_points_rewards_guest_behavior':
				return 'skip';
			case 'pos_points_rewards':
			case 'pos_points_rewards_after_tips':
			case 'pos_tips':
			case 'pos_cart_menu_display':
			case 'pos_all_customer_fields_required':
			case 'pos_cart_customer':
			case 'pos_barcode_automatically_add_to_cart':
			case 'pos_barcode_repeat_barcode_scans':
			case 'pos_gateway_fee':
				return false;
			default:
				return $value;
		}
	}

	public static function get_order_statuses(): array
	{
		$statuses = [];

		foreach (wc_get_order_statuses() as $status_key => $status_name) {
			$statuses[] = [
				'value' => str_replace('wc-', '', $status_key),
				'label' => $status_name,
			];
		}

		return $statuses;
	}

	public static function get_sort_values(): array
	{
		return [
			[
				'value' => 'price_desc',
				'label' => __('Sort by price: high to low', 'zpos-wp-api'),
			],
			[
				'value' => 'price_asc',
				'label' => __('Sort by price: low to high', 'zpos-wp-api'),
			],
			[
				'value' => 'time_desc',
				'label' => __('Sort by items added to cart: newer is higher', 'zpos-wp-api'),
			],
			[
				'value' => 'time_asc',
				'label' => __('Sort by items added to cart: older is higher', 'zpos-wp-api'),
			],
		];
	}
}
