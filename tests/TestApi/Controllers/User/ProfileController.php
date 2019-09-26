<?php declare (strict_types = 1);

namespace Wedo\OpenApiGenerator\Tests\TestApi\Controllers\User;

use Wedo\OpenApiGenerator\Tests\TestApi\Controllers\BaseController;
use Wedo\OpenApiGenerator\Tests\TestApi\Requests\EditProfileRequest;
use Wedo\OpenApiGenerator\Tests\TestApi\Responses\Response;

class ProfileController extends BaseController
{

	public function update(EditProfileRequest $request): Response
	{
		return new Response();
	}

	/**
	 * @internal
	 */
	public function internalSomething(): Response
	{
		return new Response();
	}

}
