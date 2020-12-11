<?php declare (strict_types = 1);

namespace Wedo\OpenApiGenerator\Tests\TestApi74\Entities;

use TestApi74\Utility\JsonTranslatableMessage;

class ProductListItem
{

	public int $id;

	public string $name;

	public JsonTranslatableMessage $translatable_name;
}
