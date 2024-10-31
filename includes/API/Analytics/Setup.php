<?php

namespace ZPOS\API\Analytics;

use WP_REST_Controller;
use const ZPOS\REST_NAMESPACE;

class Setup
{
	public const REST_NAMESPACE = REST_NAMESPACE . '-analytics';

	public function __construct()
	{
		add_action('rest_api_init', [$this, 'register_rest_routes'], 5);
		new Orders();
	}

	public function register_rest_routes(): void
	{
		$classes = [Gateways::class];

		foreach ($classes as $class) {
			/**
			 * @var $controller WP_REST_Controller
			 */
			$controller = new $class();
			$controller->register_routes();
		}
	}
}
