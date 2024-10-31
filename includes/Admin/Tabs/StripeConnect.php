<?php

namespace ZPOS\Admin\Tabs;

use ZPOS\Admin\Setting\Box;
use ZPOS\Admin\Setting\Input\Checkbox;
use ZPOS\Admin\Setting\Input\ConnectionStatus;
use ZPOS\Admin\Setting\Input\Description;
use ZPOS\Admin\Setting\Input\Input;
use ZPOS\Admin\Setting\Input\Password;
use ZPOS\Admin\Setting\Input\Radio;
use ZPOS\Admin\Setting\Input\SecretHidden;
use ZPOS\Admin\Setting\PageTab;
use ZPOS\Admin\Stations\Post as StationPostType;
use ZPOS\Station;
use ZPOS\StripeConnectRestClient;
use ZPOS\Structure\StripeConnectResponseError;
use const ZPOS\PLUGIN_NAME;
use const ZPOS\PLUGIN_ROOT_FILE;
use const ZPOS\PLUGIN_VERSION;

class StripeConnect extends PageTab
{
	public $exact = true;
	public $name = 'Stripe Connect';
	public $path = '/stripe-connect';

	private $needUpdateLocations = false;

	public function getBoxes()
	{
		return [
			new Box(
				null,
				null,
				new Description(
					$this->hiddenField(),
					__('Connect your Stripe account to accept payments in the Point of Sale.', 'zpos-wp-api')
				),
				new ConnectionStatus('', 'pos_stripe_connect_status', null, [
					'callback' => [$this, 'get_connection_args'],
				]),
				new Input(__('Public Key', 'zpos-wp-api'), 'pos_stripe_publicKey', [
					self::class,
					'getPublicKey',
				]),
				new Password(__('Secret Key', 'zpos-wp-api'), 'pos_stripe_secretKey', [
					self::class,
					'getSecretKey',
				]),
				new Radio(
					__('Mode', 'zpos-wp-api'),
					'pos_stripe_mode',
					[self::class, 'getMode'],
					[
						['value' => 'live', 'label' => __('Live', 'zpos-wp-api')],
						['value' => 'test', 'label' => __('Test', 'zpos-wp-api')],
					]
				),
				new Checkbox(
					__('Add and update locations for terminal readers in each POS Station.', 'zpos-wp-api'),
					'pos_stripe_autolocations',
					[self::class, 'getAutolocations'],
					'Enable'
				),
				new SecretHidden(__('Host', 'zpos-wp-api'), 'pos_stripe_host', [self::class, 'getHost'])
			),
		];
	}

	public function get_connection_args(): array
	{
		return [
			'args' => [
				'title' => __('Stripe Terminal', 'zpos-wp-api'),
				'textdomain' => '',
				'description' => __(
					'Link Jovvie with your Stripe account for seamless payment processing.',
					'zpos-wp-api'
				),
				'uri' => 'https://jovvie.com/payments/#stripe',
				'installed' => true,
				'status' => true,
				'hidestatus' => true,
				'permalinkuri' => 'https://jovvie.com/payments/#stripe',
				'type' => '',
				'connected' => $this->get_status(),
				'connectionlabels' => [
					'true' => __('Connected', 'zpos-wp-api'),
					'false' => __('Disconnected', 'zpos-wp-api'),
				],
				'cardTitle' => __('Stripe Account', 'zpos-wp-api'),
				'link' => [
					'title' => __('Link Account', 'zpos-wp-api'),
					'href' => 'https://login.bizswoop.app',
					'target' => '_blank',
					'id' => 'ssoStripeConnect',
					'large' => true,
				],
				'enable' => ['type' => 'link', 'to' => ''],
			],
		];
	}

	public function init(): void
	{
		register_setting('pos' . $this->path, 'pos_stripe_lastupdate');
		register_setting('pos' . $this->path, 'pos_stripe_publicKey');
		register_setting('pos' . $this->path, 'pos_stripe_secretKey');
		register_setting('pos' . $this->path, 'pos_stripe_mode', [
			'default' => 'live',
		]);

		register_setting('pos' . $this->path, 'pos_stripe_autolocations', [
			'default' => true,
		]);

		register_setting('pos' . $this->path, 'pos_stripe_host', [
			'default' => 'https://login.bizswoop.app',
		]);

		add_filter('pre_update_option_pos_stripe_host', [$this, 'validateHost']);
		add_action('wp_ajax_zpos_check_stripe_connect_status', [$this, 'ajax_check_status']);
		add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
		add_action('updated_option', [$this, 'handleUpdateLocationsOptions'], 10, 3);
		add_action('shutdown', [$this, 'handleUpdateLocations']);
	}

