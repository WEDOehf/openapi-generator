<?php declare (strict_types = 1);

namespace Wedo\OpenApiGenerator\Tests;

use PHPUnit\Framework\TestCase;
use TestApi\Utility\JsonTranslatableMessage;
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
		$config->typeReplacement = [JsonTranslatableMessage::class => 'string'];
		$generator = new Generator($config);
		$json = $generator->generate();
		$this->assertJson($json);
		file_put_contents(__DIR__ . '/out73.json', $json);

		$this->assertJsonFileEqualsJsonFile(__DIR__ . '/expected73.json', __DIR__ . '/out73.json');
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
		$config->typeReplacement = [\TestApi74\Utility\JsonTranslatableMessage::class => 'string'];
		$generator = new Generator($config);
		$json = $generator->generate();
		$this->assertJson($json);
		file_put_contents(__DIR__ . '/out74.json', $json);

		$this->assertJsonFileEqualsJsonFile(__DIR__ . '/expected74.json', __DIR__ . '/out74.json');
	}

	public function testGeneratePhp73SameAsPhp74(): void
	{
		if (PHP_VERSION_ID < 70400) {
			$this->assertTrue(true);
			return;
		}

		$this->testGenerate();
		$this->testGeneratePhp74();
		$this->assertFileEquals(__DIR__ . '/out74.json', __DIR__ . '/out73.json');
	}

}
