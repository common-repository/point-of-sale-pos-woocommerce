<?php

namespace ZPOS\Gateway;

use WC_Order;
use ZPOS\Model\Gateway;

class GiftCard extends Base
{
	public $id = 'pos_gift_card';
	public $method_title = 'Gift Cards';
	public $has_fields = true;
	public $supports = ['products', 'pos'];

	public static function getID(): string
	{
		return 'pos_gift_card';
	}

	public function __construct()
	{
		parent::__construct();

		$this->title = $this->get_option('title');
		$this->description = $this->get_option('description');

		add_action('woocommerce_pos_update_options_payment_gateways_' . $this->id, [
			$this,
			'process_admin_options',
		]);
	}

	/**
	 * @param int|WC_Order $order_id
	 */
	public function process_payment($order_id): array
	{
		if ($order_id instanceof WC_Order) {
			$order = $order_id;
		} else {
			$order = new WC_Order($order_id);
		}

		if (!SplitPayment::is_split_payment()) {
			$order->update_status(Gateway::getGatewayOrderStatus($this->id));
		}

		return [
			'result' => 'success',
		];
	}
}
