<?php

namespace ZPOS\Admin\Woocommerce;

use WC_Product;
use WP_Post;
use ZPOS\Model\ProductInventory;
use ZPOS\Plugin;
use ZPOS\Station;
use const ZPOS\PLUGIN_VERSION;

class Products
{
	const DEFAULT_PRODUCT_NAME_COLOR = '#1E1E1E';
	const DEFAULT_PRODUCT_SUB_TEXT_COLOR = '#737373';
	const DEFAULT_PRODUCT_PRICE_COLOR = '#1E1E1E';
	const DEFAULT_PRODUCT_OUT_OF_STOCK_COLOR = '#FF4221';
	const DEFAULT_PRODUCT_TILE_BACKGROUND_COLOR = '#FFFFFF';

	const PRODUCT_OVERRIDE_GLOBAL_STYLING_NAME = 'pos_product_override_global_styling';
	const PRODUCT_NAME_COLOR_NAME = 'pos_product_name_color';
	const PRODUCT_SUB_TEXT_COLOR_NAME = 'pos_product_sub_text_color';
	const PRODUCT_HIDE_SUB_TEXT_NAME = 'pos_hide_sub_text';
	const PRODUCT_PRICE_COLOR_NAME = 'pos_product_price_color';
	const PRODUCT_HIDE_PRICE_NAME = 'pos_hide_product_price';
	const PRODUCT_OUT_OF_STOCK_COLOR_NAME = 'pos_out_of_stock_text_color';
	const PRODUCT_TILE_BACKGROUND_COLOR_NAME = 'pos_tile_background_color';

	const PRODUCT_BARCODE_NAME = 'pos_barcode';
	const PRODUCT_BARCODE_SECONDARY_NAME = 'pos_barcode_secondary';
	const PRODUCT_BARCODE_ALTERNATIVE_NAME = 'pos_barcode_alternative';
	const PRODUCT_VARIATION_BARCODE_NAME = 'pos_variation_barcode';
	const PRODUCT_VARIATION_BARCODE_SECONDARY_NAME = 'pos_variation_barcode_secondary';
	const PRODUCT_VARIATION_BARCODE_ALTERNATIVE_NAME = 'pos_variation_barcode_alternative';

	const VISIBILITY_OPTION_NAME = 'pos';
	const VISIBILITY_META_NAME = 'pos_visibility';

	public function __construct()
	{
		add_action('woocommerce_product_visibility_options', [$this, 'add_visibility_options']);
		add_action('woocommerce_product_set_visibility', [$this, 'set_visibility'], 10, 2);
		add_filter(
			'woocommerce_product_export_product_column_catalog_visibility',
			[$this, 'add_visibility_to_csv_export'],
			10,
			2
		);
		add_filter(
			'woocommerce_product_import_pre_insert_product_object',
			[$this, 'add_visibility_to_csv_import'],
			10,
			2
		);
		add_action('woocommerce_product_options_inventory_product_data', [
			$this,
			'add_fields_to_simple_product',
		]);
		add_action('woocommerce_variation_options', [$this, 'add_fields_to_variable_product'], 1, 3);
		add_action('woocommerce_product_options_stock_fields', [$this, 'add_stock_fields']);
		add_action(
			'woocommerce_variation_options_inventory',
			[$this, 'add_variation_stock_fields'],
			10,
			3
		);
		add_action('woocommerce_process_product_meta', [$this, 'process_product_meta']);
		add_action('woocommerce_save_product_variation', [$this, 'save_product_variation'], 10, 2);
		add_action('add_meta_boxes', [$this, 'render_meta_boxes']);
		add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
	}

	public static function get_visibility(int $product_id): string
	{
		$visibility = get_post_meta($product_id, self::VISIBILITY_META_NAME, true);

		return empty($visibility) ? 'visible' : $visibility;
	}

	public function add_visibility_options(array $options): array
	{
		$options['visible'] = __('Shop, POS and search result', 'zpos-wp-api');
		$position = 2;

		return array_slice($options, 0, $position, true) + [
				self::VISIBILITY_OPTION_NAME => __('POS only', 'zpos-wp-api'),
			] +
			array_slice($options, $position, count($options) - $position, true);
	}

