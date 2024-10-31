<?php

namespace ZPOS;

use ZPOS\Admin\Tabs\StripeConnect;
use ZPOS\Structure\StripeConnectResponseError;

class StripeConnectRestClient
{
	protected string $mode;
	protected static ?StripeConnectRestClient $_instance = null;

	public function __construct($mode = null)
	{
		if ($mode === null) {
			$mode = StripeConnect::getMode();
		}
		$this->mode = $mode;

		return $this;
	}

	public static function getInstance(): StripeConnectRestClient
	{
		if (self::$_instance === null) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	public static function getAccess($key = null)
	{
		if (!in_array($key, [null, 'publicKey', 'secretKey'])) {
			throw new \Exception('Wrong key argument');
		}

		$publicKey = StripeConnect::getPublicKey();
		$secretKey = StripeConnect::getSecretKey();

		if ($key === 'publicKey') {
			return $publicKey;
		} elseif ($key === 'secretKey') {
			return $secretKey;
		} else {
			return ['publicKey' => $publicKey, 'secretKey' => $secretKey];
		}
	}

	public function getMode()
	{
		return $this->mode;
	}

	public static function hasAccess()
	{
		$data = self::getAccess();
		$publicKey = $data['publicKey'];
		$secretKey = $data['secretKey'];
		return $publicKey && $secretKey;
	}

	protected function getBaseUrl()
	{
		$url = StripeConnect::getHost();
		$url .= '/api/connect/v1/stripe-account';

		if ($this->mode === 'test') {
			$url .= '/test';
		}

		return $url;
	}

	/**
	 * @throws StripeConnectResponseError|\WP_Error
	 */
	public function getRequest($url, $options = [])
	{
		return $this->queryRequest('GET', $url, $options);
	}

	/**
	 * @throws StripeConnectResponseError|\WP_Error
	 */
	public function deleteRequest($url, $options = [])
	{
		return $this->queryRequest('DELETE', $url, $options);
	}

	/**
	 * @throws StripeConnectResponseError|\WP_Error
	 */
	public function postRequest($url, $data = [], $options = [])
	{
		return $this->bodyRequest('POST', $url, $data, $options);
	}

	/**
	 * @throws StripeConnectResponseError|\WP_Error
	 */
	public function putRequest($url, $data = [], $options = [])
	{
		return $this->bodyRequest('PUT', $url, $data, $options);
	}

	/**
	 * @throws StripeConnectResponseError|\WP_Error
	 */
	protected function queryRequest($method, $url, $options = [])
	{
		$options = self::options($options);

		$base = $this->getBaseUrl();
		$access = self::getAccess();
		$publicKey = $access['publicKey'];
		$secretKey = $access['secretKey'];

		$requestUrl = $base . '/' . $url;
		$args = ['time' => time(), 'publicKey' => $publicKey];
		$baseArgsUrl = add_query_arg($args, $requestUrl);
		$baseArgForHash = str_replace('/?', '', add_query_arg($args, '/'));

		$hash = hash('sha256', $baseArgForHash . ':' . $secretKey);
		$hashedUrl = add_query_arg('hash', $hash, $baseArgsUrl);

		$headers = [];
		$result = wp_remote_request($hashedUrl, [
			'headers' => $headers,
			'method' => $method,
			'user-agent' => self::userAgent(),
		]);

		return self::handleResponse($result, $options);
	}

	/**
	 * @throws StripeConnectResponseError|\WP_Error
	 */
	protected function bodyRequest($method, $url, $data = [], $options = [])
	{
		$options = self::options($options);

		$base = $this->getBaseUrl();
		$access = self::getAccess();
		$data['publicKey'] = $access['publicKey'];
		$data['time'] = time();

		$data['hash'] = hash(
			'sha256',
			json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) .
				':' .
				$access['secretKey']
		);

		$headers = [
			'Content-Type' => 'application/json; charset=utf-8',
		];

		$result = wp_remote_request($base . '/' . $url, [
			'body' => json_encode($data),
			'headers' => $headers,
			'method' => $method,
			'data_format' => 'body',
			'user-agent' => self::userAgent(),
		]);

		return self::handleResponse($result, $options);
	}

	protected static function userAgent(): string
	{
		return 'WordPress/' .
			get_bloginfo('version') .
			' JovviePOS/' .
			PLUGIN_VERSION .
			' (StripeConnect); ' .
			get_bloginfo('url');
	}

	protected static function options($options)
	{
		$default = [
			'allResponse' => false,
			'rejectOnError' => true,
		];

		return array_merge($default, $options);
	}

	protected static function handleResponse($result, $options)
	{
		$allResponse = $options['allResponse'];
		$rejectOnError = $options['rejectOnError'];

		if (is_wp_error($result)) {
			if ($rejectOnError) {
				throw new \Exception($result->get_error_message(), 0);
			} else {
				return $result;
			}
		}

		$code = wp_remote_retrieve_response_code($result);

		if ($allResponse) {
			if ($code >= 400) {
				throw new StripeConnectResponseError(['response' => $result], 'Request failed', $code);
			}
			return $result;
		}

		$data = null;

		if ($result['headers']['content-type'] === 'application/json; charset=utf-8') {
			$data = json_decode($result['body']);
		} else {
			$data = $result['body'];
		}

		if ($code >= 400) {
			throw new StripeConnectResponseError(
				['response' => $result, 'data' => $data],
				'Request failed',
				$code
			);
		} else {
			return $data;
		}
	}
}
