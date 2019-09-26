<?php declare(strict_types = 1);

namespace Wedo\OpenApiGenerator\OpenApiDefinition;

use stdClass;

class Components extends stdClass
{

	/** @var mixed[] */
	public $schemas = [];

	/** @var mixed[] */
	public $securitySchemes = [];

}
