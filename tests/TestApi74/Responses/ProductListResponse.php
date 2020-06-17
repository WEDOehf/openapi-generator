<?php declare (strict_types = 1);

namespace Wedo\OpenApiGenerator\Tests\TestApi74\Responses;

use Wedo\OpenApiGenerator\Tests\TestApi74\Entities\ProductListItem;

class ProductListResponse extends Response
{

	/**
	 * @var ProductListItem[]
	 */
	public array $data;

}
