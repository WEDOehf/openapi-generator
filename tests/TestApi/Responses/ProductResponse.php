<?php declare (strict_types = 1);

namespace Wedo\OpenApiGenerator\Tests\TestApi\Responses;

use DateTime;

class ProductResponse
{

	/** @var int */
	public $id;

	/** @var string */
	public $title;

	/** @var int */
	public $price;

	/** @var bool */
	public $published;

	/** @var string[] */
	public $categories;

	/** @var DateTime */
	public $expires_at;

}
