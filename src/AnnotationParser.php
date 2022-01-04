<?php declare(strict_types = 1);
// phpcs:ignoreFile
/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 *
 * maintained further by Dalibor Korpar, removed cache and simplified
 */

namespace Wedo\OpenApiGenerator;

use Nette;
use Nette\Utils\Strings;
use ReflectionClass;
use ReflectionException;
use ReflectionFunction;
use ReflectionMethod;
use Reflector;

/**
 * Annotations support for PHP.
 */
class AnnotationParser
{

	use Nette\StaticClass;

	/** @internal single & double quoted PHP string */
	private const RE_STRING = '\'(?:\\\\.|[^\'\\\\])*\'|"(?:\\\\.|[^"\\\\])*"';

	/** @internal identifier */
	private const RE_IDENTIFIER = '[_a-zA-Z\x7F-\xFF][_a-zA-Z0-9\x7F-\xFF-\\\]*';

	/** @var string[] */
	public static array $inherited = ['description', 'param', 'return'];

	/**
	 * Returns annotations.
	 *
	 * @param \ReflectionClass|\ReflectionMethod|\ReflectionProperty|\ReflectionFunction|\ReflectionClassConstant $r
	 * @return array<string, array<int, string|null>|null>
	 */
	public static function getAll(Reflector $r): array
	{
		if ($r instanceof ReflectionClass) {
			$type = $r->getName();
			$member = 'class';

		} elseif ($r instanceof ReflectionMethod) {
			$type = $r->getDeclaringClass()->getName();
			$member = $r->getName();

		} elseif ($r instanceof ReflectionFunction) {
			$type = null;
			$member = $r->getName();

		} else {
			$type = $r->getDeclaringClass()->getName();
			$member = '$' . $r->getName();
		}

		$docComment = $r->getDocComment();

		if ($docComment === false) {
			return [];
		}

		$annotations = self::parseComment($docComment);

		if ($r instanceof ReflectionMethod && !$r->isPrivate()
			&& (!$r->isConstructor() || isset($annotations['inheritdoc'][0]))
		) {
			try {
				$inherited = self::getAll(new ReflectionMethod(get_parent_class($type), $member));
			} catch (ReflectionException $e) {
				try {
					$inherited = self::getAll($r->getPrototype());
				} catch (ReflectionException $e) {
					$inherited = [];
				}
			}

			$annotations += array_intersect_key($inherited, array_flip(self::$inherited));
		}

		return $annotations;
	}

	/**
	 * Expands class name into FQN.
	 *
	 * @throws Nette\InvalidArgumentException
	 */
	public static function expandClassName(string $name, ReflectionClass $reflector): string
	{
		if (Strings::trim($name) === '') {
			throw new Nette\InvalidArgumentException('Class name must not be empty.');
		} elseif ($name === 'self') {
			return $reflector->getName();
		} elseif ($name[0] === '\\') { // already fully qualified
			return ltrim($name, '\\');
		}

		$php = file_get_contents($reflector->getFileName());

		$parsed = $php === false ? '' : self::parsePhp();
		$uses = array_change_key_case((array) $tmp = &$parsed[$reflector->getName()]['use']);
		$parts = explode('\\', $name, 2);
		$parts[0] = strtolower($parts[0]);

		if (isset($uses[$parts[0]])) {
			$parts[0] = $uses[$parts[0]];

			return implode('\\', $parts);
		} elseif ($reflector->inNamespace()) {
			return $reflector->getNamespaceName() . '\\' . $name;
		} else {
			return $name;
		}
	}

