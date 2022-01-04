<?php declare (strict_types = 1);

namespace Wedo\OpenApiGenerator\Processors;

use Exception;
use Nette\SmartObject;
use Nette\Utils\Strings;
use ReflectionClass;
use ReflectionMethod;
use ReflectionType;
use Wedo\OpenApiGenerator\AnnotationParser;
use Wedo\OpenApiGenerator\Exceptions\InvalidReturnTypeDefinitionException;
use Wedo\OpenApiGenerator\Generator;
use Wedo\OpenApiGenerator\Helper;
use Wedo\OpenApiGenerator\OpenApiDefinition\Path;
use Wedo\OpenApiGenerator\OpenApiDefinition\Response;

class MethodProcessor
{

	use SmartObject;

	/** @var callable */
	public $onProcess;

	private Generator $generator;

	private ParameterProcessor $parameterProcessor;

	public function __construct(Generator $generator)
	{
		$this->generator = $generator;
		$this->parameterProcessor = new ParameterProcessor($generator);
	}

	/**
	 * @throws Exception
	 */
	public function process(ReflectionMethod $method): void
	{
		if (!$method->isPublic() || $method->isConstructor()) {
			return;
		}

		$annotations = AnnotationParser::getAll($method);

		if (isset($annotations[$this->generator->getConfig()->internalAnnotation])) {
			return;
		}

		if (count($method->getAttributes($this->generator->getConfig()->internalAnnotation)) > 0) {
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

			if ($methodParam->getType()->isBuiltin() && !$methodParam->isDefaultValueAvailable()) { //@phpstan-ignore-line
				$pathKey .= '/{' . $methodParam->name . '}';
			}
		}

		$this->generator->getJson()->paths['/' . $this->generator->getCurrentClassPath() . '/' . $pathKey][$requestMethod] = $path;
	}

	/**
	 * @return Response[]
	 */
	public function generateResponses(ReflectionType $returnType): array
	{
		$returnType = new ReflectionClass($returnType->getName());
		$this->generator->getRefProcessor()->generateRef($returnType);
		$responses = [];
		$responses[200] = $this->createResponse('Success response', $returnType->getShortName());

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

	/**
	 * @param array<string, array<int, string|null>> $annotations
	 */
	protected function getRequestMethod(array $annotations, ReflectionMethod $method): string
	{
		$methodParams = $method->getParameters();

		if (isset($methodParams[0]) && ($methodParams[0]->getType() !== null)) {
			$type = $methodParams[0]->getType()->getName();

			if (class_exists($type) && is_a((new ReflectionClass($type))->getName(), $this->generator->getConfig()->baseRequest, true)) {
				return 'post';
			}
		}

		$annotationName = $this->generator->getConfig()->httpMethodAnnotation;

		if (isset($annotations[$annotationName])) {
			return Strings::lower($annotations[$annotationName][0]);
		}

		$methodAttributes = $method->getAttributes($this->generator->getConfig()->httpMethodAnnotation);

		if (count($methodAttributes) > 0) {
			$attribute = $methodAttributes[0]->newInstance();

			return Strings::lower($attribute->{$this->generator->getConfig()->httpMethodAttributeProperty}); //@phpstan-ignore-line
		}

		return 'get';
	}

}