	public function enqueue_assets(): void
	{
		wp_enqueue_script(
			'zpos_sso_stripe_connect_handler',
			plugins_url('assets/stripe-connect/window-handler.js', PLUGIN_ROOT_FILE),
			[],
			PLUGIN_VERSION
		);
		wp_localize_script('zpos_sso_stripe_connect_handler', 'zpos_sso_stripe_connect_handler', [
			'ajaxurl' => admin_url('admin-ajax.php'),
		]);
	}

	public static function getPublicKey(): string
	{
		return get_option('pos_stripe_publicKey', '');
	}

	public static function getSecretKey(): string
	{
		return get_option('pos_stripe_secretKey', '');
	}

	public static function getMode(): string
	{
		return get_option('pos_stripe_mode');
	}

	public static function getAutolocations(): bool
	{
		return get_option('pos_stripe_autolocations', true);
	}

	public function handleUpdateLocationsOptions($option): void
	{
		$keys = [
			'pos_stripe_lastupdate',
			'pos_stripe_publicKey',
			'pos_stripe_secretKey',
			'pos_stripe_mode',
			'pos_stripe_autolocations',
			'pos_stripe_host',
		];
		if (in_array($option, $keys)) {
			$this->needUpdateLocations = true;
		}
	}

	public function handleUpdateLocations(): void
	{
		try {
			if (!$this->needUpdateLocations) {
				return;
			}
			if (self::getAutolocations() === false) {
				return;
			}
			if (!StripeConnectRestClient::hasAccess()) {
				return;
			}

			$stations = get_posts([
				'post_type' => StationPostType::TYPE,
				'posts_per_page' => -1,
			]);

			foreach ($stations as $station) {
				(new Station($station->ID))->createOrUpdateLocationForStation();
			}
		} catch (\Exception $e) {
			wc_get_logger()->error('Failed to update locations for all stations', [
				'source' => PLUGIN_NAME,
				'exception' => $e,
			]);
		}
	}

	public static function getHost(): string
	{
		return get_option('pos_stripe_host', 'https://login.bizswoop.app');
	}

	public function validateHost($value): string
	{
		if (!$value) {
			$value = 'https://login.bizswoop.app';
		}

		return $value;
	}

	public function ajax_check_status(): void
	{
		if (!current_user_can('manage_woocommerce_pos')) {
			echo json_encode([
				'is_connected' => false,
			]);
			die();
		}

		if (isset($_GET['zpos_stripe_public_key']) && isset($_GET['zpos_stripe_secret_key'])) {
			update_option(
				'pos_stripe_publicKey',
				sanitize_text_field(wp_unslash($_GET['zpos_stripe_public_key']))
			);
			update_option(
				'pos_stripe_secretKey',
				sanitize_text_field(wp_unslash($_GET['zpos_stripe_secret_key']))
			);
		}

		echo json_encode([
			'is_connected' => $this->get_status(),
		]);
		die();
	}

	public function hiddenField()
	{
		return '<input type="hidden" id="pos_stripe_lastupdate" name="pos_stripe_lastupdate" value="' .
			time() .
			'"/>';
	}

	public function get_status(): bool
	{
		if (empty(static::getPublicKey()) || empty(static::getSecretKey())) {
			return false;
		}

		try {
			StripeConnectRestClient::getInstance()->getRequest('account');
			$is_connected = true;
		} catch (\Exception $e) {
			$is_connected = false;

			$error_data = [
				'message' => $e->getMessage(),
				'data' => null,
				'response' => null,
				'code' => $e->getCode(),
				'line' => $e->getLine(),
				'file' => $e->getFile(),
				'trace' => $e->getTraceAsString(),
			];

			if ($e instanceof StripeConnectResponseError) {
				$error_data['data'] = $e->getData();
				$error_data['response'] = [
					'url' => $e->getResponse()['http_response']->get_response_object()->url,
				];
			}

			wc_get_logger()->error('Failing connect stripe SSO service', [
				'source' => PLUGIN_NAME,
				'exception' => $error_data,
			]);
		}

		return $is_connected;
	}
}
