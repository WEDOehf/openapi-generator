<?php declare (strict_types = 1);

namespace Wedo\OpenApiGenerator\Tests\Files;

use Wedo\OpenApiGenerator\Generator;
use Wedo\OpenApiGenerator\Helper;

class TestClassWithTwoUsages
{

	public function test(): string
	{
		return Generator::class;
	}

	public function test2(): string
	{
		return Helper::class;
	}

}
