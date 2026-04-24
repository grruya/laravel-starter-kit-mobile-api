<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\Auth\IssueOneTimePassword;
use App\Enums\OneTimePasswordPurpose;
use App\Exceptions\OneTimePasswordException;
use App\Http\Requests\Auth\SendPasswordResetCodeRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;

final readonly class UserSendPasswordResetCodeController
{
    public function __invoke(SendPasswordResetCodeRequest $request, IssueOneTimePassword $issueOneTimePassword): JsonResponse
    {
        $user = User::query()
            ->where('email', $request->string('email')->value())
            ->first();

        if (! $user instanceof User) {
            return response()->json([
                'message' => 'A reset code will be sent if the account exists.',
            ]);
        }

        try {
            $issueOneTimePassword->handle(
                $user,
                OneTimePasswordPurpose::PasswordReset,
                $request->string('device_id')->value(),
            );
        } catch (OneTimePasswordException $oneTimePasswordException) {
            throw_unless($oneTimePasswordException->isCooldown(), $oneTimePasswordException);
        }

        return response()->json([
            'message' => 'A reset code will be sent if the account exists.',
        ]);
    }
}
