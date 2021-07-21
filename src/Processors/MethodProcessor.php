<?php declare (strict_types = 1);

namespace Wedo\OpenApiGenerator\Processors;

use Exception;
use Nette\Reflection\ClassType;
use Nette\Reflection\Method;
use Nette\Reflection\Parameter;
use Nette\SmartObject;
use Nette\Utils\Strings;
use ReflectionType;
use Wedo\OpenApiGenerator\Exceptions\InvalidReturnTypeDefinitionException;
use Wedo\OpenApiGenerator\Generator;
use Wedo\OpenApiGenerator\Helper;
use Wedo\OpenApiGenerator\OpenApiDefinition\Path;
use Wedo\OpenApiGenerator\OpenApiDefinition\Response;

class MethodProcessor
{

	use SmartObject;

	/** @var Generator */
	private $generator;

	/** @var ParameterProcessor */
	private $parameterProcessor;

	/** @var callable */
	public $onProcess;

	public function __construct(Generator $generator)
	{
		$this->generator = $generator;
		$this->parameterProcessor = new ParameterProcessor($generator);
	}

	/**
	 * @throws Exception
	 */
	public function process(Method $method): void
	{
		if (!$method->isPublic() || $method->isConstructor()) {
			return;
		}

		$annotations = $method->getAnnotations();
		if (isset($annotations[$this->generator->getConfig()->internalAnnotation])) {
			return;
		}

		if (PHP_VERSION_ID > 80000 && count($method->getAttributes($this->generator->getConfig()->internalAnnotation)) > 0) {
			return;
		}

		$path = new Path();
		$path->summary = trim(implode("\n", $annotations['description'] ?? []));
		if ($method->getReturnType() === null) {
			throw new InvalidReturnTypeDefinitionException('Return type not set on method ' . $method->getName());
		}

		$path->responses = $this->generateResponses($method->getReturnType());
		$this->onProcess($method, $path);

		$requestMethod = $this->getRequestMethod($annotations, $method);

		$methodParams = $method->getParameters();
		$this->parameterProcessor->process($annotations, $methodParams, $requestMethod, $path);

		$pathKey = Helper::camelCase2path($method->getShortName());
		foreach ($methodParams as $methodParam) {
			if ($methodParam->getType() === null) {
				continue;
			}

			if ($methodParam->getType()->isBuiltin() && !$methodParam->isDefaultValueAvailable()) {
				$pathKey .= '/{' . $methodParam->name . '}';
			}
		}

		$this->generator->getJson()->paths['/' . $this->generator->getCurrentClassPath() . '/' . $pathKey][$requestMethod] = $path;
	}

	/**
	 * @param string[] $annotations
	 * @param Parameter[] $methodParams
	 */
	protected function getRequestMethod(array $annotations, Method $method): string
	{
		$methodParams = $method->getParameters();

		if (isset($methodParams[0]) && ($methodParams[0]->getType() !== null)) {
			$type = $methodParams[0]->getType()->getName();
			if (class_exists($type) && ClassType::from($type)->is($this->generator->getConfig()->baseRequest)) {
				return 'post';
			}
		}

		$annotationName = $this->generator->getConfig()->httpMethodAnnotation;

		if (isset($annotations[$annotationName])) {
			return Strings::lower($annotations[$annotationName][0]);
		}

		$methodAttributes = PHP_VERSION_ID < 80000 ? [] : $method->getAttributes($this->generator->getConfig()->httpMethodAnnotation);

		if (count($methodAttributes) > 0) {
			$attribute = $methodAttributes[0]->newInstance();
			return Strings::lower($attribute->{$this->generator->getConfig()->httpMethodAttributeProperty});
		}

		return 'get';
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
