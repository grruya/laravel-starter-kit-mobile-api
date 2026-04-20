<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class SaveProductRequest extends FormRequest
{
    /**
     * @return array<string, array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:150'],
            'description' => ['required', 'string', 'min:3', 'max:500'],
            'price' => ['required', 'integer', 'min:1', 'max:1000000'],
            'stock' => ['required', 'integer', 'min:1', 'max:1000000'],
            'is_published' => ['required', 'boolean'],
        ];
    }
}