	public function set_visibility(int $product_id, string $catalog_visibility): void
	{
		update_post_meta($product_id, self::VISIBILITY_META_NAME, $catalog_visibility);

		if (self::VISIBILITY_OPTION_NAME !== $catalog_visibility) {
			return;
		}

		$this->set_catalog_visibility_hidden($product_id);
	}

	public function add_visibility_to_csv_export(string $value, \WC_Product $product): string
	{
		$visibility = get_post_meta($product->get_id(), self::VISIBILITY_META_NAME, true);

		if ($visibility) {
			return $visibility;
		}

		return $product->get_catalog_visibility();
	}

	public function add_visibility_to_csv_import(\WC_Product $product, array $data): \WC_Product
	{
		if (self::VISIBILITY_OPTION_NAME !== $data['catalog_visibility']) {
			return $product;
		}

		$product_id = $product->get_id();

		update_post_meta($product_id, self::VISIBILITY_META_NAME, self::VISIBILITY_OPTION_NAME);
		$this->set_catalog_visibility_hidden($product_id);

		return $product;
	}

	/**
	 * @return array|false|\WP_Error
	 */
	private function set_catalog_visibility_hidden(int $product_id)
	{
		return wp_set_post_terms(
			$product_id,
			['exclude-from-search', 'exclude-from-catalog'],
			'product_visibility'
		);
	}

	public function add_fields_to_simple_product(): void
	{
		$this->add_barcode_fields();
	}

	public function add_fields_to_variable_product(
		int $loop,
		array $variation_data,
		WP_Post $variation
	): void {
		$this->add_barcode_fields($loop, $variation->ID);
	}

	protected function add_barcode_fields($loop_id = null, $variation_id = null): void
	{
		$this->add_barcode_field(
			__('Barcode', 'zpos-wp-api'),
			$loop_id,
			$variation_id,
			self::PRODUCT_BARCODE_NAME,
			self::PRODUCT_VARIATION_BARCODE_NAME
		);
		$this->add_barcode_field(
			__('Barcode secondary', 'zpos-wp-api'),
			$loop_id,
			$variation_id,
			self::PRODUCT_BARCODE_SECONDARY_NAME,
			self::PRODUCT_VARIATION_BARCODE_SECONDARY_NAME
		);
		$this->add_barcode_field(
			__('Barcode alternative', 'zpos-wp-api'),
			$loop_id,
			$variation_id,
			self::PRODUCT_BARCODE_ALTERNATIVE_NAME,
			self::PRODUCT_VARIATION_BARCODE_ALTERNATIVE_NAME
		);
	}

	public function add_barcode_field(
		$label,
		$loop_id,
		$variation_id,
		$barcode_field_name,
		$variation_barcode_field_name
	): void {
		$barcode_field = [
			'id' =>
				$loop_id !== null
					? $variation_barcode_field_name . '[' . $loop_id . ']'
					: $barcode_field_name,
			'label' => $label,
			'description' => __(
				'The barcode refers to numbers and letters assigned to products as a means of identification by the POS using a barcode reader.',
				'zpos-wp-api'
			),
			'desc_tip' => true,
		];

		if ($variation_id !== null) {
			$barcode_field['value'] = get_post_meta($variation_id, $variation_barcode_field_name, true);
			$barcode_field['style'] = 'width: 100%';
		}
		woocommerce_wp_text_input($barcode_field);
	}

	protected function get_stock_fields_data(int $product_id): array
	{
		$product = wc_get_product($product_id);

		if (!$product instanceof WC_Product) {
			return [];
		}

		return array_map(function (WP_Post $station_post) use ($product): array {
			$station = new Station($station_post->ID);
			$inventory = new ProductInventory($product, $station);

			return [
				'name' => $inventory->get_quantity_key(),
				'label' => $station_post->post_title . ' (' . __('Quantity', 'woocommerce') . ')',
				'value' => $inventory->get_quantity(),
				'inventory' => $inventory,
			];
		}, Station::get_all(true));
	}

	public function add_stock_fields(): void
	{
		if (empty($_GET['post'])) {
			return;
		}

		foreach ($this->get_stock_fields_data(intval(wp_unslash($_GET['post']))) as $field) {
			woocommerce_wp_text_input([
				'id' => $field['name'],
				'value' => $field['value'],
				'label' => $field['label'],
				'desc_tip' => true,
				'description' => __(
					'Stock quantity. If this is a variable product this value will be used to control stock for all variations, unless you define stock at variation level.',
					'woocommerce'
				),
				'type' => 'number',
				'custom_attributes' => [
					'step' => 'any',
				],
				'data_type' => 'stock',
			]);
		}
	}

