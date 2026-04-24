<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Http\Requests\Concerns\PasswordValidationRules;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateUserPasswordRequest extends FormRequest
{
    use PasswordValidationRules;

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'current_password' => $this->currentPasswordRules(),
            'password' => $this->passwordRules(),
        ];
    }
}
