<?php

namespace ZPOS\Admin\Tabs;

use ZPOS\Admin\Setting\Box;
use ZPOS\Admin\Setting\Input\Description;
use ZPOS\Admin\Setting\Input\GatewayArray;
use ZPOS\Admin\Setting\PageTab;
use ZPOS\Deactivate;
use ZPOS\Gateway\Cash;
use ZPOS\Gateway\Check;
use ZPOS\Gateway\EPD;
use ZPOS\Gateway\Stripe;
use ZPOS\Gateway\StripeConnect;
use ZPOS\Gateway\StripeConnectTerminal;
use ZPOS\Gateway\GiftCard;
use ZPOS\Gateway\QRCode;
use ZPOS\Gateway\SplitPayment;
use ZPOS\Gateway\CashDelivery;
use ZPOS\Gateway\BankTransfer;
use ZPOS\Gateway\ChipPin;
use ZPOS\Gateway\Smart;
use ZPOS\Plugin;
use ZPOS\Model;

class Gateway extends PageTab
{
	public $name = 'Gateway';
	public $path = '/gateway';

	public function getBoxes()
	{
		return [
			new Box(
				null,
				null,

				new Description(
					sprintf(
						'<div style="margin: 1.33em 0"><h4 style="display: inline">%s</h4> %s</div>',
						__('Payment Gateways', 'zpos-wp-api'),
						__(
							'Installed gateways are listed below. Drag and drop gateways to control their display order on the Point of Sale.',
							'zpos-wp-api'
						)
					)
				),
				new Description(
					sprintf(
						'<div style="margin: 0.6em 0"><h4 style="display: inline; color: grey; font-weight: inherit;">%s</h4> %s</div>',
						__('Native Gateway Support', 'zpos-wp-api'),
						__('Payment Gateways enabled will be available for the Point of Sale.', 'zpos-wp-api')
					)
				),
				new GatewayArray('', 'pos_gateways', [$this, 'get_payment_value']),
				Plugin::isActive('wc-pos-gateways')
					? null
					: new Description(
						sprintf(
							'<div style="margin: 1.33em 0"><h4 style="display: inline;">%s</h4> %s <a href="%s" target="_blank">%s</a></div>',
							__('Expand Payment Gateway Support with', 'zpos-wp-api'),
							__('Pay by Woo bridge', 'zpos-wp-api'),
							'https://jovvie.com/payments/',
							__('Learn More', 'zpos-wp-api')
						)
					)
			),
		];
	}

	public function init()
	{
		register_setting('pos' . $this->path, 'pos_gateways', [
			'default' => $this->get_default_list_options(),
		]);
		register_setting('pos' . $this->path, 'pos_gateways_default', [
			'default' => Cash::getID(),
		]);

		array_map([$this, 'register_gateway_setting'], Model\Gateway::posGateways());
	}

	private function get_default_list_options(): array
	{
		$options = [
			Cash::getID() => [
				'order' => 0,
				'pos' => true,
				'order_status' => 'completed',
			],
			Check::getID() => [
				'order' => 1,
				'pos' => true,
			],
			EPD::getID() => [
				'order' => 2,
				'pos' => true,
			],
			StripeConnect::getID() => [
				'order' => 3,
				'pos' => true,
			],
			StripeConnectTerminal::getID() => [
				'order' => 4,
				'pos' => true,
			],
			GiftCard::getID() => [
				'order' => 5,
				'pos' => true,
			],
			QRCode::getID() => [
				'order' => 6,
				'pos' => true,
			],
			SplitPayment::getID() => [
				'order' => 7,
				'pos' => true,
			],
			CashDelivery::getID() => [
				'order' => 8,
				'pos' => false,
			],
			BankTransfer::getID() => [
				'order' => 9,
				'pos' => false,
			],
			ChipPin::getID() => [
				'order' => 10,
				'pos' => false,
			],
			Smart::getID() => [
				'order' => 11,
				'pos' => false,
			],
		];

		if (class_exists('\ZStripeTerminalPOS\PaymentMethod')) {
			$options[\ZStripeTerminalPOS\PaymentMethod::getID()] = [
				'order' => 4,
				'pos' => true,
			];
		}

		return $options;
	}

	private function register_gateway_setting($gateway)
	{
		list($id, $option) = call_user_func([$gateway, 'getInfo']);
		register_setting('pos' . $this->path . '/' . $id, $option);
	}

	public function get_payment_value()
	{
		$gateways = Model\Gateway::get_available_gateways(false);
		$gateways = array_filter($gateways, function ($gateway) {
			return in_array('pos', $gateway->supports);
		});

		$data = array_map([$this, 'prepare_gateway_data'], $gateways);
		usort($data, function ($a, $b) {
			$a_order = Model\Gateway::get_sort_order($a['id']);
			$b_order = Model\Gateway::get_sort_order($b['id']);

			return $a_order - $b_order;
		});

		return $data;
	}

	private function prepare_gateway_data(\WC_Payment_Gateway $gateway)
	{
		return [
			'id' => $gateway->id,
			'method_title' => $gateway->method_title,
			'title' => $gateway->title,
			'description' => $gateway->description,
			'default' => get_option('pos_gateways_default') === $gateway->id,
			'name' => $gateway->get_option_key(),
			'pos' => Model\Gateway::isGatewayEnabled($gateway->id),
			'order_status' => Model\Gateway::getGatewayOrderStatus($gateway->id),
			'gateway_settings_fields' => $this->gateway_settings_fields($gateway),
			'setting' => $gateway->supports('pos')
				? null
				: admin_url('admin.php?page=wc-settings&tab=checkout&section=' . strtolower($gateway->id)),
			'support' => $gateway->supports('pos'),
			'fee_settings' => Model\Gateway::get_fee_settings($gateway),
			'values' => $gateway->getValues(),
		];
	}

	public function gateway_settings_fields(\WC_Payment_Gateway $gateway)
	{
		ob_start();
		settings_fields('pos' . $this->path . '/' . $gateway->id);
		$data = ob_get_contents();
		ob_end_clean();

		return $data;
	}

	public static function reset()
	{
		if (!did_action(Deactivate::class . '::resetSettings')) {
			return _doing_it_wrong(
				__METHOD__,
				'Reset POS settings should called by ' . Deactivate::class . '::resetSettings',
				'2.0.3'
			);
		}

		delete_option('pos_gateways');
		delete_option('pos_gateways_default');

		$gateways = Model\Gateway::get_available_gateways(false);
		$gateways = array_filter($gateways, function ($gateway) {
			return in_array('pos', $gateway->supports);
		});
		foreach ($gateways as $gateway) {
			delete_option($gateway->get_option_key());
		}
	}
}