	public function add_variation_stock_fields(
		int $loop,
		array $variation_data,
		WP_Post $variation
	): void {
		$i = 1;
		foreach ($this->get_stock_fields_data($variation->ID) as $field) {
			woocommerce_wp_text_input([
				'id' => $field['name'] . $loop,
				'name' => $field['name'] . "[$loop]",
				'value' => $field['value'],
				'label' => $field['label'],
				'desc_tip' => true,
				'description' => __(
					"Enter a number to set stock quantity at the variation level. Use a variation's 'Manage stock?' check box above to enable/disable stock management at the variation level.",
					'woocommerce'
				),
				'type' => 'number',
				'custom_attributes' => [
					'step' => 'any',
				],
				'data_type' => 'stock',
				'wrapper_class' => 'form-row ' . ($i % 2 == 0 ? 'form-row-last' : 'form-row-first'),
			]);
			$i++;
		}
	}

	public function process_product_meta(int $product_id): void
	{
		$names_to_update = [
			self::PRODUCT_BARCODE_NAME,
			self::PRODUCT_BARCODE_SECONDARY_NAME,
			self::PRODUCT_BARCODE_ALTERNATIVE_NAME,
			self::PRODUCT_OVERRIDE_GLOBAL_STYLING_NAME,
			self::PRODUCT_NAME_COLOR_NAME,
			self::PRODUCT_SUB_TEXT_COLOR_NAME,
			self::PRODUCT_HIDE_SUB_TEXT_NAME,
			self::PRODUCT_PRICE_COLOR_NAME,
			self::PRODUCT_HIDE_PRICE_NAME,
			self::PRODUCT_OUT_OF_STOCK_COLOR_NAME,
			self::PRODUCT_TILE_BACKGROUND_COLOR_NAME,
		];

		$checkbox_fields = [
			self::PRODUCT_OVERRIDE_GLOBAL_STYLING_NAME,
			self::PRODUCT_HIDE_SUB_TEXT_NAME,
			self::PRODUCT_HIDE_PRICE_NAME,
		];

		foreach ($names_to_update as $name) {
			if (isset($_POST[$name])) {
				update_post_meta($product_id, $name, esc_attr($_POST[$name]));
			} elseif (in_array($name, $checkbox_fields)) {
				update_post_meta($product_id, $name, '');
			}
		}

		foreach ($this->get_stock_fields_data($product_id) as $quantity_field) {
			/**
			 * @var ProductInventory $inventory
			 */
			$inventory = $quantity_field['inventory'];

			if (isset($_POST[$inventory->get_quantity_key()])) {
				$inventory->update_quantity(intval(wp_unslash($_POST[$inventory->get_quantity_key()])));
			}
		}
	}

	public function save_product_variation(int $variation_id, int $loop): void
	{
		$barcode = $_POST['pos_variation_barcode'][$loop];
		if (isset($barcode)) {
			update_post_meta($variation_id, 'pos_variation_barcode', esc_attr($barcode));
		}

		foreach ($this->get_stock_fields_data($variation_id) as $quantity_field) {
			/**
			 * @var ProductInventory $inventory
			 */
			$inventory = $quantity_field['inventory'];

			if (isset($_POST[$inventory->get_quantity_key()][$loop])) {
				$inventory->update_quantity(
					intval(wp_unslash($_POST[$inventory->get_quantity_key()][$loop]))
				);
			}
		}
	}

