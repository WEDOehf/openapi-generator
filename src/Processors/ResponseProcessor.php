<?php declare (strict_types = 1);

namespace Wedo\OpenApiGenerator\Processors;

use Nette\Reflection\ClassType;
use ReflectionType;
use Wedo\OpenApiGenerator\Generator;
use Wedo\OpenApiGenerator\OpenApiDefinition\Response;

class ResponseProcessor
{

	private Generator $generator;

	public function __construct(Generator $generator)
	{
		$this->generator = $generator;
	}

	/**
	 * @return Response[]
	 */
	public function generateResponses(ReflectionType $returnType): array
	{
		$returnType = ClassType::from($returnType->getName());
		$this->generator->getRefProcessor()->generateRef($returnType);
		$responses = [];
		$responses[200] = $this->createResponse('Success response', $returnType->shortName);

		return $responses;
	}

	public function createResponse(string $description, string $type): Response
	{
		$response = new Response();
		$response->description = $description;
		$response->content = [
			'application/json' => [
				'schema' => [
					'$ref' => '#/components/schemas/' . $type,
				],
			],
		];

		return $response;
	}

}
