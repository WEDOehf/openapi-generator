<?php declare (strict_types = 1);

namespace Wedo\OpenApiGenerator;

class Config
{

	public string $serverUrl;

	public string $path;

	/**
	 * Controllers namespace
	 */
	public string $namespace = 'App\Api\Controllers\\';

	public string $controllerSuffix = 'Controller';

	/**
	 * public properties won't be extracted from this classes (only name is needed not FQN)
	 *
	 * @var string[]
	 */
	public array $skipClasses = ['BaseRequest', 'BaseResponse', 'BaseEntity'];

	public string $baseRequest = 'App\Api\Requests\BaseRequest';

	public string $baseEnum = 'App\Enums\BaseEnum';

	public string $dateTimeClass = 'App\Common\JsonDateTime';

	/** @var array<string, string> key type is replaced with value, for example ['App\TranslatableString' => 'string'] */
	public array $typeReplacement = [];

	/**
	 * annotation/attribute that show some property is required on request
	 */
	public string $requiredAnnotation = 'required';

	/**
	 * method/class/property with this annotation/attribute will be skipped in openapi schema
	 */
	public string $internalAnnotation = 'internal';

	/**
	 * annotation/attribute for defining httpMethod
	 */
	public string $httpMethodAnnotation = 'httpMethod';

	/**
	 * used only if you are using attribute, this is name of field that holds information on request (post/get/put/delete...)
	 */
	public string $httpMethodAttributeProperty = 'value';

}
