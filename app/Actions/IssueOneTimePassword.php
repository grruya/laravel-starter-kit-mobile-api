<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\OneTimePasswordPurpose;
use App\Exceptions\OneTimePasswordException;
use App\Models\OneTimePassword;
use App\Models\User;
use App\Notifications\OneTimePasswordNotification;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use SensitiveParameter;

final readonly class IssueOneTimePassword
{
    public function handle(
        User $user,
        OneTimePasswordPurpose $purpose,
        #[SensitiveParameter] ?string $deviceId = null,
    ): void {
        $code = '';

        $exception = DB::transaction(function () use ($user, $purpose, $deviceId, &$code): ?OneTimePasswordException {
            User::query()
                ->whereKey($user->id)
                ->lockForUpdate()
                ->first();

            $existingOneTimePassword = $user->oneTimePasswords()
                ->where('purpose', $purpose->value)
                ->lockForUpdate()
                ->first();

            if ($existingOneTimePassword instanceof OneTimePassword) {
                $secondsUntilRetry = $this->secondsUntilRetry($existingOneTimePassword);

                if ($secondsUntilRetry > 0) {
                    return OneTimePasswordException::cooldown($secondsUntilRetry);
                }
            }

            $code = $this->generateCode();

            OneTimePassword::query()->updateOrCreate(
                [
                    'user_id' => $user->id,
                    'purpose' => $purpose,
                ],
                [
                    'code_hash' => Hash::make($code),
                    'device_id_hash' => $deviceId === null ? null : hash('sha256', $deviceId),
                    'attempts' => 0,
                    'sent_at' => now(),
                    'expires_at' => now()->addMinutes(Config::integer('otp.expires_in_minutes')),
                ],
            );

            return null;
        });

        if ($exception instanceof OneTimePasswordException) {
            throw $exception;
        }

        $user->notify(new OneTimePasswordNotification($purpose, $code));
    }

    private function generateCode(): string
    {
        $codeLength = Config::integer('otp.length');
        $maxCodeValue = (10 ** $codeLength) - 1;

        return mb_str_pad((string) random_int(0, $maxCodeValue), $codeLength, '0', STR_PAD_LEFT);
    }

    private function secondsUntilRetry(OneTimePassword $oneTimePassword): int
    {
        $sentAt = $oneTimePassword->sent_at ?? $oneTimePassword->created_at;
        $availableAt = $sentAt->copy()->addSeconds(Config::integer('otp.resend_cooldown_in_seconds'));

        if ($availableAt->isPast()) {
            return 0;
        }

        return (int) ceil(now()->diffInSeconds($availableAt));
    }
}
