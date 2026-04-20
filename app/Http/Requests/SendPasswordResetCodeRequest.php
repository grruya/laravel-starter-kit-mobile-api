<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Rules\ValidEmail;
use Illuminate\Foundation\Http\FormRequest;

final class SendPasswordResetCodeRequest extends FormRequest
{
    /**
     * @return array<string, array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'lowercase', 'max:255', 'email', new ValidEmail],
            'device_id' => ['required', 'string', 'min:1', 'max:255'],
        ];
    }
}
