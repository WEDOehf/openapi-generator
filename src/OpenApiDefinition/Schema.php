<?php declare(strict_types = 1);

namespace Wedo\OpenApiGenerator\OpenApiDefinition;

use stdClass;

class Schema
{

	/** @var string */
	public $openapi = '3.0.0';

	/** @var Info */
	public $info;

	/** @var string[][] */
	public $servers;

	/** @var mixed[] */
	public $paths = [];

	/** @var mixed[] */
	public $security = [];

	/** @var Components */
	public $components;

	public function __construct(string $url)
	{
		$this->info = new Info();
		$sec = new stdClass();
		$sec->APIKeyHeader = [];
		$this->security[] = $sec;
		$this->servers = [ ['url' => $url]];
		$sessionId = new stdClass();
		$sessionId->SessionId = [];
		$this->security[] = $sessionId;

		$this->components = new Components();
	}

}
