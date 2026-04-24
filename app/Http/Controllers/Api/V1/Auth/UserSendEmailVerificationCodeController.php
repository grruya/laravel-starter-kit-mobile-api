<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\Auth\IssueOneTimePassword;
use App\Enums\OneTimePasswordPurpose;
use App\Http\Requests\Auth\SendEmailVerificationCodeRequest;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final readonly class UserSendEmailVerificationCodeController
{
    public function __invoke(
        SendEmailVerificationCodeRequest $request,
        #[CurrentUser] User $user,
        IssueOneTimePassword $issueOneTimePassword,
    ): JsonResponse {
        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Email already verified',
            ]);
        }

        $issueOneTimePassword->handle(
            $user,
            OneTimePasswordPurpose::EmailVerification,
            $request->string('device_id')->value(),
        );

        return response()->json([
            'message' => 'Verification code sent',
        ]);
    }
}
