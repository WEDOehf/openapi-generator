<?php declare (strict_types = 1);

namespace Wedo\OpenApiGenerator\Tests\TestApi74\Responses;

use Wedo\OpenApiGenerator\Tests\TestApi74\Entities\ProductListItem;

class GenericsResponse
{

	/** @var array<string, mixed> free-form map keyed by the caller */
	public array $meta;

	/** @var array<int, ProductListItem> */
	public array $items;

	/** @var list<string> */
	public array $tags;

	/** @var array<string, array<string, int>> */
	public array $counts;

	/** @var array<string, ProductListItem> */
	public array $by_key;

	/** @var mixed[] */
	public array $anything;

	/** @var array<string, int>|null */
	public ?array $nullable_map;

	public int|string $id_or_slug;

}
