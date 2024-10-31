<?php

namespace ZPOS\API;

use WC_REST_Controller;
use WP_REST_Server;
use ZPOS\Admin\Stations\Post;
use ZPOS\Admin\Tabs\StripeConnect;
use ZPOS\Station;
use ZPOS\StationException;
use ZPOS\Structure\ArrayObject;
use const ZPOS\REST_NAMESPACE;

class Stations extends WC_REST_Controller
{
	protected $namespace = REST_NAMESPACE;
	protected $rest_base = 'stations';

	public function __construct()
	{
		do_action(__METHOD__, $this, $this->namespace, $this->rest_base);
	}

	public function register_routes()
	{
		do_action(__METHOD__, $this, $this->namespace, $this->rest_base);

		register_rest_route($this->namespace, '/' . $this->rest_base, [
			'methods' => WP_REST_Server::READABLE,
			'callback' => [$this, 'get_all_stations'],
			'permission_callback' => [$this, 'permission_check'],
		]);

		register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)', [
			'methods' => WP_REST_Server::READABLE,
			'callback' => [$this, 'get_station'],
			'permission_callback' => [$this, 'permission_check'],
		]);

		register_rest_route($this->namespace, '/' . $this->rest_base . '/update-slug', [
			'methods' => WP_REST_Server::EDITABLE,
			'callback' => [$this, 'update_station_slug'],
			'args' => [
				'stationId' => [
					'type' => 'integer',
					'required' => true,
				],
				'stationSlug' => [
					'type' => 'string',
					'required' => true,
				],
			],
			'permission_callback' => [$this, 'permission_check'],
		]);

		register_rest_route($this->namespace, '/' . $this->rest_base . '/shared', [
			'methods' => WP_REST_Server::READABLE,
			'callback' => [$this, 'get_all_shared_stations'],
			'args' => [
				'userEmail' => [
					'type' => 'string',
					'required' => true,
				],
			],
			'permission_callback' => [$this, 'permission_check'],
		]);
	}

	public function get_all_stations()
	{
		$stations = get_posts([
			'numberposts' => -1,
			'post_type' => Post::TYPE,
		]);

		return (new ArrayObject($stations))
			->map(function ($station_post) {
				try {
					return $this->prepare_item(new Station($station_post));
				} catch (StationException $e) {
					return null;
				}
			})
			->filter(function ($station) {
				return $station !== null;
			})
			->get();
	}

	public function get_station(\WP_REST_Request $request)
	{
		try {
			$station_post = get_post($request->get_param('id'));
			$station = new Station($station_post);

			return $this->prepare_item($station);
		} catch (StationException $e) {
			if ($e->getCode() === StationException::NOT_FOUND) {
				return new \WP_Error('not_found', 'Station not found', ['status' => 404]);
			}
			return new \WP_Error('error', 'Error', ['status' => 500]);
		} catch (\Exception $e) {
			return new \WP_Error('error', 'Error', ['status' => 500]);
		}
	}

	public function update_station_slug(\WP_REST_Request $request)
	{
		// todo: add validation and some transforming for the station slug, for security
		return Station::setCloudAppStationSlug($request['stationId'], $request['stationSlug']);
	}

	public function get_all_shared_stations(\WP_REST_Request $request)
	{
		$user = get_user_by('email', $request->get_param('userEmail'));

		return [
			'adminName' => wp_get_current_user()->display_name,
			'currentUserEmail' => wp_get_current_user()->user_email,
			'roles' => $user->roles,
			'stations' => $this->get_all_stations(),
		];
	}

	public function permission_check()
	{
		return is_user_logged_in();
	}

	protected function prepare_item(Station $station)
	{
		$data = [
			'posID' => $station->post->ID,
			'name' => esc_js($station->post->post_title),
			'cloudStationSlug' =>
				Station::getCloudAppStationSlug($station->post->ID) ?: $station->post->ID,
			'access' => current_user_can('access_woocommerce_pos', $station->post->ID),
		];

		$location_id = $station->getLocationId();

		if ($location_id) {
			$data['locationID'] = $location_id;
		}

		return $data;
	}
}
