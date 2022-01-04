<?php declare (strict_types = 1);

namespace TestApi74\Utility;

use JsonSerializable;

class JsonTranslatableMessage implements JsonSerializable
{

	private string $message;

	public function __construct(string $message)
	{
		$this->message = $message;
	}

	/**
	 * @return string
	 */
	public function jsonSerialize() //phpcs:ignore
	{
		return $this->message;
	}

}
