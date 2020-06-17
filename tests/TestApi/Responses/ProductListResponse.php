<?php declare (strict_types = 1);

namespace Wedo\OpenApiGenerator\Tests\TestApi\Responses;

use Wedo\OpenApiGenerator\Tests\TestApi\Entities\ProductListItem;

class ProductListResponse extends Response
{

	/** @var ProductListItem[] */
	public $data;

}
