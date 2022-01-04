<?php declare (strict_types = 1);

namespace TestApi\Utility;

use JsonSerializable;

class JsonTranslatableMessage implements JsonSerializable
{

	/** @var string */
	private $message;

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
