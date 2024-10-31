<?php

namespace ZPOS;

class Setup
{
	public function __construct()
	{
		new Activate();
		new Deactivate();
		new Login();
		new Auth();

		add_action('before_woocommerce_init', [$this, 'add_order_storage_support']);
		add_action('woocommerce_init', [$this, 'init']);
		add_action('plugins_loaded', [$this, 'checkVersion']);
	}

	public function add_order_storage_support()
	{
		if (class_exists('Automattic\WooCommerce\Utilities\FeaturesUtil')) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'custom_order_tables',
				PLUGIN_ROOT_FILE,
				true
			);
		}
	}

	public function checkVersion()
	{
		if (!class_exists('WooCommerce')) {
			add_action('admin_notices', [$this, 'requireWCNotice']);
		}

		if (class_exists('ZStripeTerminalPOS\Setup')) {
			add_action('admin_notices', [$this, 'unsupportedNoticeTerminal']);
		}
	}

	public function requireWCNotice()
	{
		echo '<div class="notice notice-error is-dismissible"><p>';
		_e('Point of Sale (POS) for WooCommerce require WooCommerce', 'zpos-wp-api');
		echo '</p></div>';
	}

	public function unsupportedNoticeTerminal()
	{
		echo '<div class="notice notice-error is-dismissible"><p>';
		echo sprintf(
			__(
				'Jovvie Legacy Stripe Terminal plugin is no longer supported. Connect your Stripe account to continue processing payments with terminal. <a href="%s">[Connect Now]</a>',
				'zpos-wp-api'
			),
			admin_url('edit.php?post_type=pos-station&page=pos#/stripe-connect')
		);
		echo '</p></div>';
	}

	public function init()
	{
		if (!class_exists('WooCommerce')) {
			return;
		}

		new Translation();
		new Woocommerce();
		new Frontend();
		new Model();
		new Emails();
		new API();
		new Admin();
	}
}
