<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Http\Requests\Concerns\DeviceValidationRules;
use App\Http\Requests\Concerns\PasswordValidationRules;
use App\Models\User;
use App\Rules\ValidEmail;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CreateUserRequest extends FormRequest
{
    use DeviceValidationRules;
    use PasswordValidationRules;

    /**
     * @return array<string, array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'max:255',
                'email',
                new ValidEmail,
                Rule::unique(User::class),
            ],
            'password' => [
                ...$this->passwordRules(),
            ],
            'device_id' => $this->deviceIdRules(),
            'device_name' => $this->deviceNameRules(),
        ];
    }
}
