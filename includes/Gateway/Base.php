<?php

namespace ZPOS\Gateway;

use WC_Payment_Gateway;
use ZPOS\Model;

abstract class Base extends WC_Payment_Gateway
{
	public function __construct()
	{
		add_filter('default_option_' . $this->get_option_key(), [$this, 'get_default_option_values']);
		add_filter('pre_update_option_' . $this->get_option_key(), [$this, 'sanitize_option_values']);
	}

	abstract public static function getID(): string;

	public function getValues()
	{
		return [
			'title' => $this->title,
			'description' => $this->description,
		];
	}

	public static function getInfo(): array
	{
		$id = static::getID();
		$option = 'woocommerce_' . $id . '_settings';

		return [$id, $option];
	}

	public function get_option_key(): string
	{
		return 'woocommerce_' . $this->id . '_settings';
	}

	public function get_default_option_values(): array
	{
		return [
			'title' => $this->method_title,
			'description' => $this->method_description,
			'enabled' => 'yes',
			'fee_label' => 'Transaction Fee',
			'tax_class' => array_keys(Model\Gateway::get_available_tax_classes())[0],
			'percent_fee_amount' => 0,
			'fixed_fee_amount' => 0,
			'fee_cart_max' => 0,
			'fee_cart_min' => 0,
		];
	}

	public function sanitize_option_values(array $option): array
	{
		foreach ($option as $key => $value) {
			switch ($key) {
				case 'title':
				case 'description':
				case 'fee_label':
					$option[$key] = sanitize_text_field(wp_unslash($value));
					break;
				case 'percent_fee_amount':
				case 'fixed_fee_amount':
					$option[$key] = floatval(wp_unslash($value));
					break;
				case 'fee_cart_max':
				case 'fee_cart_min':
					$option[$key] = max(0, intval(wp_unslash($value)));
					break;
				case 'tax_class':
					$option[$key] = $this->validate_selected_value(
						sanitize_text_field(wp_unslash($value)),
						array_keys(Model\Gateway::get_available_tax_classes())
					);
					break;
				default:
					$option[$key] = $this->sanitize_additional_option_value($key, $value);
					break;
			}
		}

		return $option;
	}

	/**
	 * @param $value mixed
	 * @return mixed
	 */
	protected function sanitize_additional_option_value(string $key, $value)
	{
		return '';
	}

	protected function validate_selected_value(string $value, array $available): string
	{
		if (in_array($value, $available, true)) {
			return $value;
		}

		return $available[0];
	}
}
