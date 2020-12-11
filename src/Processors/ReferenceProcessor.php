<?php declare (strict_types = 1);

namespace Wedo\OpenApiGenerator\Processors;

use Exception;
use Nette\Reflection\AnnotationsParser;
use Nette\Reflection\ClassType;
use Nette\Reflection\Property;
use Nette\Utils\ArrayHash;
use Nette\Utils\Strings;
use Wedo\OpenApiGenerator\Generator;
use Wedo\OpenApiGenerator\Helper;
use Wedo\OpenApiGenerator\OpenApiDefinition\Schema;

class ReferenceProcessor
{

	/** @var Generator */
	private $generator;

	/** @var Schema */
	private $json;

	public function __construct(Generator $generator)
	{
		$this->generator = $generator;
		$this->json = $generator->getJson();
	}

	public function generateRef(ClassType $type): void
	{
		$required = [];
		$parent = $type->getParentClass();
		//inheritance
		if (($parent !== null) && (!in_array($parent->getShortName(), $this->generator->getConfig()->skipClasses, true))) {
			if (!isset($this->json->components->schemas[$parent->getShortName()])) {
				$this->generateRef($parent);
			}

			$this->generator->getJson()
				->components->schemas[$type->shortName]['allOf'][] = ['$ref' => '#/components/schemas/' . $parent->shortName];
		}

		$properties = $type->getProperties();
		$jsonProperties = [];
		foreach ($properties as $property) {
			if (!$property->isPublic() || (isset($parent) && $parent->hasProperty($property->getName()))) {
				continue;
			}

			if ($property->hasAnnotation('internal')) {
				continue;
			}

			if (isset($property->annotations[$this->generator->getConfig()->requiredAnnotation])) {
				$required[] = $property->name;
			}

			$jsonProperties[$property->name] = $this->getJsonProperty($type, $property);
		}

		$this->json->components->schemas[$type->shortName]['properties'] = $jsonProperties;
		if (count($required) > 0) {
			$this->json->components->schemas[$type->shortName]['required'] = $required;
		}
	}

	protected function getJsonProperty(ClassType $type, Property $property): ArrayHash
	{
		$jsonProperty = new ArrayHash();
		[$propertyType, $arrayDimensions] = $this->getPropertyType($type, $property, $jsonProperty);

		if (isset($this->generator->getConfig()->tpypeReplacement[$propertyType])) {
			$propertyType = $this->generator->getConfig()->tpypeReplacement[$propertyType];
		}

		if (class_exists($propertyType)) {
			$jsonProperty = $this->extractObjectProperty($propertyType, $jsonProperty, $arrayDimensions);
		} else {
			if (isset($this->generator->getConfig()->tpypeReplacement[$propertyType])) {
				$property = $this->generator->getConfig()->tpypeReplacement[$propertyType];
			}
			$this->extractBuiltInProperty($arrayDimensions, $jsonProperty, $propertyType);
		}

		if (
			isset($property->annotations['description'])
			&& Strings::trim((string) $property->annotations['description']) !== ''
		) {
			$jsonProperty->description = implode("\n", $property->annotations['description']);
		}

		return $jsonProperty;
	}

	protected function getEnumProperty(ArrayHash $jsonProperty, ClassType $propertyClass): ArrayHash
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

	private function getSeeEnumInfo(ClassType $type, Property $property): ?string
	{
		if (!$property->hasAnnotation('see')) {
			return null;
		}

		$seeAnnotation = $property->getAnnotation('see');
		$filename = $type->getFileName();
		if ($filename === false) {
			throw new Exception('Cannot get filename of ' . $type->getName());
		}

		$useStatements = Helper::getUseStatements($filename);
		if (!isset($useStatements[$seeAnnotation])) {
			return null;
		}

		$seeType = $useStatements[$seeAnnotation];
		$seeClass = ClassType::from($seeType);
		if (!$seeClass->is($this->generator->getConfig()->baseEnum)) {
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
	private function getPropertyType(ClassType $type, Property $property, ArrayHash $jsonProperty): array
	{
		if (isset($property->annotations['var']) && Strings::trim((string) $property->annotations['var'][0]) === '') {
			throw new Exception('Missing var annotation on ' . $type->getName() . '::$' . $property->getName());
		}

		$propertyType = null;

		if (PHP_VERSION_ID >= 70400 && $property->hasType()) {
			$propertyType = $property->getType()->getName();
		}

		if ($propertyType === null || $propertyType === 'array') {
			if (Strings::trim((string) $property->annotations['var'][0]) === '') {
				throw new Exception('Missing var annotation for array on ' . $type->getName() . '::$' . $property->getName());
			}

			$propertyType = explode(' ', $property->annotations['var'][0])[0];
		}

		$enumDescription = $this->getSeeEnumInfo($type, $property);

		if ($enumDescription !== null) {
			$jsonProperty->description = $enumDescription;
		}

		$arrayDimensions = 0;
		for (; Strings::endsWith($propertyType, '[]'); $arrayDimensions++) {
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

	protected function extractObjectProperty(string $propertyType, ArrayHash $jsonProperty, int $arrayDimensions = 0): ArrayHash
	{
		if ($propertyType === $this->generator->getConfig()->dateTimeClass) {
			$jsonProperty->type = 'string';
			$jsonProperty->format = 'date-time';
			return $jsonProperty;
		}

		$propertyClass = ClassType::from($propertyType);
		if ($propertyClass->is($this->generator->getConfig()->baseEnum)) {
			return $this->getEnumProperty($jsonProperty, $propertyClass);
		}

		$this->generateRef($propertyClass);

		if ($arrayDimensions > 0) {
			$jsonProperty->type = 'array';
			$endItem = ['$ref' => '#/components/schemas/' . $propertyClass->shortName];
			$jsonProperty->items = $arrayDimensions === 2 ? [
					'type' => 'array',
					'items' => $endItem,
				] : $endItem;
		} else {
			$jsonProperty = ArrayHash::from(['$ref' => '#/components/schemas/' . $propertyClass->shortName]);
		}

		return $jsonProperty;
	}

	private function getEnumDescription(ClassType $seeClass): string
	{
		$constants = $seeClass->getReflectionConstants();
		$info = "Possible values: \n";
		foreach ($constants as $const) {
			$annotations = AnnotationsParser::getAll($const);
			$desc = !isset($annotations['description']) ? strtolower(str_replace('_', ' ', $const->name)) : implode("\n", $annotations['description']);

			$info .= '* `' . $const->name . '` - ' . $desc . "\n";
		}

		return $info;
	}

}
