<?php declare (strict_types = 1);

namespace Wedo\OpenApiGenerator\Tests\TestApi74\Controllers\User;

use Wedo\OpenApiGenerator\Tests\TestApi74\Controllers\BaseController;
use Wedo\OpenApiGenerator\Tests\TestApi74\Requests\EditProfileRequest;
use Wedo\OpenApiGenerator\Tests\TestApi74\Responses\Response;

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
