<?php declare(strict_types = 1);

namespace Wedo\OpenApiGenerator;

use Exception;
use Nette\Utils\Strings;

class Helper
{

	public static function convertType(string $objType): string
	{
		switch ($objType) {
			case 'bool':
				return 'boolean';
			case 'int':
				return 'integer';
			case 'double':
			case 'float':
				return 'number';
		}

		return $objType;
	}

	/**
	 * @return string[]
	 */
	public static function getUseStatements(string $filename): array
	{
		$lines = file($filename);

		if ($lines === false) {
			return [];
		}

		$useStatements = [];

		foreach ($lines as $line) {
			$line = trim($line);

			if (str_starts_with(strtolower($line), 'class')) {
				return $useStatements;
			}

			if (!str_starts_with(strtolower($line), 'use')) {
				continue;
			}

			$useStatementParts = explode(' ', $line);
			$useStatementClass = rtrim($useStatementParts[1], ';');
			$namespaceParts = explode('\\', $useStatementClass);
			$useStatements[end($namespaceParts)] = $useStatementClass;
		}

		return $useStatements;
	}

	public static function camelCase2path(string $className): string
	{
		$path = preg_replace('#([^.])(?=[A-Z])#', '$1-', $className);

		if ($path === null) {
			throw new Exception('Wrong name for ' . $className);
		}

		$path = Strings::lower($path);

		return $path;
	}

}
