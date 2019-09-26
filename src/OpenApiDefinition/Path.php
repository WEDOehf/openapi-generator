<?php declare(strict_types = 1);

namespace Wedo\OpenApiGenerator\OpenApiDefinition;

use stdClass;

class Path extends stdClass
{

	/** @var string */
	public $summary;

	/** @var Parameter[] */
	public $parameters = [];

	/** @var Response[] */
	public $responses;

}
