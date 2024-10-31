<?php

namespace ZPOS\Admin\Setting;

abstract class InputBase
{
	protected $type = null;
	protected $label = null;
	public $name = null;
	protected $value = null;
	protected $args = [];
	public $sanitize;
	public $savePost = null;
	public $defaultValue = null;

	/**
	 * @param $label string|null
	 * @param $name string
	 * @param $value callback|mixed
	 * @param array $args
	 */

	public function __construct($label, $name, $value, $args = [])
	{
		$this->label = $label;
		$this->name = $name;
		$this->value = $value;
		if (isset($args['savePost'])) {
			$this->savePost = $args['savePost'];
			unset($args['savePost']);
		}
		if (isset($args['sanitize'])) {
			$this->sanitize = $args['sanitize'];
			unset($args['sanitize']);
		}
		$this->args = $args;
	}

	public function getData(...$args)
	{
		$argsData = $this->args;

		$basic_data = [
			'label' => $this->label,
			'name' => $this->name,
			'value' => is_callable($this->value)
				? call_user_func_array($this->value, $args)
				: $this->value,
			'type' => $this->type,
		];

		if (isset($argsData['callback']) && is_callable($argsData['callback'])) {
			$basic_data = array_merge($basic_data, call_user_func_array($argsData['callback'], $args));
			unset($argsData['callback']);
		}

		return array_merge($argsData, $basic_data);
	}
}
