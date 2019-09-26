<?php declare (strict_types = 1);

namespace Wedo\OpenApiGenerator\Processors;

use Exception;
use Nette\Reflection\ClassType;
use Nette\SmartObject;
use Nette\Utils\Strings;
use Wedo\OpenApiGenerator\Generator;
use Wedo\OpenApiGenerator\Helper;

class ClassProcessor
{

	use SmartObject;

	/** @var Generator */
	private $generator;

	/** @var MethodProcessor */
	private $methodProcessor;

	/** @var callable */
	public $onProcess;

	public function __construct(Generator $generator)
	{
		$this->generator = $generator;
		$this->methodProcessor = new MethodProcessor($generator);
	}

	public function process(string $className, string $dir): void
	{
		$classRef = ClassType::from($className);
		if ($classRef->isAbstract()) {
			return;
		}

		$annotations = $classRef->getAnnotations();
		if (isset($annotations['internal'])) {
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
			if ($method->getDeclaringClass()->is($classRef->getName())) {
				$this->methodProcessor->process($method);
			}
		}
	}

}
