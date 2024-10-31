<?php

namespace ZPOS\Model;

use WC_Product;
use WP_REST_Response;
use ZPOS\Station;

class ProductInventory
{
	protected $station;
	protected $product;
	protected $quantity_key;
	protected $location_quantity_key;

	public function __construct(WC_Product $product, Station $station)
	{
		$this->product = $product;
		$this->station = $station;
		$this->quantity_key = 'pos_quantity_' . $station->getID();
		$this->location_quantity_key = 'pos_quantity_' . $this->get_location();
	}

	public function get_location_quantity(): ?int
	{
		if ($this->is_base_location()) {
			return $this->product->get_stock_quantity();
		}

		if ($this->is_variation_not_managing_stock()) {
			$parent = $this->get_parent();

			if ($parent->managing_stock()) {
				return (new static($parent, $this->station))->get_location_quantity();
			}
		}

		if (!$this->product->managing_stock()) {
			return null;
		}

		if (!$this->is_station_location()) {
			return $this->get_quantity(true);
		}

		return $this->get_quantity();
	}

	public function get_quantity(bool $from_location = false): int
	{
		$value = get_post_meta(
			$this->product->get_id(),
			$from_location ? $this->location_quantity_key : $this->quantity_key,
			true
		);

		return empty($value) ? 0 : intval($value);
	}

	/**
	 * @return int|bool
	 */
	public function update_location_quantity(int $quantity)
	{
		if ($this->is_base_location()) {
			$this->product->set_stock_quantity($quantity);
			$this->product->set_stock_status(0 < $quantity ? 'instock' : 'outofstock');

			return $this->product->save();
		}

		if ($this->is_variation_not_managing_stock()) {
			$parent = $this->get_parent();

			if ($parent->managing_stock()) {
				return (new static($parent, $this->station))->update_location_quantity($quantity);
			}
		}

		if (!$this->is_station_location()) {
			return $this->update_quantity($quantity, true);
		}

		return $this->update_quantity($quantity);
	}

	/**
	 * @return int|bool
	 */
	public function update_quantity(int $quantity, bool $for_location = false)
	{
		return update_post_meta(
			$this->product->get_id(),
			$for_location ? $this->location_quantity_key : $this->quantity_key,
			$quantity
		);
	}

	public function get_quantity_key(): string
	{
		return $this->quantity_key;
	}

	public function apply_data_to_response(array $product_data): array
	{
		if ($this->is_base_location()) {
			return $product_data;
		}

		$quantity = $this->get_location_quantity();
		$in_stock = null === $quantity || 0 < $quantity;

		return array_merge($product_data, [
			'stock_quantity' => $quantity,
			'in_stock' => $in_stock,
			'stock_status' => $in_stock ? 'instock' : 'outofstock',
			'manage_stock' => $this->product->managing_stock() || $this->get_parent()->managing_stock(),
		]);
	}

	public function is_base_location(): bool
	{
		return 0 === $this->get_location();
	}

	protected function is_station_location(): bool
	{
		return $this->station->getID() === $this->get_location();
	}

	protected function get_location(): int
	{
		$station_id = $this->station->getID();
		$location_id = $this->station->getData('pos_inventory_location');

		if (Station::isWCStation($station_id) || empty($location_id)) {
			return 0;
		}

		if (is_numeric($location_id)) {
			return intval($location_id);
		}

		return $station_id;
	}

	protected function is_variation_not_managing_stock(): bool
	{
		return !$this->product->managing_stock() && 'variation' === $this->product->get_type();
	}

	protected function get_parent(): WC_Product
	{
		return 'variation' === $this->product->get_type()
			? wc_get_product($this->product->get_parent_id())
			: $this->product;
	}
}
