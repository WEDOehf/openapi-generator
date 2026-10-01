<?php declare (strict_types = 1);

namespace Wedo\OpenApiGenerator\Processors;

use Exception;
use Nette\Utils\ArrayHash;
use Nette\Utils\Strings;
use ReflectionClass;
use ReflectionProperty;
use ReflectionUnionType;
use stdClass;
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

		$this->json->components->schemas[$type->getShortName()]['properties'] = count($jsonProperties) > 0 ? $jsonProperties : new stdClass();

		if (count($required) > 0) {
			$this->json->components->schemas[$type->getShortName()]['required'] = $required;
		}
	}

	protected function getJsonProperty(ReflectionClass $type, ReflectionProperty $property): ArrayHash
	{
		$jsonProperty = new ArrayHash();
		$nativeType = $property->getType();

		if ($nativeType instanceof ReflectionUnionType) {
			return $this->getGenericJsonProperty($type, $property, (string) $nativeType);
		}

		$varType = $this->getVarTypeExpression($property);

		if ($varType !== null && (str_contains($varType, '<') || str_contains($varType, '|'))) {
			return $this->getGenericJsonProperty($type, $property, $varType);
		}

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
			$jsonProperty->items = $propertyType === 'mixed' ? new stdClass() : ['type' => Helper::convertType($propertyType)];

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

			$propertyType = (string) $this->getVarTypeExpression($property);
		}

		$enumDescription = $this->getSeeEnumInfo($type, $property);

		if ($enumDescription !== null) {
			$jsonProperty->description = $enumDescription;
		}

		$arrayDimensions = 0;

		for (; str_ends_with($propertyType, '[]'); $arrayDimensions++) {
			$propertyType = substr($propertyType, 0, strlen($propertyType) - 2);
		}

		return [$this->resolveClassName($propertyType, $type), $arrayDimensions];
	}

	/**
	 * The type part of the `@var` annotation: everything up to the first whitespace outside `<...>`,
	 * so a description after `array<string, mixed>` does not cut the expression at its comma.
	 */
	private function getVarTypeExpression(ReflectionProperty $property): ?string
	{
		$annotations = AnnotationParser::getAll($property);

		if (!isset($annotations['var'])) {
			return null;
		}

		$raw = Strings::trim((string) $annotations['var'][0]);
		$depth = 0;
		$length = strlen($raw);

		for ($i = 0; $i < $length; $i++) {
			$char = $raw[$i];

			if ($char === '<') {
				$depth++;
			} elseif ($char === '>') {
				$depth--;
			} elseif ($depth === 0 && ctype_space($char)) {
				return substr($raw, 0, $i);
			}
		}

		return $raw === '' ? null : $raw;
	}

	private function resolveClassName(string $propertyType, ReflectionClass $context): string
	{
		$filename = $context->getFileName();

		if ($filename === false) {
			throw new Exception('Cannot determine filename of ' . $context->getName());
		}

		$useStatements = Helper::getUseStatements($filename);

		if (isset($useStatements[$propertyType])) {
			$propertyType = $useStatements[$propertyType];
		}

		if (class_exists($context->getNamespaceName() . '\\' . $propertyType)) {
			$propertyType = $context->getNamespaceName() . '\\' . $propertyType;
		}

		return $propertyType;
	}

	/**
	 * A property whose type uses generics or a union: `array<string, mixed>`, `list<Item>`,
	 * `array<int, array<string, int>>`, `Item[]|null`, native `int|string`. Built recursively from the
	 * leaf types, so a map becomes an object with `additionalProperties`, a list an array with `items`,
	 * and `mixed` an unconstrained schema instead of the literal text being emitted as the type.
	 */
	private function getGenericJsonProperty(ReflectionClass $type, ReflectionProperty $property, string $expression): ArrayHash
	{
		$schema = $this->schemaForTypeExpression($expression, $type);
		$jsonProperty = ArrayHash::from($schema instanceof stdClass ? [] : $schema, false);

		$propertyAnnotations = AnnotationParser::getAll($property);

		if (
			isset($propertyAnnotations['description'])
			&& Strings::trim($propertyAnnotations['description'][0]) !== ''
		) {
			$jsonProperty->description = implode("\n", $propertyAnnotations['description']);
		}

		return $jsonProperty;
	}

	/**
	 * @return mixed[]|stdClass an OpenAPI schema fragment; an empty stdClass means "any type"
	 */
	private function schemaForTypeExpression(string $expression, ReflectionClass $context): array|stdClass
	{
		$expression = trim($expression);
		$nullable = str_starts_with($expression, '?');
		$members = [];

		foreach ($this->splitTopLevel(ltrim($expression, '?'), '|') as $member) {
			if (strtolower($member) === 'null') {
				$nullable = true;
			} else {
				$members[] = $member;
			}
		}

		if (count($members) > 1) {
			sort($members);
			$schema = ['oneOf' => array_map(fn (string $member): array|stdClass => $this->schemaForTypeExpression($member, $context), $members)];
		} else {
			$schema = $this->schemaForSingleType($members[0] ?? 'mixed', $context);
		}

		if ($nullable && !$schema instanceof stdClass) {
			$schema['nullable'] = true;
		}

		return $schema;
	}

	/**
	 * @return mixed[]|stdClass
	 */
	private function schemaForSingleType(string $expression, ReflectionClass $context): array|stdClass
	{
		if (str_ends_with($expression, '[]')) {
			return ['type' => 'array', 'items' => $this->schemaForTypeExpression(substr($expression, 0, -2), $context)];
		}

		$generic = Strings::match($expression, '~^([a-zA-Z0-9_\\-]+)<(.*)>$~s');

		if ($generic !== null) {
			return $this->schemaForGeneric(strtolower($generic[1]), $this->splitTopLevel($generic[2], ','), $context);
		}

		$builtIn = $this->schemaForBuiltIn($expression);

		if ($builtIn !== null) {
			return $builtIn;
		}

		$className = $this->resolveClassName($expression, $context);

		if (isset($this->generator->getConfig()->typeReplacement[$className])) {
			$className = $this->generator->getConfig()->typeReplacement[$className];
		}

		if (class_exists($className)) {
			return (array) $this->extractObjectProperty($className, new ArrayHash());
		}

		return ['type' => Helper::convertType($expression)];
	}

	/**
	 * `list<V>`, `array<V>`, `array<int, V>` are arrays; `array<string, V>` (any non-int key) is a map.
	 *
	 * @param string[] $arguments
	 * @return mixed[]
	 */
	private function schemaForGeneric(string $container, array $arguments, ReflectionClass $context): array
	{
		if (count($arguments) === 1) {
			return ['type' => 'array', 'items' => $this->schemaForTypeExpression($arguments[0], $context)];
		}

		$key = strtolower(trim($arguments[0]));
		$value = $this->schemaForTypeExpression($arguments[1], $context);
		$isList = in_array($container, ['list', 'non-empty-list'], true)
			|| in_array($key, ['int', 'integer', 'positive-int', 'non-negative-int'], true);

		return $isList ? ['type' => 'array', 'items' => $value] : ['type' => 'object', 'additionalProperties' => $value];
	}

	/**
	 * @return mixed[]|stdClass|null null when the expression is not a built-in type
	 */
	private function schemaForBuiltIn(string $expression): array|stdClass|null
	{
		return match (strtolower($expression)) {
			'mixed' => new stdClass(),
			'array', 'iterable', 'list' => ['type' => 'array', 'items' => new stdClass()],
			'object' => ['type' => 'object'],
			'bool', 'boolean', 'int', 'integer', 'float', 'double', 'number', 'string' => ['type' => Helper::convertType(strtolower($expression))],
			default => null,
		};
	}

	/**
	 * Splits on a separator that is not nested inside `<...>`.
	 *
	 * @return string[]
	 */
	private function splitTopLevel(string $expression, string $separator): array
	{
		$parts = [];
		$depth = 0;
		$current = '';
		$length = strlen($expression);

		for ($i = 0; $i < $length; $i++) {
			$char = $expression[$i];

			if ($char === '<') {
				$depth++;
			} elseif ($char === '>') {
				$depth--;
			}

			if ($char === $separator && $depth === 0) {
				$parts[] = trim($current);
				$current = '';

				continue;
			}

			$current .= $char;
		}

		$parts[] = trim($current);

		return $parts;
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
