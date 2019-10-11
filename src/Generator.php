<?php declare(strict_types = 1);

namespace Wedo\OpenApiGenerator;

use Nette\SmartObject;
use Nette\Utils\Finder;
use Wedo\OpenApiGenerator\OpenApiDefinition\Schema;
use Wedo\OpenApiGenerator\Processors\ClassProcessor;
use Wedo\OpenApiGenerator\Processors\ReferenceProcessor;

/**
 * Class Generator
 */
class Generator
{

	use SmartObject;

	/** @var Schema */
	private $json;

	/** @var string */
	private $currentClassPath;

	/** @var ClassProcessor */
	private $classProcessor;

	/** @var ReferenceProcessor */
	private $refProcessor;

	/** @var Config */
	private $config;

	/** @var callable */
	public $onBeforeGenerate;

	public function __construct(Config $config)
	{
		$this->config = $config;
		$this->json = new Schema($this->config->serverUrl);
		$this->refProcessor = new ReferenceProcessor($this);
		$this->classProcessor = new ClassProcessor($this);
	}


	public function generate(): string
	{
		$this->onBeforeGenerate();
		$this->processDirectory();
		$dirs = glob(rtrim($this->config->path . '/') . '/*', GLOB_ONLYDIR);
		if ($dirs !== false) {
			foreach ($dirs as $dir) {
				$dirname = basename($dir);
				$this->processDirectory($dirname);
			}
		}

		$json = (string) json_encode($this->json, JSON_PRETTY_PRINT);
		return $json;
	}

	public function getJson(): Schema
	{
		return $this->json;
	}

	public function getCurrentClassPath(): ?string
	{
		return $this->currentClassPath;
	}

	public function setCurrentClassPath(string $currentClassPath): void
	{
		$this->currentClassPath = $currentClassPath;
	}

	public function getRefProcessor(): ReferenceProcessor
	{
		return $this->refProcessor;
	}

	public function getClassProcessor(): ClassProcessor
	{
		return $this->classProcessor;
	}

	public function getConfig(): Config
	{
		return $this->config;
	}

	private function processDirectory(string $dir = ''): void
	{
		$nsDir = ($dir !== '' ? ($dir . '\\') : '');
		$path = rtrim($this->config->path, '/') . '/';
		foreach (Finder::findFiles('*' . $this->config->controllerSuffix . '.php')->in($path . $dir) as $file) {

			$className = $this->config->namespace . $nsDir . str_replace('.php', '', $file->getFileName());

			$this->classProcessor->process($className, $dir);
		}
	}

}
