<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\IssueOneTimePassword;
use App\Enums\OneTimePasswordPurpose;
use App\Exceptions\OneTimePasswordException;
use App\Http\Requests\SendPasswordResetCodeRequest;
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
        } catch (OneTimePasswordException $exception) {
            if (! $exception->isCooldown()) {
                throw $exception;
            }
        }

        return response()->json([
            'message' => 'A reset code will be sent if the account exists.',
        ]);
    }
}
