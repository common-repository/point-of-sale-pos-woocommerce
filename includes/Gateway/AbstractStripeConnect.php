<?php

namespace ZPOS\Gateway;

use ZPOS\StripeConnectRestClient;
use const ZPOS\PLUGIN_NAME;

abstract class AbstractStripeConnect extends Base
{
	public function __construct()
	{
		parent::__construct();
		add_action('woocommerce_admin_order_totals_after_total', [$this, 'displayOrderTotals']);
	}

	public function displayOrderTotals($order_id)
	{
		$order = wc_get_order($order_id);

		if ($order->get_payment_method() !== $this->id) {
			return;
		}
		if (!in_array($order->get_status(), ['completed', 'refunded'])) {
			return;
		}

		$currency_code = $order->get_meta('_stripe_terminal_currency');
		if ($net_value = $order->get_meta('_stripe_terminal_net')) {
			$net = $this->getResponseAmount($net_value, $currency_code);
		}
		if ($fee_value = $order->get_meta('_stripe_terminal_fee')) {
			$fee = $this->getResponseAmount($fee_value, $currency_code);
		}
		$currency = strtoupper($currency_code);

		if (!empty($net)): ?>
			<tr>
				<td
					class="label"><?php _e('Jovvie Net', 'point-of-sale-pos-woocommerce'); ?></td>
				<td width="1%"></td>
				<td class="total">
					<?php echo wc_price($net, ['currency' => $currency]); ?>
				</td>
			</tr>
		<?php endif;
		if (!empty($fee)): ?>
			<tr>
				<td
					class="label"><?php _e('Jovvie Fee', 'point-of-sale-pos-woocommerce'); ?></td>
				<td width="1%"></td>
				<td class="total">
					<?php echo wc_price($fee, ['currency' => $currency]); ?>
				</td>
			</tr>
		<?php endif;
	}

	public function process_refund($order_id, $amount = null, $reason = '')
	{
		try {
			if ($order_id instanceof \WC_Order) {
				$order = $order_id;
			} else {
				$order = new \WC_Order($order_id);
			}

			$currency = strtolower($order->get_currency());
			$charge = $order->get_transaction_id();

			$amount = $amount ? $this->getRequestAmount($amount, $currency) : $amount;

			$refund = StripeConnectRestClient::getInstance()->postRequest(
				'charges/' . $charge . '/refund',
				[
					'amount' => $amount,
					'currency' => $currency,
					'reason' => $reason,
				]
			);

			$currency = $refund->currency;
			$id = $refund->id;
			$amount = $this->getResponseAmount($refund->amount, $currency);
			$status = $refund->status;

			if ($status === 'failed') {
				return false;
			}

			$refund_message = sprintf(
				__('Refunded %1$s - Refund ID: %2$s - Reason: %3$s', 'point-of-sale-pos-woocommerce'),
				$amount,
				$id,
				$reason
			);

			$order->update_meta_data('_stripe_refund_id', $id);
			$order->add_order_note($refund_message);
			return true;
		} catch (\Exception $exception) {
			return false;
		}
	}

	protected function generateMetadata($order)
	{
		if (!$order instanceof \WC_Order) {
			$order = new \WC_Order($order);
		}

		$meta = [
			'site_url' => home_url(),
			'order_id' => $order->get_id(),
		];

		$description = get_bloginfo('name', 'raw') . ' POS - Order ' . $order->get_id();

		if ($customer = $order->get_customer_id()) {
			$user = get_user_by('ID', $customer);
			$meta['customer_email'] = $user->user_email;
			$meta['customer_name'] = $user->user_firstname . ' ' . $user->user_lastname;
		} else {
			$meta['customer_name'] = 'Guest';
		}

		$payment_method_id = $this->id;
		$meta = apply_filters('jovvie_pos_stripe_connect_metadata', $meta, $order, $payment_method_id);
		$description = apply_filters(
			'jovvie_pos_stripe_connect_description',
			$description,
			$order,
			$payment_method_id
		);

		return compact('meta', 'description');
	}

