<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class VerifyEmailCodeRequest extends FormRequest
{
    /**
     * @return array<string, array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'digits:6'],
            'device_id' => ['required', 'string', 'min:1', 'max:255'],
        ];
    }
}
