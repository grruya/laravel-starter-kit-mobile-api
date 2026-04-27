<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\Auth\ConsumeOneTimePassword;
use App\Enums\OneTimePasswordPurpose;
use App\Http\Requests\Auth\VerifyEmailCodeRequest;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final readonly class UserVerifyEmailController
{
    public function __invoke(
        VerifyEmailCodeRequest $request,
        ConsumeOneTimePassword $consumeOneTimePassword,
        #[CurrentUser] User $user,
    ): JsonResponse {
        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Email already verified',
            ]);
        }

        $consumeOneTimePassword->handle(
            $user,
            OneTimePasswordPurpose::EmailVerification,
            $request->string('code')->value(),
            $request->string('device_id')->value(),
        );

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return response()->json([
            'message' => 'Email verified successfully',
        ]);
    }
}
