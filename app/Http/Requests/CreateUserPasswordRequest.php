<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\User;
use App\Rules\ValidEmail;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;
use LogicException;

final class CreateUserPasswordRequest extends FormRequest
{
    private ?User $passwordResetUser = null;

    /**
     * @return array<string, array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'digits:6'],
            'device_id' => ['required', 'string', 'min:1', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', new ValidEmail],
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }

    /**
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $user = User::query()
                    ->where('email', $this->string('email')->value())
                    ->first();

                if (! $user instanceof User) {
                    $validator->errors()->add('code', 'The provided code is invalid.');

                    return;
                }

                $this->passwordResetUser = $user;
            },
        ];
    }

    public function passwordResetUser(): User
    {
        throw_unless($this->passwordResetUser instanceof User, LogicException::class, 'Password reset user is not available.');

        return $this->passwordResetUser;
    }
}
