<?php declare (strict_types = 1);

namespace Wedo\OpenApiGenerator\Tests\TestApi\Controllers;

use DateTime;
use Wedo\OpenApiGenerator\Tests\TestApi\Responses\ProductResponse;

class ProductController extends BaseController
{

	/**
	 * @param bool $extra
	 */
	public function get(int $id): ProductResponse
	{
		$response = new ProductResponse();
		$response->id = $id;
		$response->expires_at = new DateTime();
		$response->price = 129900;
		$response->published = true;
		$response->title = 'Product ' . $id . ' cost 1299,00';
		return $response;
	}

}
