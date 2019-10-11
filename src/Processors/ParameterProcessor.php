<?php declare (strict_types = 1);

namespace Wedo\OpenApiGenerator\Processors;

use Exception;
use InvalidArgumentException;
use Nette\Reflection\Parameter as MethodParameter;
use Nette\Utils\Strings;
use Wedo\OpenApiGenerator\Config;
use Wedo\OpenApiGenerator\Generator;
use Wedo\OpenApiGenerator\Helper;
use Wedo\OpenApiGenerator\OpenApiDefinition\Parameter;
use Wedo\OpenApiGenerator\OpenApiDefinition\Path;

class ParameterProcessor
{

	/** @var ReferenceProcessor */
	private $referenceProcessor;

	/** @var string|null */
	private $currentClassPath;

	/** @var Config */
	private $config;

	public function __construct(Generator $generator)
	{
		$this->referenceProcessor = $generator->getRefProcessor();
		$this->currentClassPath = $generator->getCurrentClassPath();
		$this->config = $generator->getConfig();
	}

	/**
	 * @param string[][]        $annotations
	 * @param MethodParameter[] $methodParams
	 */
	public function process(array $annotations, array $methodParams, string $requestMethod, Path $path): void
	{
		if (!isset($annotations['param'])) {
			return;
		}

		foreach ($annotations['param'] as $param) {
			if (count($methodParams) > 0 && $methodParams[0]->getClass() !== null && $methodParams[0]->getClass()->is($this->config->baseRequest)) {
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
	 * @param MethodParameter[] $methodParameters
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
	protected function generateParamType(string $requestMethod, MethodParameter $methodParam, Parameter $jsonParam): void
	{
		if ($methodParam->getType() === null) {
			throw new Exception('Type not set for parameter  in ' . $this->currentClassPath);
		}

		$jsonParam->schema = ['type' => Helper::convertType($methodParam->getType()->__toString())];
		if ($methodParam->isDefaultValueAvailable()) {
			if ($requestMethod === 'get') {
				$jsonParam->in = 'query';
			}
		} else {
			$jsonParam->required = true;
			if ($requestMethod === 'get') {
				$jsonParam->in = 'path';
			} else {
				$pClass = $methodParam->getClass();
				$this->referenceProcessor->generateRef($pClass);
				$jsonParam->schema = ['$ref' => '#/components/schemas/' . $pClass->getShortName()];
				unset($jsonParam->type);
			}
		}
	}

}
