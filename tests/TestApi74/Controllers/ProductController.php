<?php declare (strict_types = 1);

namespace Wedo\OpenApiGenerator\Tests\TestApi74\Controllers;

use DateTime;
use Wedo\OpenApiGenerator\Tests\TestApi74\Entities\ProductListItem;
use Wedo\OpenApiGenerator\Tests\TestApi74\Responses\ProductListResponse;
use Wedo\OpenApiGenerator\Tests\TestApi74\Responses\ProductResponse;

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
	 * @param int $id product list id
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
