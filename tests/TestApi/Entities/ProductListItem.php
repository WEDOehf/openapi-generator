<?php declare (strict_types = 1);

namespace Wedo\OpenApiGenerator\Tests\TestApi\Entities;

use TestApi\Utility\JsonTranslatableMessage;

class ProductListItem
{

	/** @var int */
	public $id;

	/** @var string */
	public $name;

	/** @var JsonTranslatableMessage */
	public $translatable_name;

}
