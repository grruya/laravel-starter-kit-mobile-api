<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\IssueOneTimePassword;
use App\Enums\OneTimePasswordPurpose;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Request;

final readonly class UserSendEmailVerificationCodeController
{
    public function __invoke(
        Request $request,
        #[CurrentUser] User $user,
        IssueOneTimePassword $issueOneTimePassword,
    ): JsonResponse {
        $request->validate(['device_id' => ['required', 'string', 'min:1', 'max:255']]);

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
