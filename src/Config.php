<?php declare (strict_types = 1);

namespace Wedo\OpenApiGenerator;

class Config
{

	/** @var string */
	public $serverUrl;

	/** @var string */
	public $path;

	/**
	 * Controllers namespace
	 *
	 * @var string
	 */
	public $namespace = 'App\Api\Controllers\\';

	/** @var string */
	public $controllerSuffix = 'Controller';

	/**
	 * public properties won't be extracted from this classes (only name is needed not FQN)
	 *
	 * @var string[]
	 */
	public $skipClasses = ['BaseRequest', 'BaseResponse', 'BaseEntity'];

	/** @var string */
	public $baseRequest = 'App\Api\Requests\BaseRequest';

	/** @var string */
	public $baseEnum = 'App\Enums\BaseEnum';

	/** @var string  */
	public $dateTimeClass = 'App\Common\JsonDateTime';

	/** @var array<string, string> key type is replaced with value, for example ['App\TranslatableString' => 'string'] */
	public array $tpypeReplacement = [];

	/**
	 * annotation that show some property is required on request
	 *
	 * @var string
	 */
	public $requiredAnnotation = 'required';

}
