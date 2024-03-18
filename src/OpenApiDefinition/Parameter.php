<?php declare(strict_types = 1);

namespace Wedo\OpenApiGenerator\OpenApiDefinition;

class Parameter
{

	public string $name;

	public string $in;

	public string $description;

	public bool $required;

	public mixed $schema;

}
