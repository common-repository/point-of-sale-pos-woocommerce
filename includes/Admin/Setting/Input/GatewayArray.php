<?php

namespace ZPOS\Admin\Setting\Input;

use ZPOS\Admin\Setting\InputBase;
use ZPOS\Model;

class GatewayArray extends InputBase
{
	protected $type = 'gateway_array';

	public function __construct($label, $name, $value, $description = null)
	{
		$args = [
			'description' => $description,
			'available_tax_classes' => array_values(Model\Gateway::get_available_tax_classes()),
		];
		parent::__construct($label, $name, $value, $args);
	}
}
