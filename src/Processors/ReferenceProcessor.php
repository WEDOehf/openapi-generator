<?php declare (strict_types = 1);

namespace Wedo\OpenApiGenerator\Processors;

use Exception;
use Nette\Utils\ArrayHash;
use Nette\Utils\Strings;
use ReflectionClass;
use ReflectionProperty;
use Wedo\OpenApiGenerator\AnnotationParser;
use Wedo\OpenApiGenerator\Generator;
use Wedo\OpenApiGenerator\Helper;
use Wedo\OpenApiGenerator\OpenApiDefinition\Schema;

class ReferenceProcessor
{

	private Generator $generator;

	private Schema $json;

	public function __construct(Generator $generator)
	{
		$this->generator = $generator;
		$this->json = $generator->getJson();
	}

	public function generateRef(ReflectionClass $type): void
	{
		$required = [];
		$parent = $type->getParentClass();

		//inheritance
		if (($parent !== false) && (!in_array($parent->getShortName(), $this->generator->getConfig()->skipClasses, true))) {
			if (!isset($this->json->components->schemas[$parent->getShortName()])) {
				$this->generateRef($parent);
			}

			$this->generator->getJson()
				->components->schemas[$type->getShortName()]['allOf'][] = ['$ref' => '#/components/schemas/' . $parent->getShortName()];
		}

		$properties = $type->getProperties();
		$jsonProperties = [];

		foreach ($properties as $property) {
			if (!$property->isPublic() || ($parent !== false && $parent->hasProperty($property->getName()))) {
				continue;
			}

			$propertyAnnotations = AnnotationParser::getAll($property);

			if (isset($propertyAnnotations[$this->generator->getConfig()->internalAnnotation])) {
				continue;
			}

			if (count($property->getAttributes($this->generator->getConfig()->internalAnnotation)) > 0) {
				continue;
			}

			if (
				isset($propertyAnnotations[$this->generator->getConfig()->requiredAnnotation]) ||
				count($property->getAttributes($this->generator->getConfig()->requiredAnnotation)) > 0
			) {
				$required[] = $property->getName();
			}

			$jsonProperties[$property->getName()] = $this->getJsonProperty($type, $property);
		}

		$this->json->components->schemas[$type->getShortName()]['properties'] = $jsonProperties;

		if (count($required) > 0) {
			$this->json->components->schemas[$type->getShortName()]['required'] = $required;
		}
	}

	protected function getJsonProperty(ReflectionClass $type, ReflectionProperty $property): ArrayHash
	{
		$jsonProperty = new ArrayHash();
		[$propertyType, $arrayDimensions] = $this->getPropertyType($type, $property, $jsonProperty);

		if (isset($this->generator->getConfig()->typeReplacement[$propertyType])) {
			$propertyType = $this->generator->getConfig()->typeReplacement[$propertyType];
		}

		if (class_exists($propertyType)) {
			$jsonProperty = $this->extractObjectProperty($propertyType, $jsonProperty, $arrayDimensions);
		} else {
			if (isset($this->generator->getConfig()->typeReplacement[$propertyType])) {
				$property = new ReflectionClass($this->generator->getConfig()->typeReplacement[$propertyType]); //@phpstan-ignore-line
			}

			$this->extractBuiltInProperty($arrayDimensions, $jsonProperty, $propertyType);
		}

		$propertyAnnotations = AnnotationParser::getAll($property);

		if (
			isset($propertyAnnotations['description'])
			&& Strings::trim($propertyAnnotations['description'][0]) !== ''
		) {
			$jsonProperty->description = implode("\n", $propertyAnnotations['description']);
		}

		return $jsonProperty;
	}

	protected function getEnumProperty(ArrayHash $jsonProperty, ReflectionClass $propertyClass): ArrayHash
	{
		$constants = $propertyClass->getConstants();
		$description = '';

		foreach ($constants as $key => $value) {
			$description .= $key . ' => ' . $value . "\n";
		}

		$jsonProperty->type = 'string';
		$jsonProperty->enum = array_values($constants);
		$jsonProperty->description = $description;

		return $jsonProperty;
	}

