<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\PersonalAccessToken;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use SensitiveParameter;

final readonly class IssueAuthToken
{
    /**
     * @return non-empty-string
     */
    public function handle(User $user, string $deviceName, #[SensitiveParameter] string $deviceId): string
    {
        return DB::transaction(function () use ($user, $deviceName, $deviceId): string {
            $plainTextToken = $user->generateTokenString();
            $deviceIdHash = hash('sha256', $deviceId);

            /** @var PersonalAccessToken $accessToken */
            $accessToken = $user->tokens()->updateOrCreate(
                ['device_id_hash' => $deviceIdHash],
                [
                    'name' => $deviceName,
                    'token' => hash('sha256', $plainTextToken),
                    'abilities' => ['*'],
                    'last_used_at' => null,
                    'expires_at' => null,
                ],
            );

            return sprintf('%s|%s', $accessToken->id, $plainTextToken);
        });
    }
}
