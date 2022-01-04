<?php declare (strict_types = 1);

namespace Wedo\OpenApiGenerator\Processors;

use Exception;
use Nette\SmartObject;
use Nette\Utils\Strings;
use ReflectionClass;
use Wedo\OpenApiGenerator\AnnotationParser;
use Wedo\OpenApiGenerator\Generator;
use Wedo\OpenApiGenerator\Helper;

class ClassProcessor
{

	use SmartObject;

	/** @var callable */
	public $onProcess;

	private Generator $generator;

	private MethodProcessor $methodProcessor;

	public function __construct(Generator $generator)
	{
		$this->generator = $generator;
		$this->methodProcessor = new MethodProcessor($generator);
	}

	public function process(string $className, string $dir): void
	{
		$classRef = new ReflectionClass($className); //@phpstan-ignore-line

		if ($classRef->isAbstract()) {
			return;
		}

		$annotations = AnnotationParser::getAll($classRef);

		if (isset($annotations[$this->generator->getConfig()->internalAnnotation])) {
			return;
		}

		if (count($classRef->getAttributes($this->generator->getConfig()->internalAnnotation)) > 0) {
			return;
		}

		$this->onProcess($classRef);
		$dir = strtolower($dir);
		$className = Strings::before($classRef->getShortName(), $this->generator->getConfig()->controllerSuffix, -1);

		if ($className === null) {
			throw new Exception('Wrong naming for controller ' . $classRef->getShortName());
		}

		$path = Helper::camelCase2path($className);
		$this->generator->setCurrentClassPath(($dir !== '' ? ($dir . '/') : '') . $path);

		$methods = $classRef->getMethods();

		foreach ($methods as $method) {
			if (is_a($method->getDeclaringClass()->getName(), $classRef->getName(), true)) {
				$this->methodProcessor->process($method);
			}
		}
	}

	public function getMethodProcessor(): MethodProcessor
	{
		return $this->methodProcessor;
	}

}
