<?php declare (strict_types = 1);

namespace TestApi74\Utility;

class JsonTranslatableMessage  implements \JsonSerializable
{

	private string $message;

	public function __construct($message)
	{
		$this->message = $message;
	}

	public function jsonSerialize()
	{
		return $this->message;
	}
}
