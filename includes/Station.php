<?php

namespace ZPOS;

use ZPOS\Admin\Setting\PostTab;
use ZPOS\Admin\Stations\Post;
use ZPOS\Admin\Tabs\StripeConnect;
use ZPOS\Structure\StripeConnectResponseError;
use ZPOS_UI\License as UILicense;

class Station
{
	const CLOUD_POS_STATION_SLUG_META_FIELD = '_pos_cloud_pos_station_url';

	protected $id;
	public $post;

	public function __construct($post)
	{
		if ($post instanceof \WP_Post) {
			$this->post = $post;
			$this->id = $post->ID;
		} elseif (is_numeric($post)) {
			$this->post = get_post($post);
			$this->id = $this->post->ID;
		} else {
			throw new StationException('Not found station', StationException::NOT_FOUND);
		}

		if ($this->post->post_type !== Post::TYPE || $this->post === null) {
			throw new StationException('Not found station', StationException::NOT_FOUND);
		}
	}

	public function getAddress()
	{
		if (self::isWCStation($this->post->ID)) {
			$line1 = get_option('woocommerce_store_address');
			$line2 = get_option('woocommerce_store_address_2');
			$postal_code = get_option('woocommerce_store_postcode');
			$city = get_option('woocommerce_store_city');
			list($country, $state) = explode(':', get_option('woocommerce_default_country'));
		} else {
			$line1 = get_post_meta($this->post->ID, 'pos_address_1', true);
			$line2 = get_post_meta($this->post->ID, 'pos_address_2', true);
			$postal_code = get_post_meta($this->post->ID, 'pos_postcode', true);
			$city = get_post_meta($this->post->ID, 'pos_city', true);
			$state = get_post_meta($this->post->ID, 'pos_state', true);
			$country = get_post_meta($this->post->ID, 'pos_country', true);
		}

		$address = [];
		if (!empty($line1)) {
			$address['line1'] = $line1;
		}
		if (!empty($line2)) {
			$address['line2'] = $line2;
		}
		if (!empty($postal_code)) {
			$address['postal_code'] = $postal_code;
		}
		if (!empty($city)) {
			$address['city'] = $city;
		}
		if (!empty($state)) {
			$address['state'] = $state;
		}
		if (!empty($country)) {
			$address['country'] = $country;
		}

		if (empty($line1) || empty($postal_code) || empty($city) || empty($country)) {
			return null;
		}

		return $address;
	}

	public function getBaseURL($append = '')
	{
		$structure = get_option('permalink_structure');

		$url = parse_url(home_url());

		$base_url =
			$url['scheme'] . '://' . $url['host'] . (isset($url['port']) ? ':' . $url['port'] : '');

		return str_replace($base_url, '', get_the_permalink($this->post->ID)) .
			($structure ? '' : ($append ? '&rest=/' : '&rest=')) .
			$append;
	}

	public function getID()
	{
		return $this->post->ID;
	}

	public function getDebugURL()
	{
		return add_query_arg('debug', '1', $this->getBaseURL());
	}

	public function getData($key)
	{
		return PostTab::getValue($key, $this->post->ID);
	}

	public static function getFromOrder($order)
	{
		if (!$order instanceof \WC_Order) {
			if ($order instanceof \WP_Post) {
				$order = new \WC_Order($order->ID);
			} elseif (is_int($order)) {
				$order = new \WC_Order($order);
			} else {
				throw new \Exception('The $order parameter must be WC_Order, or WC_Post, or integer.');
			}
		}
		$station_id = $order->get_meta('_pos_by');
		if ($station_id === 'pos') {
			$station_id = self::getDefaultStationID();
		} elseif (empty($station_id)) {
			$station_id = self::getWCStationID();
		}
		return new self($station_id);
	}

	public static function getDefaultStationID()
	{
		return (int) get_option('pos_legacy_station_id', null);
	}

	public static function getWCStationID()
	{
		return (int) get_option('pos_wc_station_id', null);
	}

	public static function isWCStation($post)
	{
		return self::getWCStationID() === (int) $post;
	}

	public static function isDefaultStation($post)
	{
		return self::getDefaultStationID() === (int) $post;
	}

	public static function getURL($post_id)
	{
		return Plugin::isActive('pos-ui')
			? (UILicense::isActive()
				? get_the_permalink($post_id)
				: '#')
			: Plugin::getPOSCloudAppURL() .
					'/cloud-pos-station/?url=' .
					home_url('/') .
					'&stationSlug=' .
					(self::getCloudAppStationSlug($post_id) ?: $post_id);
	}

	public static function getCloudAppStationSlug($post_id)
	{
		return get_post_meta($post_id, self::CLOUD_POS_STATION_SLUG_META_FIELD, true);
	}

	public static function setCloudAppStationSlug($post_id, $pos_url)
	{
		return update_post_meta($post_id, self::CLOUD_POS_STATION_SLUG_META_FIELD, $pos_url);
	}

	public static function get_all(bool $exclude_wc_station = false): array
	{
		$args = [
			'numberposts' => -1,
			'post_type' => Post::TYPE,
		];

		if ($exclude_wc_station) {
			$wc_station_id = self::getWCStationID();

			if (is_numeric($wc_station_id)) {
				$args['exclude'] = [$wc_station_id];
			}
		}

		return get_posts($args);
	}

	public function getLocationId($mode = null)
	{
		if ($mode === null) {
			$mode = StripeConnect::getMode();
		}

		$id = get_post_meta($this->post->ID, '_pos_stripe_' . $mode . '_location_id', true);
		return !empty($id) ? $id : null;
	}

	public function createOrUpdateLocationForStation(): array
	{
		$action = 'nothing';
		try {
			if (!StripeConnectRestClient::hasAccess()) {
				return [
					'action' => 'nothing',
					'error' => 'No access',
					'success' => false,
					'station_id' => $this->post->ID,
				];
			}

			$address = $this->getAddress();
			$name = get_the_title($this->post->ID);
			$location_id = $this->getLocationId();
			$action = $location_id ? 'update' : 'create';

			if (!$address) {
				return [
					'action' => $action,
					'error' => 'Provide address for location',
					'success' => false,
					'station_id' => $this->post->ID,
				];
			}

			$data = [
				'name' => $name,
				'address' => $address,
			];

			if ($location_id) {
				StripeConnectRestClient::getInstance()->putRequest('locations/' . $location_id, $data);
			} else {
				$location = StripeConnectRestClient::getInstance()->postRequest('locations', $data);
				$location_id = $location->id;
				update_post_meta(
					$this->post->ID,
					'_pos_stripe_' . StripeConnectRestClient::getInstance()->getMode() . '_location_id',
					$location_id
				);
			}

			return [
				'action' => $action,
				'error' => null,
				'success' => true,
				'station_id' => $this->post->ID,
			];
		} catch (StripeConnectResponseError $e) {
			return [
				'action' => $action,
				'error' => $e->getData()->error,
				'success' => false,
				'station_id' => $this->post->ID,
			];
		} catch (\Exception $e) {
			wc_get_logger()->error('Failed to create/update location for station', [
				'source' => PLUGIN_NAME,
				'exception' => $e,
				'station_id' => $this->post->ID,
			]);

			return [
				'action' => $action,
				'error' => 'Something went wrong',
				'success' => false,
				'station_id' => $this->post->ID,
			];
		}
	}
}
