<?php

namespace ZPOS\Admin\Setting\Input;

use ZPOS\Admin\Setting\InputBase;

class CreateTerminalReader extends InputBase
{
	protected $type = 'create_terminal_reader';

	public function __construct($label, $name, $args = [])
	{
		parent::__construct($label, $name, null, $args);
	}
}