	public function render_meta_boxes(): void
	{
		add_meta_box(
			'zpos_product_styling',
			__('POS product tile', 'order-hours-scheduler-delivery-woocommerce'),
			function ($post) {
				$values = get_post_custom($post->id);
				$inputs = self::get_stylization_inputs();
				?>
						<div id="zpos-product-styling">
								<?php foreach ($inputs as $input) {
        	$value = isset($values[$input['name']])
        		? $values[$input['name']][0]
        		: $input['default_value']; ?>
										<div class="zpos-styling-row">
												<div class="zpos-styling-column">
														<span><?= $input['label'] ?></span>
														<?php if (isset($input['description'])) { ?>
															<span class="zpos-styling-column-description"><?= $input['description'] ?></span>
														<?php } ?>
												</div>
												<div class="zpos-styling-column">
														<?php if (isset($input['checkbox']) && $input['checkbox']) { ?>
																<input type="checkbox" name="<?= $input['name'] ?>" <?= $value ? 'checked' : '' ?> />
														<?php } else { ?>
																<input type="text" class="zpos-color-picker" name="<?= $input['name'] ?>" value="<?= $value ?>" />
														<?php } ?>
												</div>
										</div>
										<?php
        } ?>
						</div>
						<?php
			},
			'product',
			'side',
			'low'
		);
	}

	public static function get_stylization_inputs(): array
	{
		return [
			[
				'label' => __('Override Global Color Styling', 'zpos-wp-api'),
				'name' => self::PRODUCT_OVERRIDE_GLOBAL_STYLING_NAME,
				'default_value' => false,
				'checkbox' => true,
			],
			[
				'label' => __('Product name', 'zpos-wp-api'),
				'name' => self::PRODUCT_NAME_COLOR_NAME,
				'default_value' => self::DEFAULT_PRODUCT_NAME_COLOR,
			],
			[
				'label' => __('Product sub text', 'zpos-wp-api'),
				'name' => self::PRODUCT_SUB_TEXT_COLOR_NAME,
				'default_value' => self::DEFAULT_PRODUCT_SUB_TEXT_COLOR,
				'description' => __('Variation/Inventory counts', 'zpos-wp-api'),
			],
			[
				'label' => __('Hide sub text', 'zpos-wp-api'),
				'name' => self::PRODUCT_HIDE_SUB_TEXT_NAME,
				'default_value' => false,
				'checkbox' => true,
			],
			[
				'label' => __('Product price', 'zpos-wp-api'),
				'name' => self::PRODUCT_PRICE_COLOR_NAME,
				'default_value' => self::DEFAULT_PRODUCT_PRICE_COLOR,
			],
			[
				'label' => __('Hide product price', 'zpos-wp-api'),
				'name' => self::PRODUCT_HIDE_PRICE_NAME,
				'default_value' => false,
				'checkbox' => true,
			],
			[
				'label' => __('Out of stock text', 'zpos-wp-api'),
				'name' => self::PRODUCT_OUT_OF_STOCK_COLOR_NAME,
				'default_value' => self::DEFAULT_PRODUCT_OUT_OF_STOCK_COLOR,
			],
			[
				'label' => __('Tile background', 'zpos-wp-api'),
				'name' => self::PRODUCT_TILE_BACKGROUND_COLOR_NAME,
				'default_value' => self::DEFAULT_PRODUCT_TILE_BACKGROUND_COLOR,
			],
		];
	}

	public static function get_stylization(\WC_Product $product): array
	{
		$styles = self::get_stylization_inputs();
		$product_id = $product->is_type('variation') ? $product->get_parent_id() : $product->get_id();
		$response = [];
		foreach ($styles as $style) {
			$name = $style['name'];
			$value = get_post_meta($product_id, $name, true);
			$response[$name] = $value ?: $style['default_value'];
		}
		return $response;
	}

	public function enqueue_assets(): void
	{
		global $current_screen;

		wp_enqueue_style('wp-color-picker');
		wp_enqueue_script(
			'zpos_color_picker',
			Plugin::getUrl('assets/admin/colorPicker.js'),
			['jquery', 'wp-color-picker'],
			'1.0',
			true
		);
		wp_enqueue_style('zpos_admin_styles', Plugin::getUrl('assets/admin/style.css'));

		if (
			'product' !== $current_screen->post_type ||
			'post' !== $current_screen->base ||
			empty($_GET['post'])
		) {
			return;
		}

		wp_enqueue_script(
			'zpos_admin_product',
			Plugin::getUrl('assets/admin/admin-product.js'),
			['jquery'],
			PLUGIN_VERSION
		);
		wp_localize_script('zpos_admin_product', 'zposAdminProduct', [
			'visibility' => get_post_meta(
				sanitize_text_field(wp_unslash($_GET['post'])),
				self::VISIBILITY_META_NAME,
				true
			),
		]);
	}
}
