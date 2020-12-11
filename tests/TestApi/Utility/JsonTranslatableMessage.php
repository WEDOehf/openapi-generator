<?php declare (strict_types = 1);

namespace TestApi\Utility;

class JsonTranslatableMessage  implements \JsonSerializable
{

	/**
	 * @var string
	 */
	private $message;

	public function __construct(string $message)
	{
		$this->message = $message;
	}

	public function jsonSerialize()
	{
		return $this->message;
	}
}