	/**
	 * Processes the Stripe customer.
	 *
	 * This function is responsible for handling the Stripe customer. It first retrieves the customer ID from the order.
	 * If the customer ID does not exist, it returns null. It then attempts to retrieve the Stripe customer ID from the user's options.
	 * If the Stripe customer ID does not exist, it tries to retrieve the Stripe customer ID from the fallback user's options.
	 * If the Stripe customer ID still does not exist, it makes a request to the Stripe API to create a new customer and stores the returned customer ID.
	 * If the Stripe customer ID has changed, it updates the user's options with the new Stripe customer ID.
	 * Finally, it updates the order's metadata with the Stripe customer ID and saves the order.
	 *
	 * @param \WC_Order $order The WooCommerce order.
	 * @return string|null The Stripe customer ID or null if the customer ID does not exist.
	 */
	public function processStripeCustomer($order)
	{
		try {
			// Get the customer ID from the order
			$customer_id = $order->get_customer_id();
			// If the customer ID does not exist, return null
			if (!$customer_id) {
				return null;
			}

			// Attempt to retrieve the Stripe customer ID from the user's options
			$stripe_customer_id = $stripe_connect_customer_id = get_user_option(
				'_pos_stripe_connect_customer_id',
				$customer_id
			);

			// If the Stripe customer ID does not exist, try to retrieve the Stripe customer ID from the fallback user's options
			if (!$stripe_connect_customer_id) {
				$stripe_customer_id = get_user_option('_stripe_customer_id', $customer_id);
			}

			// Try to get the customer from Stripe
			try {
				StripeConnectRestClient::getInstance()->getRequest('customers/' . $stripe_customer_id);
			} catch (\Exception $e) {
				// If the customer does not exist on Stripe, set the Stripe customer ID to null
				$stripe_customer_id = null;
			}

			// If the Stripe customer ID does not exist
			if (!$stripe_customer_id) {
				// Get the user by their ID
				$customer = get_user_by('ID', $customer_id);

				// Create a new customer on Stripe
				$customerData = StripeConnectRestClient::getInstance()->postRequest('customers', [
					'name' => $customer->user_nicename,
					'email' => $customer->user_email,
				]);

				// Store the returned customer ID
				$stripe_connect_customer_id = $stripe_customer_id = $customerData->id;
				// Update the user's options with the new Stripe customer ID
				update_user_option($customer_id, '_pos_stripe_connect_customer_id', $stripe_customer_id);
			}

			// If the Stripe customer ID has changed
			if ($stripe_connect_customer_id !== $stripe_customer_id) {
				// Update the user's options with the new Stripe customer ID
				update_user_option($customer_id, '_pos_stripe_connect_customer_id', $stripe_customer_id);
			}

			// Update the order's metadata with the Stripe customer ID
			$order->update_meta_data('_stripe_connect_customer_id', $stripe_customer_id);
			// Save the order
			$order->save();

			// Return the Stripe customer ID
			return $stripe_customer_id;
		} catch (\Exception $e) {
			$error_data = [
				'order' => $order->get_id(),
				'message' => $e->getMessage(),
				'code' => $e->getCode(),
				'line' => $e->getLine(),
				'file' => $e->getFile(),
				'trace' => $e->getTraceAsString(),
			];

			wc_get_logger()->error(wc_print_r($error_data, true), ['source' => PLUGIN_NAME]);

			return null;
		}
	}

	protected function getRequestAmount($amount, $currency)
	{
		return in_array($currency, $this->noDecimalCurrencies()) ? $amount : $amount * 100;
	}

	protected function getResponseAmount($amount, $currency)
	{
		return in_array($currency, $this->noDecimalCurrencies()) ? $amount : $amount / 100;
	}

	protected function noDecimalCurrencies()
	{
		return [
			'bif', // Burundian Franc
			'clp', // Chilean Peso
			'djf', // Djiboutian Franc
			'gnf', // Guinean Franc
			'jpy', // Japanese Yen
			'kmf', // Comorian Franc
			'krw', // South Korean Won
			'mga', // Malagasy Ariary
			'pyg', // Paraguayan Guaraní
			'rwf', // Rwandan Franc
			'vnd', // Vietnamese Đồng
			'vuv', // Vanuatu Vatu
			'xaf', // Central African Cfa Franc
			'xof', // West African Cfa Franc
			'xpf', // Cfp Franc
		];
	}
}
