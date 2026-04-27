<?php

declare(strict_types=1);

use App\Actions\Auth\IssueAuthToken;
use App\Models\PersonalAccessToken;
use App\Models\User;

it('creates an id-prefixed plaintext token while storing only the token hash and device hash', function (): void {
    $user = User::factory()->create();

    $token = resolve(IssueAuthToken::class)->handle($user, 'iPhone', 'device-1');
    [$tokenId, $plainTextToken] = explode('|', $token, 2);
    $accessToken = PersonalAccessToken::query()->findOrFail($tokenId);

    expect($token)->toContain('|')
        ->and($plainTextToken)->not->toBe('')
        ->and($accessToken->token)->toBe(hash('sha256', $plainTextToken))
        ->and($accessToken->token)->not->toBe($plainTextToken)
        ->and($accessToken->device_id_hash)->toBe(hash('sha256', 'device-1'))
        ->and($accessToken->name)->toBe('iPhone')
        ->and($accessToken->abilities)->toBe(['*'])
        ->and($accessToken->last_used_at)->toBeNull()
        ->and($accessToken->expires_at)->toBeNull();
});

it('replaces same-device tokens and keeps different-device tokens', function (): void {
    $user = User::factory()->create();
    $issueAuthToken = resolve(IssueAuthToken::class);

    $oldToken = $issueAuthToken->handle($user, 'Old iPhone', 'device-1');
    $newToken = $issueAuthToken->handle($user, 'New iPhone', 'device-1');
    $otherToken = $issueAuthToken->handle($user, 'iPad', 'device-2');

    [$oldTokenId] = explode('|', $oldToken, 2);
    [$newTokenId, $newPlainTextToken] = explode('|', $newToken, 2);
    [$otherTokenId] = explode('|', $otherToken, 2);

    expect($newTokenId)->toBe($oldTokenId)
        ->and($otherTokenId)->not->toBe($newTokenId)
        ->and(PersonalAccessToken::query()->where('tokenable_id', $user->id)->count())->toBe(2);

    $sameDeviceToken = PersonalAccessToken::query()->findOrFail($newTokenId);

    expect($sameDeviceToken->name)->toBe('New iPhone')
        ->and($sameDeviceToken->token)->toBe(hash('sha256', $newPlainTextToken));
});

it('returns a token id matching the database row and authenticates through sanctum lookup', function (): void {
    $user = User::factory()->create();

    $token = resolve(IssueAuthToken::class)->handle($user, 'iPhone', 'device-1');
    [$tokenId] = explode('|', $token, 2);

    $accessToken = PersonalAccessToken::findToken($token);

    expect($accessToken)->toBeInstanceOf(PersonalAccessToken::class)
        ->and((string) $accessToken->id)->toBe($tokenId)
        ->and($accessToken->tokenable->is($user))->toBeTrue();
});
