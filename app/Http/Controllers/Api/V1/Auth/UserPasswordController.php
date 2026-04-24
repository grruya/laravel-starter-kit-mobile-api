<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\Auth\ConsumeOneTimePassword;
use App\Actions\Auth\ResetUserPassword;
use App\Enums\OneTimePasswordPurpose;
use App\Http\Requests\Auth\CreateUserPasswordRequest;
use App\Http\Requests\Auth\UpdateUserPasswordRequest;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final readonly class UserPasswordController
{
    public function store(
        CreateUserPasswordRequest $request,
        ConsumeOneTimePassword $consumeOneTimePassword,
        ResetUserPassword $resetUserPassword,
    ): JsonResponse {
        $user = $request->passwordResetUser();

        $consumeOneTimePassword->handle(
            $user,
            OneTimePasswordPurpose::PasswordReset,
            $request->string('code')->value(),
            $request->string('device_id')->value(),
        );

        $resetUserPassword->handle(
            $user,
            $request->string('password')->value(),
        );

        if (! $user->hasVerifiedEmail() && $user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return response()->json([
            'message' => 'Password reset successfully',
        ]);
    }

    public function update(UpdateUserPasswordRequest $request, #[CurrentUser] User $user): JsonResponse
    {
        $user->update(['password' => $request->string('password')->value()]);
        $user->tokens()->delete();

        return response()->json([
            'message' => 'Password updated successfully',
        ]);
    }
}