	protected function extractObjectProperty(string $propertyType, ArrayHash $jsonProperty, int $arrayDimensions = 0): ArrayHash
	{
		if ($propertyType === $this->generator->getConfig()->dateTimeClass) {
			$jsonProperty->type = 'string';
			$jsonProperty->format = 'date-time';

			return $jsonProperty;
		}

		$propertyClass = new ReflectionClass($propertyType); //@phpstan-ignore-line

		if (is_a($propertyClass->getName(), $this->generator->getConfig()->baseEnum, true)) {
			return $this->getEnumProperty($jsonProperty, $propertyClass);
		}

		$this->generateRef($propertyClass);

		if ($arrayDimensions > 0) {
			$jsonProperty->type = 'array';
			$endItem = ['$ref' => '#/components/schemas/' . $propertyClass->getShortName()];
			$jsonProperty->items = $arrayDimensions === 2 ? [
					'type' => 'array',
					'items' => $endItem,
				] : $endItem;
		} else {
			$jsonProperty = ArrayHash::from(['$ref' => '#/components/schemas/' . $propertyClass->getShortName()]);
		}

		return $jsonProperty;
	}

	private function getSeeEnumInfo(ReflectionClass $type, ReflectionProperty $property): ?string
	{
		$propertyAnnotations = AnnotationParser::getAll($property);

		if (!isset($propertyAnnotations['see'])) {
			return null;
		}

		$seeAnnotation = $propertyAnnotations['see'][0];
		$filename = $type->getFileName();

		if ($filename === false) {
			throw new Exception('Cannot get filename of ' . $type->getName());
		}

		$useStatements = Helper::getUseStatements($filename);

		if (!isset($useStatements[$seeAnnotation])) {
			return null;
		}

		$seeType = $useStatements[$seeAnnotation];
		$seeClass = new ReflectionClass($seeType); //@phpstan-ignore-line

		if (!is_a($seeClass->getName(), $this->generator->getConfig()->baseEnum, true)) {
			return null;
		}

		return $this->getEnumDescription($seeClass);
	}

	private function extractBuiltInProperty(int $arrayDimensions, ArrayHash $jsonProperty, string $propertyType): void
	{
		if ($arrayDimensions > 0) {
			$jsonProperty->type = 'array';
			$jsonProperty->items = ['type' => Helper::convertType($propertyType)];

			return;
		}

		$jsonProperty->type = Helper::convertType($propertyType);
	}

	/**
	 * @return mixed[]
	 */
	private function getPropertyType(ReflectionClass $type, ReflectionProperty $property, ArrayHash $jsonProperty): array
	{
		$propertyAnnotations = AnnotationParser::getAll($property);

		if (isset($propertyAnnotations['var']) && Strings::trim((string) $propertyAnnotations['var'][0]) === '') {
			throw new Exception('Missing var annotation on ' . $type->getName() . '::$' . $property->getName());
		}

		$propertyType = null;

		if ($property->hasType()) {
			$propertyType = $property->getType()->getName();
		}

		if ($propertyType === null || $propertyType === 'array') {
			if (Strings::trim((string) $propertyAnnotations['var'][0]) === '') {
				throw new Exception('Missing var annotation for array on ' . $type->getName() . '::$' . $property->getName());
			}

			$propertyType = explode(' ', $propertyAnnotations['var'][0])[0];
		}

		$enumDescription = $this->getSeeEnumInfo($type, $property);

		if ($enumDescription !== null) {
			$jsonProperty->description = $enumDescription;
		}

		$arrayDimensions = 0;

		for (; str_ends_with($propertyType, '[]'); $arrayDimensions++) {
			$propertyType = substr($propertyType, 0, strlen($propertyType) - 2);
		}

		$filename = $type->getFileName();

		if ($filename === false) {
			throw new Exception('Cannot determine filename of ' . $type->getName());
		}

		$useStatements = Helper::getUseStatements($filename);

		if (isset($useStatements[$propertyType])) {
			$propertyType = $useStatements[$propertyType];
		}

		if (class_exists($type->getNamespaceName() . '\\' . $propertyType)) {
			$propertyType = $type->getNamespaceName() . '\\' . $propertyType;
		}

		return [$propertyType, $arrayDimensions];
	}

	private function getEnumDescription(ReflectionClass $seeClass): string
	{
		$constants = $seeClass->getReflectionConstants();
		$info = "Possible values: \n";

		foreach ($constants as $const) {
			$annotations = AnnotationParser::getAll($const);
			$desc = !isset($annotations['description']) ? strtolower(str_replace('_', ' ', $const->name)) : implode("\n", $annotations['description']);

			$info .= '* `' . $const->name . '` - ' . $desc . "\n";
		}

		return $info;
	}

}
