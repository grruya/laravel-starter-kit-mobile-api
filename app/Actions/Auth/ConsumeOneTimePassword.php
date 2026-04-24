<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Enums\OneTimePasswordPurpose;
use App\Exceptions\OneTimePasswordException;
use App\Models\OneTimePassword;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Timebox;
use SensitiveParameter;

final readonly class ConsumeOneTimePassword
{
    public function __construct(private Timebox $timebox) {}

    public function handle(
        User $user,
        OneTimePasswordPurpose $purpose,
        #[SensitiveParameter] string $code,
        #[SensitiveParameter] string $deviceId,
    ): void {
        $exception = $this->timebox->call(
            fn (Timebox $timebox): ?OneTimePasswordException => DB::transaction(function () use ($user, $purpose, $code, $deviceId, $timebox): ?OneTimePasswordException {
                User::query()
                    ->whereKey($user->id)
                    ->lockForUpdate()
                    ->first();

                $oneTimePassword = $user->oneTimePasswords()
                    ->where('purpose', $purpose->value)
                    ->lockForUpdate()
                    ->first();

                if (! $oneTimePassword instanceof OneTimePassword) {
                    return OneTimePasswordException::invalid();
                }

                if ($oneTimePassword->expires_at->isPast()) {
                    $oneTimePassword->delete();

                    return OneTimePasswordException::expired();
                }

                if (! Hash::check($code, $oneTimePassword->code_hash)) {
                    $this->recordInvalidAttempt($oneTimePassword);

                    return OneTimePasswordException::invalid();
                }

                if (! $this->matchesDevice($oneTimePassword, $deviceId)) {
                    return OneTimePasswordException::differentDevice();
                }

                $oneTimePassword->delete();
                $timebox->returnEarly();

                return null;
            }),
            microseconds: Config::integer('otp.verification_timebox_in_milliseconds') * 1000,
        );

        throw_if(
            $exception !== null,
            $exception ?? OneTimePasswordException::invalid(),
        );
    }

    private function matchesDevice(OneTimePassword $oneTimePassword, string $deviceId): bool
    {
        if ($oneTimePassword->device_id_hash === null) {
            return true;
        }

        return hash_equals($oneTimePassword->device_id_hash, hash('sha256', $deviceId));
    }

    private function recordInvalidAttempt(OneTimePassword $oneTimePassword): void
    {
        $attempts = $oneTimePassword->attempts + 1;

        if ($attempts >= Config::integer('otp.max_attempts')) {
            $oneTimePassword->delete();

            return;
        }

        $oneTimePassword->update(['attempts' => $attempts]);
    }
}
