<?php

declare(strict_types=1);

use App\Models\PersonalAccessToken;
use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;

it('may log in and return a working bearer token', function (): void {
    $user = User::factory()->create(['email' => 'taylor@example.com']);

    $token = $this->postJson('/api/v1/login', validLoginPayload(email: 'taylor@example.com'))
        ->assertOk()
        ->assertJsonPath('data.user.email', 'taylor@example.com')
        ->assertJsonMissingPath('data.user.password')
        ->assertJsonMissingPath('data.user.remember_token')
        ->json('data.token');

    $this->withToken($token)
        ->postJson('/api/v1/logout')
        ->assertOk();

    expect($user->tokens()->count())->toBe(0);
});

it('stores token metadata without storing the plaintext token', function (): void {
    User::factory()->create(['email' => 'taylor@example.com']);

    $plainTextToken = $this->postJson('/api/v1/login', validLoginPayload(deviceId: 'device-1', deviceName: 'Taylor iPhone'))
        ->assertOk()
        ->json('data.token');

    [$tokenId, $tokenValue] = explode('|', (string) $plainTextToken, 2);
    $accessToken = PersonalAccessToken::query()->findOrFail($tokenId);

    expect($accessToken->token)->toBe(hash('sha256', $tokenValue))
        ->and($accessToken->token)->not->toBe($tokenValue)
        ->and($accessToken->device_id_hash)->toBe(hash('sha256', 'device-1'))
        ->and($accessToken->name)->toBe('Taylor iPhone')
        ->and($accessToken->abilities)->toBe(['*'])
        ->and($accessToken->last_used_at)->toBeNull()
        ->and($accessToken->expires_at)->toBeNull();
});

it('validates login payloads', function (array $overrides, string $field): void {
    User::factory()->create(['email' => 'taylor@example.com']);

    $this->postJson('/api/v1/login', [...validLoginPayload(email: 'taylor@example.com'), ...$overrides])
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'missing email' => [['email' => null], 'email'],
    'missing password' => [['password' => null], 'password'],
    'missing device id' => [['device_id' => null], 'device_id'],
    'missing device name' => [['device_name' => null], 'device_name'],
    'invalid email' => [['email' => 'not-an-email'], 'email'],
    'overlong device id' => [['device_id' => str_repeat('a', 256)], 'device_id'],
    'overlong device name' => [['device_name' => str_repeat('a', 256)], 'device_name'],
]);

it('fails wrong passwords without creating a token', function (): void {
    User::factory()->create(['email' => 'taylor@example.com']);

    $this->postJson('/api/v1/login', validLoginPayload(email: 'taylor@example.com', password: 'wrong-password'))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');

    expect(PersonalAccessToken::query()->count())->toBe(0);
});

it('fails unknown emails like wrong passwords without creating a token', function (): void {
    $this->postJson('/api/v1/login', validLoginPayload(email: 'missing@example.com'))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');

    expect(PersonalAccessToken::query()->count())->toBe(0);
});

it('replaces same-device tokens and keeps different-device tokens working', function (): void {
    User::factory()->create(['email' => 'taylor@example.com']);

    $firstToken = $this->postJson('/api/v1/login', validLoginPayload(deviceId: 'phone'))
        ->assertOk()
        ->json('data.token');
    $replacementToken = $this->postJson('/api/v1/login', validLoginPayload(deviceId: 'phone'))
        ->assertOk()
        ->json('data.token');
    $tabletToken = $this->postJson('/api/v1/login', validLoginPayload(deviceId: 'tablet', deviceName: 'iPad'))
        ->assertOk()
        ->json('data.token');

    expect(PersonalAccessToken::query()->count())->toBe(2);

    assertApiTokenIsRejected($firstToken);
    assertApiTokenAuthenticates($replacementToken);
    assertApiTokenAuthenticates($tabletToken);
});

it('throttles failed login attempts and dispatches lockout', function (): void {
    Event::fake([Lockout::class]);
    User::factory()->create(['email' => 'taylor@example.com']);

    foreach (range(1, 5) as $attempt) {
        $this->postJson('/api/v1/login', validLoginPayload(email: 'taylor@example.com', password: 'wrong-'.$attempt))
            ->assertUnprocessable();
    }

    $this->postJson('/api/v1/login', validLoginPayload(email: 'taylor@example.com', password: 'wrong-again'))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');

    Event::assertDispatched(Lockout::class);
});

it('clears failed login limiter state after a successful login', function (): void {
    User::factory()->create(['email' => 'taylor@example.com']);

    foreach (range(1, 4) as $attempt) {
        $this->postJson('/api/v1/login', validLoginPayload(email: 'taylor@example.com', password: 'wrong-'.$attempt))
            ->assertUnprocessable();
    }

    $this->postJson('/api/v1/login', validLoginPayload(email: 'taylor@example.com'))
        ->assertOk();

    expect(RateLimiter::tooManyAttempts('taylor@example.com|127.0.0.1', 5))->toBeFalse();
});

it('rejects uppercase login emails', function (): void {
    User::factory()->create(['email' => 'test@example.com']);

    $this->postJson('/api/v1/login', validLoginPayload(email: 'TEST@example.com'))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');
});

it('ignores an existing bearer token and authenticates the submitted credentials', function (): void {
    $currentUser = User::factory()->create(['email' => 'current@example.com']);
    $submittedUser = User::factory()->create(['email' => 'submitted@example.com']);
    $currentToken = issueApiToken($currentUser, 'current-device');

    $submittedToken = $this->withToken($currentToken)
        ->postJson('/api/v1/login', validLoginPayload(email: 'submitted@example.com', deviceId: 'submitted-device'))
        ->assertOk()
        ->assertJsonPath('data.user.id', $submittedUser->id)
        ->json('data.token');

    assertApiTokenAuthenticates($currentToken);
    assertApiTokenAuthenticates($submittedToken);
});

it('deletes only the current token on logout', function (): void {
    $user = User::factory()->create(['email' => 'taylor@example.com']);
    $phoneToken = issueApiToken($user, 'phone');
    $tabletToken = issueApiToken($user, 'tablet');

    $this->withToken($phoneToken)
        ->postJson('/api/v1/logout')
        ->assertOk()
        ->assertJsonPath('message', 'Logged out successfully');

    expect($user->tokens()->count())->toBe(1);
    Auth::forgetGuards();
    assertApiTokenIsRejected($phoneToken);
    Auth::forgetGuards();
    assertApiTokenAuthenticates($tabletToken);
});

it('requires a token to log out', function (): void {
    $this->postJson('/api/v1/logout')
        ->assertUnauthorized();
});

function validLoginPayload(
    string $email = 'taylor@example.com',
    string $password = 'password',
    string $deviceId = 'device-1',
    string $deviceName = 'iPhone',
): array {
    return [
        'email' => $email,
        'password' => $password,
        'device_id' => $deviceId,
        'device_name' => $deviceName,
    ];
}
