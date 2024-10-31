<?php

namespace ZPOS\Gateway;

use ZPOS\API;
use ZPOS\StripeConnectRestClient;
use const ZPOS\PLUGIN_NAME;

class StripeConnectTerminal extends AbstractStripeConnect
{
	public $id = 'pos_stripe_connect_terminal';
	public $method_title = 'Stripe Connect Terminal';
	public $method_description = '';
	public $has_fields = true;
	public $supports = ['products', 'pos', 'refunds', 'kiosk'];

	public $terminalType;

	public static function getID(): string
	{
		return 'pos_stripe_connect_terminal';
	}

	public function getValues()
	{
		return [
			'title' => $this->title,
			'description' => $this->description,
			'terminalType' => $this->terminalType,
		];
	}

	public function getPublicValues()
	{
		return [
			'terminalType' => $this->terminalType,
		];
	}

	public function __construct()
	{
		parent::__construct();

		$this->title = $this->get_option('title');
		$this->description = $this->get_option('description');
		$this->terminalType = $this->get_option('terminalType', 'all');
	}

	public function process_payment(/* int|\WC_Order */ $order_id): array
	{
		if ($order_id instanceof \WC_Order) {
			$order = $order_id;
		} else {
			$order = new \WC_Order($order_id);
		}

		$data = API::get_raw_data();
		$is_split_payment =
			class_exists('ZPOS\Gateway\SplitPayment') && SplitPayment::is_split_payment($data);
		$is_payment_intent = $is_split_payment
			? isset($data['payment_details']['splitPayments'][$this->id]['payment_intent']) &&
				$data['payment_details']['splitPayments'][$this->id]['payment_intent']
			: isset($data['payment_details']['payment_intent']) &&
				$data['payment_details']['payment_intent'];

		try {
			if ($is_payment_intent) {
				$paymentIntentID = $is_split_payment
					? $data['payment_details']['splitPayments'][$this->id]['payment_intent']
					: $data['payment_details']['payment_intent'];

				$metaInfo = $this->generateMetadata($order);
				$meta = $metaInfo['meta'];
				$description = $metaInfo['description'];

				$requestData = [
					'metadata' => $meta,
					'description' => $description ?: null,
				];

				if ($customer_stripe_id = $this->processStripeCustomer($order)) {
					$requestData['customer'] = $customer_stripe_id;
				}

				StripeConnectRestClient::getInstance()->putRequest(
					'payment-intents/' . $paymentIntentID,
					$requestData
				);

				$paymentIntent = StripeConnectRestClient::getInstance()->getRequest(
					'payment-intents/' . $paymentIntentID
				);

				$order->set_transaction_id($paymentIntent->transactionId);
				$order->add_meta_data('_stripe_terminal_net', $paymentIntent->net);
				$order->add_meta_data('_stripe_terminal_fee', $paymentIntent->fee);
				$order->add_meta_data('_stripe_terminal_currency', $paymentIntent->currency);
				$order->add_meta_data(
					'_stripe_terminal_charge_captured',
					$paymentIntent->captured ? 'yes' : 'no'
				);

				if ($paymentIntent->captured) {
					$order->payment_complete();
					$order->add_order_note(
						sprintf(
							__(
								'POS Stripe Connect Terminal charge complete (Charge ID: %s)',
								'point-of-sale-pos-woocommerce'
							),
							$paymentIntent->transactionId
						)
					);
					$order->set_status('completed');
				} else {
					$order->add_order_note(
						__('POS Stripe Connect Terminal charge not captured', 'point-of-sale-pos-woocommerce')
					);
					$order->add_meta_data(
						'_pos_gateway_error',
						__('POS Stripe Connect Terminal charge not captured', 'point-of-sale-pos-woocommerce')
					);
				}
				$order->save();

				return [
					'result' => $paymentIntent->captured ? 'success' : 'failed',
				];
			} else {
				return [
					'result' => 'failed',
				];
			}
		} catch (\Exception $e) {
			$order->set_status('failed');
			$order->add_meta_data('_pos_stripe_connect_terminal_gateway_error', $e->getMessage());
			$order->save();

			$error_data = [
				'order' => $order->get_id(),
				'message' => $e->getMessage(),
				'code' => $e->getCode(),
				'line' => $e->getLine(),
				'file' => $e->getFile(),
				'trace' => $e->getTraceAsString(),
			];

			wc_get_logger()->error(wc_print_r($error_data, true), ['source' => PLUGIN_NAME]);
			return [
				'result' => 'failed',
			];
		}
	}

	/**
	 * @param $value mixed
	 */
	protected function sanitize_additional_option_value(string $key, $value): string
	{
		if ('terminalType' === $key && is_string($value)) {
			return $this->validate_selected_value($value, ['web', 'app', 'all']);
		}

		return '';
	}
}
