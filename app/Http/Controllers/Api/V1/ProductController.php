<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\SaveProductRequest;
use App\Models\Product;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;

final readonly class ProductController
{
    public function index(): ResourceCollection
    {
        $products = Product::query()->where('is_published', true)->paginate(10);

        return $products->toResourceCollection();
    }

    public function store(SaveProductRequest $request): JsonResource
    {
        $product = Product::query()->create($request->validated());

        return $product->toResource();
    }

    public function show(Product $product): JsonResource
    {
        return $product->toResource();
    }

    public function update(SaveProductRequest $request, Product $product): JsonResource
    {
        $product->update($request->validated());

        return $product->toResource();
    }

    public function destroy(Product $product): JsonResource
    {
        $product->delete();

        return $product->toResource();
    }
}
