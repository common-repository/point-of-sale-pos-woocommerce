<?php

namespace ZPOS\Admin\Stations\Tabs;

use ZPOS\Admin\Setting\Box;
use ZPOS\Admin\Setting\Input\CreateTerminalReader;
use ZPOS\Admin\Setting\Input\Description;
use ZPOS\Admin\Setting\Input\ListTerminalReader;
use ZPOS\Admin\Setting\PostTab;
use ZPOS\Admin\Setting\Sanitize\Boolean;
use ZPOS\Admin\Tabs\StripeConnect;
use ZPOS\Station;
use ZPOS\StripeConnectRestClient;
use const ZPOS\PLUGIN_NAME;

class TerminalReaders extends PostTab
{
	use Boolean;

	public $name = 'terminalReaders';
	public $label;
	public $path = '/terminalReaders';

	public function __construct()
	{
		parent::__construct();
		$this->label = __('Terminal Readers', 'zpos-wp-api');
	}

	public function isVisible(...$args)
	{
		try {
			$post = $args[0];

			return (new Station($post->ID))->getLocationId() !== null;
		} catch (\Exception $e) {
			return false;
		}
	}

	public function getBoxes()
	{
		return [
			new Box(
				null,
				null,
				new Description([$this, 'getLocationId'], __('Location ID', 'zpos-wp-api')),
				new ListTerminalReader(__('Registered readers', 'zpos-wp-api'), 'pos_terminal_reader', [
					$this,
					'getTerminalsList',
				]),
				new CreateTerminalReader(__('Register new reader', 'zpos-wp-api'), 'pos_terminal_reader', [
					'sanitize' => function ($value) {
						$value['registration_code'] = sanitize_text_field($value['registration_code']);
						$value['label'] = sanitize_text_field($value['label']);
						return $value;
					},
					'savePost' => [$this, 'createReader'],
				])
			),
		];
	}

	public function getLocationId($data): string
	{
		try {
			$location_id = (new Station($data->ID))->getLocationId();

			if (empty($location_id)) {
				return __(
					'No location ID found. Please update your address to register terminal readers.',
					'zpos-wp-api'
				);
			}

			if (StripeConnect::getMode() === 'live') {
				return sprintf(
					__('Current live mode ID: %s', 'zpos-wp-api'),
					'<code>' . $location_id . '</code>'
				);
			}
			if (StripeConnect::getMode() === 'test') {
				return sprintf(
					__('Current test mode ID: %s', 'zpos-wp-api'),
					'<code>' . $location_id . '</code>'
				);
			}
		} catch (\Exception $e) {
			wc_get_logger()->error('Error getting location ID', [
				'source' => PLUGIN_NAME,
				'exception' => $e,
			]);
		}
		return __('Error getting location ID', 'zpos-wp-api');
	}

	public function createReader($post, $inputName, $value): void
	{
		try {
			if (empty($value['registration_code'])) {
				return;
			}
			$location_id = (new Station($post->ID))->getLocationId();
			if (empty($location_id)) {
				return;
			}

			$data = [
				'registration_code' => $value['registration_code'],
			];
			if (!empty($value['label'])) {
				$data['label'] = $value['label'];
			}

			StripeConnectRestClient::getInstance()->postRequest(
				'locations/' . $location_id . '/readers',
				$data
			);
		} catch (\Exception $e) {
			wc_get_logger()->error('Error creating terminal reader', [
				'source' => PLUGIN_NAME,
				'exception' => $e,
			]);
		}
	}

	public function getTerminalsList($post)
	{
		try {
			$location_id = (new Station($post->ID))->getLocationId();

			return StripeConnectRestClient::getInstance()->getRequest(
				'locations/' . $location_id . '/readers'
			);
		} catch (\Exception $e) {
			wc_get_logger()->error('Error getting terminal readers', [
				'source' => PLUGIN_NAME,
				'exception' => $e,
			]);

			return [];
		}
	}
}
