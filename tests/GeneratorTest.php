<?php declare (strict_types = 1);

namespace Wedo\OpenApiGenerator\Tests;

use PHPUnit\Framework\TestCase;
use Wedo\OpenApiGenerator\Config;
use Wedo\OpenApiGenerator\Generator;
use Wedo\OpenApiGenerator\Tests\TestApi\Requests\BaseRequest as Base73Request;
use Wedo\OpenApiGenerator\Tests\TestApi74\Requests\BaseRequest;

class GeneratorTest extends TestCase
{

	public function testGenerate(): void
	{
		$config = new Config();
		$config->serverUrl = 'http://www.test-api.com/api/v1';
		$config->path = __DIR__ . '/TestApi/Controllers';
		$config->namespace = 'Wedo\OpenApiGenerator\Tests\TestApi\\Controllers\\';
		$config->baseRequest = Base73Request::class;
		$generator = new Generator($config);
		$json = $generator->generate();
		$this->assertJson($json);
		file_put_contents(__DIR__ . '/out.json', $json);
	}

	public function testGeneratePhp74(): void
	{
		if (PHP_VERSION_ID < 70400) {
			$this->assertTrue(true);
			return;
		}

		$config = new Config();
		$config->serverUrl = 'http://www.test-api.com/api/v1';
		$config->path = __DIR__ . '/TestApi74/Controllers';
		$config->namespace = 'Wedo\OpenApiGenerator\Tests\TestApi74\\Controllers\\';
		$config->baseRequest = BaseRequest::class;
		$generator = new Generator($config);
		$json = $generator->generate();
		$this->assertJson($json);
		file_put_contents(__DIR__ . '/out.json', $json);
	}

}
