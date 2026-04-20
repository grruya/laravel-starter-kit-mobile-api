<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\ConsumeOneTimePassword;
use App\Enums\OneTimePasswordPurpose;
use App\Http\Requests\VerifyEmailCodeRequest;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final readonly class UserVerifyEmailController
{
    public function __invoke(
        VerifyEmailCodeRequest $request,
        #[CurrentUser] User $user,
        ConsumeOneTimePassword $consumeOneTimePassword,
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
