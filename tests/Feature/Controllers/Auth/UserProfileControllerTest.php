<?php

declare(strict_types=1);

use App\Enums\OneTimePasswordPurpose;
use App\Models\OneTimePassword;
use App\Models\User;
use App\Notifications\OneTimePasswordNotification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;

it('may update an authenticated user profile', function (): void {
    $user = User::factory()->create(['email' => 'old@example.com']);

    $this->withToken(issueApiToken($user))
        ->putJson('/api/v1/user', validProfilePayload(name: 'New Name', email: 'old@example.com'))
        ->assertOk()
        ->assertJsonPath('data.name', 'New Name')
        ->assertJsonPath('data.email', 'old@example.com')
        ->assertJsonPath('data.is_verified', true)
        ->assertJsonMissingPath('data.password')
        ->assertJsonMissingPath('data.remember_token')
        ->assertJsonMissingPath('data.tokens')
        ->assertJsonMissingPath('data.one_time_passwords');

    expect($user->fresh()->name)->toBe('New Name');
});

it('keeps email verification when the email is unchanged', function (array $payload): void {
    $user = User::factory()->create(['name' => 'Old Name', 'email' => 'same@example.com']);
    $verifiedAt = $user->email_verified_at;

    $this->withToken(issueApiToken($user))
        ->putJson('/api/v1/user', $payload)
        ->assertOk();

    expect($user->fresh()->email_verified_at->equalTo($verifiedAt))->toBeTrue();
})->with([
    'same email and new name' => [validProfilePayload(name: 'New Name', email: 'same@example.com')],
    'name only change with same email' => [validProfilePayload(name: 'Another Name', email: 'same@example.com')],
]);

it('updates new lowercase profile emails, clears verification, replaces old codes, and sends a notification', function (): void {
    Notification::fake();
    $user = User::factory()->create(['email' => 'old@example.com']);
    OneTimePassword::factory()->for($user)->create(['purpose' => OneTimePasswordPurpose::PasswordReset]);

    $this->withToken(issueApiToken($user))
        ->putJson('/api/v1/user', validProfilePayload(email: 'new@example.com', deviceId: 'profile-device'))
        ->assertOk()
        ->assertJsonPath('data.email', 'new@example.com')
        ->assertJsonPath('data.is_verified', false);

    $user->refresh();
    $oneTimePassword = OneTimePassword::query()->whereBelongsTo($user)->firstOrFail();

    expect($user->email)->toBe('new@example.com')
        ->and($user->email_verified_at)->toBeNull()
        ->and(OneTimePassword::query()->whereBelongsTo($user)->count())->toBe(1)
        ->and($oneTimePassword->purpose)->toBe(OneTimePasswordPurpose::EmailVerification)
        ->and($oneTimePassword->device_id_hash)->toBe(hash('sha256', 'profile-device'));

    Notification::assertSentTo($user, OneTimePasswordNotification::class);
});

it('rejects uppercase profile emails', function (): void {
    Notification::fake();
    $user = User::factory()->create(['email' => 'old@example.com']);

    $this->withToken(issueApiToken($user))
        ->putJson('/api/v1/user', validProfilePayload(email: 'NEW@example.com'))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');

    expect($user->fresh()->email)->toBe('old@example.com')
        ->and(OneTimePassword::query()->whereBelongsTo($user)->count())->toBe(0);

    Notification::assertNothingSent();
});

it('validates profile update payloads', function (array $overrides, string $field): void {
    Notification::fake();
    $user = User::factory()->create(['email' => 'current@example.com']);
    User::factory()->create(['email' => 'taken@example.com']);

    $this->withToken(issueApiToken($user))
        ->putJson('/api/v1/user', [...validProfilePayload(email: 'current@example.com'), ...$overrides])
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'duplicate email' => [['email' => 'taken@example.com'], 'email'],
    'invalid email' => [['email' => 'not-an-email'], 'email'],
    'missing name' => [['name' => null], 'name'],
    'missing email' => [['email' => null], 'email'],
    'too long name' => [['name' => str_repeat('a', 256)], 'name'],
    'too long email' => [['email' => str_repeat('a', 245).'@example.com'], 'email'],
]);

it('allows keeping the current email through the unique email rule', function (): void {
    $user = User::factory()->create(['email' => 'current@example.com']);

    $this->withToken(issueApiToken($user))
        ->putJson('/api/v1/user', validProfilePayload(email: 'current@example.com'))
        ->assertOk()
        ->assertJsonPath('data.email', 'current@example.com');
});

it('does not change the user or one-time passwords after a failed profile update', function (): void {
    Notification::fake();
    $user = User::factory()->create(['name' => 'Old Name', 'email' => 'old@example.com']);
    $existingOneTimePassword = OneTimePassword::factory()
        ->for($user)
        ->create(['purpose' => OneTimePasswordPurpose::PasswordReset]);

    $this->withToken(issueApiToken($user))
        ->putJson('/api/v1/user', validProfilePayload(email: 'not-an-email'))
        ->assertUnprocessable();

    $user->refresh();
    expect($user->name)->toBe('Old Name')
        ->and($user->email)->toBe('old@example.com')
        ->and($existingOneTimePassword->fresh())->not->toBeNull()
        ->and(OneTimePassword::query()->count())->toBe(1);

    Notification::assertNothingSent();
});

it('reports user verification state in user responses', function (): void {
    Notification::fake();
    $user = User::factory()->unverified()->create(['email' => 'state@example.com']);
    $token = issueApiToken($user);

    $this->withToken($token)
        ->putJson('/api/v1/user', validProfilePayload(email: 'state@example.com'))
        ->assertOk()
        ->assertJsonPath('data.is_verified', false);

    $user->markEmailAsVerified();
    Auth::forgetGuards();
    Auth::setDefaultDriver('web');

    $this->withToken($token)
        ->putJson('/api/v1/user', validProfilePayload(email: 'state@example.com'))
        ->assertOk()
        ->assertJsonPath('data.is_verified', true);

    $this->withToken($token)
        ->putJson('/api/v1/user', validProfilePayload(email: 'changed@example.com'))
        ->assertOk()
        ->assertJsonPath('data.is_verified', false);
});

function validProfilePayload(
    string $name = 'Taylor Otwell',
    string $email = 'taylor@example.com',
    string $deviceId = 'device-1',
): array {
    return [
        'name' => $name,
        'email' => $email,
        'device_id' => $deviceId,
    ];
}
