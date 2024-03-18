<?php declare (strict_types = 1);

namespace Wedo\OpenApiGenerator\Processors;

use Exception;
use InvalidArgumentException;
use Nette\Utils\Strings;
use ReflectionNamedType;
use ReflectionParameter;
use Wedo\OpenApiGenerator\Config;
use Wedo\OpenApiGenerator\Generator;
use Wedo\OpenApiGenerator\Helper;
use Wedo\OpenApiGenerator\OpenApiDefinition\Parameter;
use Wedo\OpenApiGenerator\OpenApiDefinition\Path;

class ParameterProcessor
{

	private ReferenceProcessor $referenceProcessor;

	private ?string $currentClassPath = null;

	private Config $config;

	public function __construct(Generator $generator)
	{
		$this->referenceProcessor = $generator->getRefProcessor();
		$this->currentClassPath = $generator->getCurrentClassPath();
		$this->config = $generator->getConfig();
	}

	/**
	 * @param string[][]        $annotations
	 * @param ReflectionParameter[] $methodParams
	 */
	public function process(array $annotations, array $methodParams, string $requestMethod, Path $path): void
	{
		if (!isset($annotations['param'])) {
			$annotations['param'] = [];
		}

		//add method typehint parameters to annotations if they're not there
		foreach ($methodParams as $methodParam) {
			if ($this->hasParamAnnotation($annotations['param'], $methodParam->getName())) {
				continue;
			}

			/** @var ReflectionNamedType $type */
			$type = $methodParam->getType();
			array_unshift($annotations['param'], $type->getName() . ' $' . $methodParam->getName());
		}

		foreach ($annotations['param'] as $param) {
			if (count($methodParams) > 0 && $methodParams[0]->getType() !== null && is_a($methodParams[0]->getType()->getName(), $this->config->baseRequest, true)) {
				$param = $this->generateParameter($param, $methodParams, $requestMethod);
				$path->requestBody = [
					'content' => [
						'application/json' => ['schema' => $param->schema],
					],
				];

				continue;
			}

			$path->parameters[] = $this->generateParameter($param, $methodParams, $requestMethod);
		}
	}

	/**
	 * @param ReflectionParameter[] $methodParameters
	 */
	public function generateParameter(string $annotation, array $methodParameters, string $requestMethod): Parameter
	{
		$jsonParam = new Parameter();
		$param = explode(' ', $annotation);

		if (count($param) < 2) {
			throw new InvalidArgumentException('Parameter has wrongly defined type hint on ' . $methodParameters[0]->getDeclaringClass()->getName());
		}

		$jsonParam->name = Strings::trim($param[1], '$ ');
		$jsonParam->required = false;
		$jsonParam->in = $requestMethod === 'post' ? 'body' : 'query';

		$paramInMethodCall = false;

		foreach ($methodParameters as $methodParameter) {
			if ($methodParameter->getName() === $jsonParam->name) {
				$this->generateParamType($requestMethod, $methodParameter, $jsonParam);
				$paramInMethodCall = true;
				break;
			}
		}

		if (!$paramInMethodCall) {
			$jsonParam->schema = ['type' => Helper::convertType($param[0])];
		}

		unset($param[0], $param[1]);

		if (count($param) > 0) {
			$jsonParam->description = implode(' ', $param);
		}

		if (!isset($jsonParam->description)) {
			$jsonParam->description = '';
		}

		return $jsonParam;
	}

	/**
	 * @throws Exception
	 */
	protected function generateParamType(string $requestMethod, ReflectionParameter $methodParam, Parameter $jsonParam): void
	{
		if ($methodParam->getType() === null) {
			throw new Exception('Type not set for parameter  in ' . $this->currentClassPath);
		}

		$jsonParam->schema = ['type' => Helper::convertType($methodParam->getType()->getName())];

		if ($methodParam->isDefaultValueAvailable()) {
			if ($requestMethod === 'get') {
				$jsonParam->in = 'query';
			}
		} else {
			$jsonParam->required = true;

			if ($requestMethod === 'get') {
				$jsonParam->in = 'path';
			} else {
				$pClass = new \ReflectionClass($methodParam->getType()->getName());
				$this->referenceProcessor->generateRef($pClass);
				$jsonParam->schema = ['$ref' => '#/components/schemas/' . $pClass->getShortName()];
				unset($jsonParam->type);
			}
		}
	}

	/**
	 * @param string[] $paramList
	 */
	private function hasParamAnnotation(array $paramList, string $param): bool
	{
		foreach ($paramList as $existingParam) {
			if (explode(' ', $existingParam)[1] === '$' . $param) {
				return true;
			}
		}

		return false;
	}

}