	/**
	 * Parses PHP file.
	 *
	 * @return array<string, mixed> [class => [prop => comment (or 'use' => [alias => class])]
	 * @internal
	 */
	public static function parsePhp(string $code): ?array
	{
		if (Strings::match($code, '#//nette' . 'loader=(\S*)#')) {
			return null;
		}

		$tokens = @token_get_all($code);
		$namespace = $class = $classLevel = $level = $docComment = null;
		$res = $uses = [];

		while ($token = current($tokens)) {
			next($tokens);

			switch (is_array($token) ? $token[0] : $token) {
				case T_DOC_COMMENT:
					$docComment = $token[1];
					break;

				case T_NAMESPACE:
					$namespace = ltrim(self::fetch($tokens, [T_STRING, T_NS_SEPARATOR]) . '\\', '\\');
					$uses = [];
					break;

				case T_CLASS:
				case T_INTERFACE:
				case T_TRAIT:
					if ($name = self::fetch($tokens, T_STRING)) {
						$class = $namespace . $name;
						$classLevel = $level + 1;
						$res[$class] = [];

						if ($docComment) {
							$res[$class]['class'] = $docComment;
						}

						if ($uses) {
							$res[$class]['use'] = $uses;
						}
					}

					break;

				case T_FUNCTION:
					self::fetch($tokens, '&');

					if ($level === $classLevel && $docComment && ($name = self::fetch($tokens, T_STRING))) {
						$res[$class][$name] = $docComment;
					}

					break;

				case T_VAR:
				case T_PUBLIC:
				case T_PROTECTED:
					self::fetch($tokens, T_STATIC);

					if ($level === $classLevel && $docComment && ($name = self::fetch($tokens, T_VARIABLE))) {
						$res[$class][$name] = $docComment;
					}

					break;

				case T_USE:
					while (!$class && ($name = self::fetch($tokens, [T_STRING, T_NS_SEPARATOR]))) {
						$name = ltrim($name, '\\');

						if (self::fetch($tokens, '{')) {
							while ($suffix = self::fetch($tokens, [T_STRING, T_NS_SEPARATOR])) {
								if (self::fetch($tokens, T_AS)) {
									$uses[self::fetch($tokens, T_STRING)] = $name . $suffix;
								} else {
									$tmp = explode('\\', $suffix);
									$uses[end($tmp)] = $name . $suffix;
								}

								if (!self::fetch($tokens, ',')) {
									break;
								}
							}
						} elseif (self::fetch($tokens, T_AS)) {
							$uses[self::fetch($tokens, T_STRING)] = $name;

						} else {
							$tmp = explode('\\', $name);
							$uses[end($tmp)] = $name;
						}

						if (!self::fetch($tokens, ',')) {
							break;
						}
					}

					break;

				case T_CURLY_OPEN:
				case T_DOLLAR_OPEN_CURLY_BRACES:
				case '{':
					$level++;
					break;

				case '}':
					if ($level === $classLevel) {
						$class = $classLevel = null;
					}

					$level--;
				// break omitted
				case ';':
					$docComment = null;
			}
		}

		return $res;
	}

	/**
	 * Parses phpDoc comment.
	 *
	 * @return array<int|string, array<int, mixed>>
	 */
	private static function parseComment(string $comment): array
	{
		static $tokens = ['true' => true, 'false' => false, 'null' => null, '' => true];

		$res = [];
		$comment = preg_replace('#^\s*\*\s?#ms', '', trim($comment, '/*'));
		$parts = preg_split('#^\s*(?=@' . self::RE_IDENTIFIER . ')#m', $comment, 2);

		$description = trim($parts[0]);

		if ($description !== '') {
			$res['description'] = [$description];
		}

		$matches = Strings::matchAll(
			$parts[1] ?? '',
			'~
				(?<=\s|^)@(' . self::RE_IDENTIFIER . ')[ \t]*      ##  annotation
				(
					\((?>' . self::RE_STRING . '|[^\'")@]+)+\)|  ##  (value)
					[^(@\r\n][^@\r\n]*|)                     ##  value
			~xi'
		);

		foreach ($matches as $match) {
			[, $name, $value] = $match;

			if (substr($value, 0, 1) === '(') {
				$items = [];
				$key = '';
				$val = true;
				$value[0] = ',';

				while ($m = Strings::match($value, '#\s*,\s*(?>(' . self::RE_IDENTIFIER . ')\s*=\s*)?(' . self::RE_STRING . '|[^\'"),\s][^\'"),]*)#A')) {
					$value = substr($value, strlen($m[0]));
					[, $key, $val] = $m;
					$val = rtrim($val);

					if ($val[0] === "'" || $val[0] === '"') {
						$val = substr($val, 1, -1);

					} elseif (is_numeric($val)) {
						$val = 1 * $val;

					} else {
						$lval = strtolower($val);
						$val = array_key_exists($lval, $tokens) ? $tokens[$lval] : $val;
					}

					if ($key === '') {
						$items[] = $val;

					} else {
						$items[$key] = $val;
					}
				}

				$value = count($items) < 2 && $key === '' ? $val : $items;

			} else {
				$value = trim($value);

				if (is_numeric($value)) {
					$value = 1 * $value;

				} else {
					$lval = strtolower($value);
					$value = array_key_exists($lval, $tokens) ? $tokens[$lval] : $value;
				}
			}

			$res[$name][] = is_array($value) ? Nette\Utils\ArrayHash::from($value) : $value;
		}

		return $res;
	}

	// @phpstan-ignore-next-line
	private static function fetch(&$tokens, $take)
	{
		$res = null;

		while ($token = current($tokens)) {
			[$token, $s] = is_array($token) ? $token : [$token, $token];

			if (in_array($token, (array) $take, true)) {
				$res .= $s;
			} elseif (!in_array($token, [T_DOC_COMMENT, T_WHITESPACE, T_COMMENT], true)) {
				break;
			}

			next($tokens);
		}

		return $res;
	}

}
