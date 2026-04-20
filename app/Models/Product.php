<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property-read int $id
 * @property-read string $name
 * @property-read string $description
 * @property-read int $price
 * @property-read int $stock
 * @property-read bool $is_published
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 */
#[Fillable(['name', 'description', 'price', 'stock', 'is_published'])]
#[Hidden(['is_published'])]
final class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'name' => 'string',
            'description' => 'string',
            'price' => 'integer',
            'stock' => 'integer',
            'is_published' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
