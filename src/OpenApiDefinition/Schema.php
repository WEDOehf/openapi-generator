<?php declare(strict_types = 1);

namespace Wedo\OpenApiGenerator\OpenApiDefinition;

use stdClass;

class Schema
{

	public string $openapi = '3.0.0';

	public Info $info;

	/** @var string[][] */
	public array $servers;

	/** @var mixed[] */
	public array $paths = [];

	/** @var mixed[] */
	public array $security = [];

	public Components $components;

	public function __construct(string $url)
	{
		$this->info = new Info();
		$sec = new stdClass();
		$sec->APIKeyHeader = [];
		$this->security[] = $sec;
		$this->servers = [['url' => $url]];
		$sessionId = new stdClass();
		$sessionId->SessionId = [];
		$this->security[] = $sessionId;

		$this->components = new Components();
	}

}
