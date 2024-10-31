<?php

namespace ZPOS\Model;

use WC_Payment_Gateway;
use ZPOS\Gateway\BankTransfer;
use ZPOS\Gateway\Cash;
use ZPOS\Gateway\CashDelivery;
use ZPOS\Gateway\Check;
use ZPOS\Gateway\ChipPin;
use ZPOS\Gateway\EPD;
use ZPOS\Gateway\GiftCard;
use ZPOS\Gateway\QRCode;
use ZPOS\Gateway\Smart;
use ZPOS\Gateway\SplitPayment;
use ZPOS\Gateway\StripeConnect;
use ZPOS\Gateway\StripeConnectTerminal;

class Gateway
{
	public function __construct()
	{
		add_action('woocommerce_thankyou', [$this, 'add_payment_complete_by']);
	}

	public static function registerPOSGateway(string $classGateway): void
	{
		add_filter('zpos_support_gateways', function ($pos_gateways) use ($classGateway) {
			return array_merge($pos_gateways, [$classGateway]);
		});
	}

	public static function posGateways(): array
	{
		$pos_gateways = [
			Cash::class,
			Smart::class,
			EPD::class,
			Check::class,
			ChipPin::class,
			BankTransfer::class,
			CashDelivery::class,
			GiftCard::class,
			QRCode::class,
			SplitPayment::class,
			StripeConnect::class,
			StripeConnectTerminal::class,
		];

		return apply_filters('zpos_support_gateways', $pos_gateways);
	}

	public static function get_available_gateways(bool $enabled_only = true): array
	{
		$gateways = [];

		foreach (static::posGateways() as $gateway_class) {
			/**
			 * @var WC_Payment_Gateway $gateway
			 */
			$gateway = new $gateway_class();

			if (!$enabled_only || static::isGatewayEnabled($gateway->id)) {
				$gateways[$gateway->id] = $gateway;
			}
		}
		return $gateways;
	}

	public static function isGatewayEnabled(string $id): bool
	{
		$gateways_data = static::get_gateway_data($id);
		return isset($gateways_data['pos']) && $gateways_data['pos'];
	}

	public static function getGatewayOrderStatus(string $id): string
	{
		if (
			in_array($id, [
				'pos_stripe',
				'pos_stripe_terminal',
				'pos_stripe_connect',
				'pos_stripe_connect_terminal',
			])
		) {
			return 'completed';
		}

		return static::get_gateway_data($id)['order_status'] ?? 'processing';
	}

	public static function get_gateway_data(string $id): array
	{
		return get_option('pos_gateways')[$id] ?? [];
	}

	public static function get_sort_order(string $id): int
	{
		$gateways_data = static::get_gateway_data($id);
		return isset($gateways_data['order']) ? (int) $gateways_data['order'] : 9999;
	}

	public function add_payment_complete_by(int $order_id): void
	{
		$order = wc_get_order($order_id);

		if (empty($order->get_meta('_pos_by'))) {
			return;
		}

		$order->update_meta_data('pos_payment_complete_by', $order->get_payment_method());
		$order->save();
	}

	public static function get_fee_settings(WC_Payment_Gateway $gateway): array
	{
		return [
			'fee_label' => $gateway->get_option('fee_label'),
			'tax_class' => $gateway->get_option('tax_class'),
			'percent_fee_amount' => $gateway->get_option('percent_fee_amount'),
			'fixed_fee_amount' => $gateway->get_option('fixed_fee_amount'),
			'fee_cart_max' => $gateway->get_option('fee_cart_max'),
			'fee_cart_min' => $gateway->get_option('fee_cart_min'),
		];
	}

	public static function get_available_tax_classes(): array
	{
		$tax_classes = [
			'none' => [
				'value' => 'none',
				'title' => 'None',
			],
		];

		foreach (wc_get_product_tax_class_options() as $value => $title) {
			if ('Standard' === $title) {
				$value = 'standard';
			}

			$tax_classes[$value] = [
				'value' => $value,
				'title' => $title,
			];
		}

		return $tax_classes;
	}
}
