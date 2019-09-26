<?php declare (strict_types = 1);

namespace Wedo\OpenApiGenerator\Tests;

use PHPUnit\Framework\TestCase;
use Wedo\OpenApiGenerator\Helper;

class HelperTest extends TestCase
{

	public function testConvertType(): void
	{
		$this->assertEquals('boolean', Helper::convertType('bool'));
		$this->assertEquals('integer', Helper::convertType('int'));
		$this->assertEquals('number', Helper::convertType('float'));
		$this->assertEquals('number', Helper::convertType('double'));
		$this->assertEquals('unknown', Helper::convertType('unknown'));
	}

	public function testGetUseStatements(): void
	{
		$useStatements = Helper::getUseStatements(__DIR__ . '/Files/EmptyFile.php');
		$this->assertCount(0, $useStatements);

		$useStatements = Helper::getUseStatements(__DIR__ . '/Files/TestClass.php');
		$this->assertCount(0, $useStatements);

		$useStatements = Helper::getUseStatements(__DIR__ . '/Files/FileWithUsage.php');
		$this->assertCount(1, $useStatements);

		$useStatements = Helper::getUseStatements(__DIR__ . '/Files/TestClassWithTwoUsages.php');
		$this->assertCount(2, $useStatements);
	}

	public function testCamelCaseToPath(): void
	{
		$this->assertEquals(Helper::camelCase2path('OnePath'), 'one-path');
		$this->assertEquals(Helper::camelCase2path('one'), 'one');
	}

}
