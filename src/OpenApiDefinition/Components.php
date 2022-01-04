<?php declare(strict_types = 1);

namespace Wedo\OpenApiGenerator\OpenApiDefinition;

use stdClass;

class Components extends stdClass
{

	/** @var mixed[] */
	public array $schemas = [];

	/** @var mixed[] */
	public array $securitySchemes = [];

}
