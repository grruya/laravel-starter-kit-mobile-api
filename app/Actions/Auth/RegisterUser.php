<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Enums\OneTimePasswordPurpose;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use SensitiveParameter;

final readonly class RegisterUser
{
    public function __construct(
        private IssueOneTimePassword $issueOneTimePassword,
        private IssueAuthToken $issueAuthToken,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{user: User, token: non-empty-string}
     */
    public function handle(
        array $attributes,
        #[SensitiveParameter] string $password,
        string $deviceName,
        #[SensitiveParameter] string $deviceId,
    ): array {
        return DB::transaction(function () use ($attributes, $password, $deviceName, $deviceId): array {
            $user = User::query()->create([
                ...$attributes,
                'password' => $password,
            ]);

            $this->issueOneTimePassword->handle($user, OneTimePasswordPurpose::EmailVerification, $deviceId);

            $token = $this->issueAuthToken->handle($user, $deviceName, $deviceId);

            return [
                'user' => $user,
                'token' => $token,
            ];
        });
    }
}
