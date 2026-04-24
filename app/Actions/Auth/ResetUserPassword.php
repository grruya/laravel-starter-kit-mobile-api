<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Str;
use SensitiveParameter;

final readonly class ResetUserPassword
{
    public function handle(User $user, #[SensitiveParameter] string $password): void
    {
        $user->update([
            'password' => $password,
            'remember_token' => Str::random(60),
        ]);

        $user->tokens()->delete();

        event(new PasswordReset($user));
    }
}
