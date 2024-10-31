<?php

namespace ZPOS\Structure;

class StripeConnectResponseError extends \Exception
{
	public $response;
	public $data;

	public function __construct($responseWithData, $message, $code = 0, \Exception $previous = null)
	{
		$this->response = $responseWithData['response'];
		$this->data = $responseWithData['data'];
		parent::__construct($message, $code, $previous);
	}

	public function getResponse()
	{
		return $this->response;
	}

	public function getData()
	{
		return $this->data;
	}
}
