<?php declare(strict_types = 1);

namespace Wedo\OpenApiGenerator\OpenApiDefinition;

use stdClass;

class Path extends stdClass
{

	public string $summary;

	/** @var Parameter[] */
	public array $parameters = [];

	/** @var Response[] */
	public array $responses;

}
