<?php declare (strict_types = 1);

namespace Wedo\OpenApiGenerator\Tests\TestApi74\Responses;

use DateTime;

class ProductResponse
{

	public int $id;

	public string $title;

	public int $price;

	public bool $published;

	/** @var string[] */
	public array $categories;

	public DateTime $expires_at;

}
