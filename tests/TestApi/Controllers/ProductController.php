<?php declare (strict_types = 1);

namespace Wedo\OpenApiGenerator\Tests\TestApi\Controllers;

use DateTime;
use Wedo\OpenApiGenerator\Tests\TestApi\Responses\ProductResponse;
use Wedo\OpenApiGenerator\Tests\TestApi\Entities\ProductListItem;
use Wedo\OpenApiGenerator\Tests\TestApi\Responses\ProductListResponse;

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

	/**
	 * @param bool $extra
	 */
	public function getList(int $id): ProductListResponse
	{
		$response = new ProductListResponse();
		$product = new ProductListItem();
		$product->id = 5;
		$product->name = 'blabla';
		$response->data = [$product];
		return $response;
	}
}
