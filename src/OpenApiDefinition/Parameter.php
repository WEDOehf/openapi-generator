<?php declare(strict_types = 1);

namespace Wedo\OpenApiGenerator\OpenApiDefinition;

/**
 * @property mixed $schema;
 */
class Parameter
{

	/** @var string */
	public $name;

	/** @var string */
	public $in;

	/** @var string */
	public $description;

	/** @var bool */
	public $required;

}
