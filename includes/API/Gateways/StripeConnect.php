<?php

namespace ZPOS\API\Gateways;

use WP_REST_Request;
use WP_REST_Server;
use ZPOS\StripeConnectRestClient;
use ZPOS\Structure\StripeConnectResponseError;
use const ZPOS\PLUGIN_NAME;

class StripeConnect
{
	public function __construct($apiParent, $namespace, $rest_base)
	{
		$this->registerRoutes($apiParent, $namespace, $rest_base);
	}

	public function registerRoutes($apiParent, $namespace, $rest_base)
	{
		register_rest_route($namespace, '/' . $rest_base . '/stripe-connect-terminal/token', [
			'methods' => WP_REST_Server::READABLE,
			'callback' => [$this, 'terminalToken'],
			'permission_callback' => [$apiParent, 'get_items_permissions_check'],
		]);

		register_rest_route(
			$namespace,
			'/' . $rest_base . '/stripe-connect-terminal/payment/(?P<id>[\w-]+)',
			[
				'methods' => WP_REST_Server::READABLE,
				'callback' => [$this, 'terminalPayment'],
				'permission_callback' => [$apiParent, 'get_items_permissions_check'],
			]
		);

		register_rest_route($namespace, '/' . $rest_base . '/stripe-connect-terminal/intent', [
			'methods' => WP_REST_Server::CREATABLE,
			'callback' => [$this, 'terminalIntent'],
			'permission_callback' => [$apiParent, 'get_items_permissions_check'],
		]);
	}

	public function terminalPayment(WP_REST_Request $request)
	{
		try {
			$id = $request->get_param('id');

			StripeConnectRestClient::getInstance()->postRequest('payment-intents/' . $id . '/capture');

			return rest_ensure_response(new \stdClass());
		} catch (StripeConnectResponseError $exception) {
			wc_get_logger()->error(
				wc_print_r(
					[
						'data' => $exception->getData(),
						'code' => $exception->getCode(),
					],
					true
				),
				['source' => PLUGIN_NAME]
			);

			return new \WP_REST_Response(['error' => 'something went wrong'], 400);
		} catch (\Exception $exception) {
			wc_get_logger()->error(
				wc_print_r(
					[
						'message' => $exception->getMessage(),
						'code' => $exception->getCode(),
						'class' => get_class($exception),
					],
					true
				),
				['source' => PLUGIN_NAME]
			);

			return new \WP_REST_Response(['error' => 'something went wrong'], 400);
		}
	}

	public function terminalToken()
	{
		try {
			$token = StripeConnectRestClient::getInstance()->postRequest('connection-tokens');

			$data = [
				'location' => $token->location,
				'token' => $token->secret,
			];

			return rest_ensure_response(['data' => $data]);
		} catch (StripeConnectResponseError $exception) {
			wc_get_logger()->error(
				wc_print_r(
					[
						'data' => $exception->getData(),
						'code' => $exception->getCode(),
					],
					true
				),
				['source' => PLUGIN_NAME]
			);

			return new \WP_HTTP_Response(['error' => 'something went wrong'], 500);
		} catch (\Exception $exception) {
			wc_get_logger()->error(
				wc_print_r(
					[
						'message' => $exception->getMessage(),
						'code' => $exception->getCode(),
					],
					true
				),
				['source' => PLUGIN_NAME]
			);

			return new \WP_HTTP_Response(['error' => 'something went wrong'], 500);
		}
	}

	public function terminalIntent(WP_REST_Request $request)
	{
		try {
			$body = json_decode($request->get_body(), true);

			$intent = StripeConnectRestClient::getInstance()->postRequest('payment-intents', [
				'amount' => $body['amount'],
				'currency' => $body['currency'],
				'description' => $body['description'] ?: null,
			]);

			$data = [
				'id' => $intent->id,
				'client_secret' => $intent->clientSecret,
			];

			return rest_ensure_response(['data' => $data]);
		} catch (StripeConnectResponseError $exception) {
			wc_get_logger()->error(
				wc_print_r(
					[
						'data' => $exception->getData(),
						'code' => $exception->getCode(),
					],
					true
				),
				['source' => PLUGIN_NAME]
			);

			return new \WP_HTTP_Response(['error' => 'something went wrong'], 500);
		} catch (\Exception $exception) {
			wc_get_logger()->error(
				wc_print_r(
					[
						'message' => $exception->getMessage(),
						'code' => $exception->getCode(),
					],
					true
				),
				['source' => PLUGIN_NAME]
			);

			return new \WP_HTTP_Response(['error' => 'something went wrong'], 500);
		}
	}
}
