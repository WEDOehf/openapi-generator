<?php declare (strict_types = 1);

namespace Wedo\OpenApiGenerator\Tests;

use PHPUnit\Framework\TestCase;
use Wedo\OpenApiGenerator\Config;
use Wedo\OpenApiGenerator\Generator;
use Wedo\OpenApiGenerator\Tests\TestApi\Requests\BaseRequest;

class GeneratorTest extends TestCase
{

	public function testGenerate(): void
	{
		$config = new Config();
		$config->serverUrl = 'http://www.test-api.com/api/v1';
		$config->path = __DIR__ . '/TestApi/Controllers';
		$config->namespace = 'Wedo\OpenApiGenerator\Tests\TestApi\\Controllers\\';
		$config->baseRequest = BaseRequest::class;
		$generator = new Generator($config);
		$json = $generator->generate();
		$this->assertJson($json);
	}

}
