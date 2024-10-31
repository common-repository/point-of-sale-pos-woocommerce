<?php

namespace ZPOS\Gateway;

use ZPOS\API;
use ZPOS\StripeConnectRestClient;
use const ZPOS\PLUGIN_NAME;

class StripeConnect extends AbstractStripeConnect
{
	public $id = 'pos_stripe_connect';
	public $method_title = 'Stripe Connect';
	public $method_description = '';
	public $has_fields = true;
	public $supports = ['products', 'pos', 'refunds', 'kiosk'];

	public static function getID(): string
	{
		return 'pos_stripe_connect';
	}

	public function getValues()
	{
		return [
			'title' => $this->title,
			'description' => $this->description,
		];
	}

	public function getPublicValues()
	{
		try {
			$account = StripeConnectRestClient::getInstance()->getRequest('account');

			return [
				'publicKey' => $account->platformPublicKey,
				'id' => $account->id,
			];
		} catch (\Exception $e) {
			wc_get_logger()->error('Failing retrieving account data', ['source' => PLUGIN_NAME]);

			return [
				'publicKey' => '',
				'id' => '',
			];
		}
	}

	public function __construct()
	{
		parent::__construct();

		$this->title = $this->get_option('title');
		$this->description = $this->get_option('description');
	}

	public function process_payment(/* int|\WC_Order */ $order_id): array
	{
		if ($order_id instanceof \WC_Order) {
			$order = $order_id;
		} else {
			$order = new \WC_Order($order_id);
		}

		try {
			$data = API::get_raw_data();
			$is_split_payment = SplitPayment::is_split_payment($data);
			$payment_id = $is_split_payment
				? $data['payment_details']['splitPayments'][$this->id]['stripe_source']['id'] ?? false
				: $data['payment_details']['stripe_source']['id'] ?? false;

			if ($payment_id) {
				$metaInfo = $this->generateMetadata($order);
				$meta = $metaInfo['meta'];
				$description = $metaInfo['description'];

				$data = [
					'amount' => $this->getRequestAmount($order->get_total(), $order->get_currency()),
					'currency' => strtolower($order->get_currency()),
					'source' => $payment_id,
					'description' => $description ?: null,
					'metadata' => $meta,
				];

				$customer_stripe_id = $this->processStripeCustomer($order);
				if ($customer_stripe_id) {
					$data['customer'] = $customer_stripe_id;
				}

				$charge = StripeConnectRestClient::getInstance()->postRequest('charges', $data);

				$transactionId = $charge->id;
				$order->set_transaction_id($transactionId);
				$order->add_meta_data('_stripe_terminal_net', $charge->net);
				$order->add_meta_data('_stripe_terminal_fee', $charge->fee);
				$order->add_meta_data('_stripe_terminal_currency', $charge->currency);
				$order->add_meta_data('_stripe_terminal_charge_captured', $charge->captured ? 'yes' : 'no');

				if ($charge->captured) {
					$order->payment_complete();
					$order->add_order_note(
						sprintf(
							__('POS Stripe charge complete (Charge ID: %s)', 'point-of-sale-pos-woocommerce'),
							$transactionId
						)
					);
					$order->set_status('completed');
				} else {
					$order->add_order_note(
						__('POS Stripe charge not captured', 'point-of-sale-pos-woocommerce')
					);
					$order->add_meta_data(
						'_pos_gateway_error',
						__('POS Stripe charge not captured', 'point-of-sale-pos-woocommerce')
					);
				}
				$order->save();

				return [
					'result' => $charge->captured ? 'success' : 'failed',
				];
			} else {
				return [
					'result' => 'failed',
				];
			}
		} catch (\Exception $e) {
			$order->set_status('failed');
			$order->add_meta_data('_pos_stripe_connect_gateway_error', $e->getMessage());
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
}
