<?php declare (strict_types = 1);

namespace Wedo\OpenApiGenerator\Tests\TestApi\Responses;

use Wedo\OpenApiGenerator\Tests\TestApi\Entities\ProductListItem;

class GenericsResponse
{

	/** @var array<string, mixed> free-form map keyed by the caller */
	public $meta;

	/** @var array<int, ProductListItem> */
	public $items;

	/** @var list<string> */
	public $tags;

	/** @var array<string, array<string, int>> */
	public $counts;

	/** @var array<string, ProductListItem> */
	public $by_key;

	/** @var mixed[] */
	public $anything;

	/** @var array<string, int>|null */
	public $nullable_map;

	/** @var int|string */
	public $id_or_slug;

}
