<?php

namespace ZPOS\Admin\Tabs;

use ZPOS\Admin\Setting\Box;
use ZPOS\Admin\Setting\Input\Description;
use ZPOS\Admin\Setting\Input\Media;
use ZPOS\Admin\Setting\Input\Number;
use ZPOS\Admin\Setting\PageTab;
use ZPOS\Deactivate;

class General extends PageTab
{
	public $exact = true;
	public $name = 'General';
	public $path = '/general';

	public function getBoxes(): array
	{
		return [
			new Box(
				null,
				null,
				new Media(__('Loading Screen Logo', 'zpos-wp-api'), 'pos_logo', [$this, 'get_media_value'])
			),
			new Box(
				__('Sync Behavior', 'zpos-wp-api'),
				null,
				new Description(
					__(
						'Define Number of products syncing per request. Optimize based upon server performance',
						'zpos-wp-api'
					)
				),
				new Number(
					null,
					'pos_product_sync_initial',
					[$this, 'get_product_sync_initial'],
					[
						'title' => __('Product Sync on initial load', 'zpos-wp-api'),
						'inputDescription' => __('per request (Maximum: 100)', 'zpos-wp-api'),
						'min' => '1',
						'max' => '100',
					]
				),
				new Number(
					null,
					'pos_product_sync_background',
					[$this, 'get_product_sync_background'],
					[
						'title' => __('Product Sync on background', 'zpos-wp-api'),
						'inputDescription' => __('per request (Maximum: 100)', 'zpos-wp-api'),
						'min' => '1',
						'max' => '100',
					]
				)
			),
		];
	}

	public function init(): void
	{
		register_setting('pos' . $this->path, 'pos_logo');
		register_setting('pos' . $this->path, 'pos_product_sync_initial');
		register_setting('pos' . $this->path, 'pos_product_sync_background');
	}

	public function get_media_value(): array
	{
		$id = get_option('pos_logo');
		$src = null;
		if ($id) {
			$src_data = wp_get_attachment_image_src($id, 'full');
			$src = is_array($src_data) ? $src_data[0] : '';
		}

		return compact('id', 'src');
	}

	public function get_product_sync_initial(): int
	{
		return static::get_product_sync('initial');
	}

	public function get_product_sync_background(): int
	{
		return static::get_product_sync('background');
	}

	public static function get_product_sync(string $type): int
	{
		$default = 100;
		$option = get_option("pos_product_sync_$type", $default);

		if (!is_numeric($option)) {
			return $default;
		}

		$option = intval($option);

		if (1 > $option) {
			return 1;
		}
		if (100 < $option) {
			return $default;
		}

		return $option;
	}

	public static function reset(): void
	{
		if (!did_action(Deactivate::class . '::resetSettings')) {
			_doing_it_wrong(
				__METHOD__,
				'Reset POS settings should called by ' . Deactivate::class . '::resetSettings',
				'2.0.3'
			);
			return;
		}

		delete_option('pos_logo');
		delete_option('pos_product_sync_initial');
		delete_option('pos_product_sync_background');
	}
}
