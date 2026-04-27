<?php

declare(strict_types=1);

use App\Enums\OneTimePasswordPurpose;
use App\Models\OneTimePassword;
use App\Models\PersonalAccessToken;
use App\Models\User;
use App\Notifications\OneTimePasswordNotification;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

it('returns the same forgot password response for existing and missing emails', function (): void {
    Notification::fake();
    User::factory()->create(['email' => 'taylor@example.com']);

    $existing = $this->postJson('/api/v1/forgot-password', [
        'email' => 'taylor@example.com',
        'device_id' => 'device-1',
    ])->assertOk();

    $missing = $this->postJson('/api/v1/forgot-password', [
        'email' => 'missing@example.com',
        'device_id' => 'device-1',
    ])->assertOk();

    expect($existing->json('message'))->toBe($missing->json('message'));
});

it('creates a password reset code and sends a notification for existing emails only', function (): void {
    Notification::fake();
    $user = User::factory()->create(['email' => 'taylor@example.com']);

    $this->postJson('/api/v1/forgot-password', [
        'email' => 'taylor@example.com',
        'device_id' => 'device-1',
    ])->assertOk();

    $oneTimePassword = OneTimePassword::query()->whereBelongsTo($user)->firstOrFail();

    expect($oneTimePassword->purpose)->toBe(OneTimePasswordPurpose::PasswordReset)
        ->and($oneTimePassword->device_id_hash)->toBe(hash('sha256', 'device-1'));
    Notification::assertSentTo($user, OneTimePasswordNotification::class);

    $this->postJson('/api/v1/forgot-password', [
        'email' => 'missing@example.com',
        'device_id' => 'device-1',
    ])->assertOk();

    expect(OneTimePassword::query()->count())->toBe(1);
});

it('validates forgot password payloads', function (array $payload, string $field): void {
    Notification::fake();

    $this->postJson('/api/v1/forgot-password', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'missing email' => [['device_id' => 'device-1'], 'email'],
    'invalid email' => [['email' => 'not-an-email', 'device_id' => 'device-1'], 'email'],
    'uppercase email' => [['email' => 'TAYLOR@example.com', 'device_id' => 'device-1'], 'email'],
    'missing device id' => [['email' => 'taylor@example.com'], 'device_id'],
    'overlong device id' => [['email' => 'taylor@example.com', 'device_id' => str_repeat('a', 256)], 'device_id'],
]);

it('returns the generic forgot password response during cooldown without sending a duplicate notification', function (): void {
    Notification::fake();
    $user = User::factory()->create(['email' => 'taylor@example.com']);
    passwordResetOtp($user, sentAt: now());

    $this->postJson('/api/v1/forgot-password', [
        'email' => 'taylor@example.com',
        'device_id' => 'device-1',
    ])->assertOk()
        ->assertJsonPath('message', 'A reset code will be sent if the account exists.');

    expect(OneTimePassword::query()->count())->toBe(1);
    Notification::assertNothingSent();
});

it('may reset a password with a valid code and verify an unverified user', function (): void {
    Event::fake([PasswordReset::class, Verified::class]);
    $user = User::factory()->unverified()->create(['email' => 'taylor@example.com']);
    $oldRememberToken = $user->remember_token;
    $oldToken = issueApiToken($user);
    passwordResetOtp($user);

    $this->postJson('/api/v1/reset-password', validPasswordResetPayload())
        ->assertOk()
        ->assertJsonPath('message', 'Password reset successfully');

    $user->refresh();
    expect(Hash::check('NewPassword123!', $user->password))->toBeTrue()
        ->and($user->remember_token)->not->toBe($oldRememberToken)
        ->and($user->hasVerifiedEmail())->toBeTrue()
        ->and(PersonalAccessToken::query()->count())->toBe(0)
        ->and(OneTimePassword::query()->count())->toBe(0);

    Auth::forgetGuards();
    assertApiTokenIsRejected($oldToken);
    Event::assertDispatched(PasswordReset::class);
    Event::assertDispatched(Verified::class);
});

it('does not dispatch verified when resetting an already verified user password', function (): void {
    Event::fake([PasswordReset::class, Verified::class]);
    $user = User::factory()->create(['email' => 'taylor@example.com']);
    passwordResetOtp($user);

    $this->postJson('/api/v1/reset-password', validPasswordResetPayload())
        ->assertOk();

    Event::assertDispatched(PasswordReset::class);
    Event::assertNotDispatched(Verified::class);
});

it('validates reset password payloads', function (array $overrides, string $field): void {
    User::factory()->create(['email' => 'taylor@example.com']);

    $this->postJson('/api/v1/reset-password', [...validPasswordResetPayload(), ...$overrides])
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'missing email' => [['email' => null], 'email'],
    'uppercase email' => [['email' => 'TAYLOR@example.com'], 'email'],
    'missing code' => [['code' => null], 'code'],
    'non six digit code' => [['code' => '12345'], 'code'],
    'missing device id' => [['device_id' => null], 'device_id'],
    'missing password' => [['password' => null], 'password'],
    'missing confirmation' => [['password_confirmation' => null], 'password'],
]);

it('returns a code validation error for reset requests with a non-existent email', function (): void {
    $this->postJson('/api/v1/reset-password', validPasswordResetPayload(email: 'missing@example.com'))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('code');
});

it('fails invalid reset attempts without changing password or deleting tokens', function (array $overrides, string $field): void {
    $user = User::factory()->create(['email' => 'taylor@example.com']);
    $oldPassword = $user->password;
    issueApiToken($user);
    passwordResetOtp($user, deviceId: 'device-1', expiresAt: ($field === 'expired-code' ? now()->subSecond() : null));

    $response = $this->postJson('/api/v1/reset-password', [...validPasswordResetPayload(), ...$overrides]);

    $response->assertUnprocessable();

    if (! in_array($field, ['otp', 'expired-code'], true)) {
        $response->assertJsonValidationErrors($field);
    }

    expect($user->fresh()->password)->toBe($oldPassword)
        ->and(PersonalAccessToken::query()->count())->toBe(1);
})->with([
    'invalid email' => [['email' => 'not-an-email'], 'email'],
    'wrong code' => [['code' => '000000'], 'otp'],
    'different device' => [['device_id' => 'device-2'], 'otp'],
    'expired code' => [[], 'expired-code'],
    'weak password' => [['password' => 'short', 'password_confirmation' => 'short'], 'password'],
    'confirmation mismatch' => [['password_confirmation' => 'Mismatch123!'], 'password'],
]);

it('may update an authenticated password and delete all tokens', function (): void {
    $user = User::factory()->create(['email' => 'taylor@example.com']);
    $oldToken = issueApiToken($user, 'phone');
    issueApiToken($user, 'tablet');

    $this->withToken($oldToken)
        ->putJson('/api/v1/update-password', validPasswordUpdatePayload())
        ->assertOk()
        ->assertJsonPath('message', 'Password updated successfully');

    expect(Hash::check('NewPassword123!', $user->fresh()->password))->toBeTrue()
        ->and(PersonalAccessToken::query()->count())->toBe(0);

    Auth::forgetGuards();
    Auth::setDefaultDriver('web');
    assertApiTokenIsRejected($oldToken);

    Auth::forgetGuards();
    Auth::setDefaultDriver('web');
    $this->postJson('/api/v1/login', validPasswordLoginPayload(password: 'password'))
        ->assertUnprocessable();
    $this->postJson('/api/v1/login', validPasswordLoginPayload(password: 'NewPassword123!'))
        ->assertOk();
});

it('keeps tokens and password after failed authenticated password updates', function (array $overrides, string $field): void {
    $user = User::factory()->create(['email' => 'taylor@example.com']);
    $oldPassword = $user->password;
    $token = issueApiToken($user);

    $this->withToken($token)
        ->putJson('/api/v1/update-password', [...validPasswordUpdatePayload(), ...$overrides])
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);

    expect($user->fresh()->password)->toBe($oldPassword)
        ->and(PersonalAccessToken::query()->count())->toBe(1);

    Auth::forgetGuards();
    assertApiTokenAuthenticates($token);
})->with([
    'missing current password' => [['current_password' => null], 'current_password'],
    'wrong current password' => [['current_password' => 'wrong-password'], 'current_password'],
    'missing new password' => [['password' => null], 'password'],
    'weak password' => [['password' => 'short', 'password_confirmation' => 'short'], 'password'],
    'confirmation mismatch' => [['password_confirmation' => 'Mismatch123!'], 'password'],
]);

function passwordResetOtp(
    User $user,
    string $deviceId = 'device-1',
    mixed $sentAt = null,
    mixed $expiresAt = null,
): OneTimePassword {
    return OneTimePassword::factory()
        ->for($user)
        ->create([
            'purpose' => OneTimePasswordPurpose::PasswordReset,
            'device_id_hash' => hash('sha256', $deviceId),
            'sent_at' => $sentAt ?? now()->subSeconds(Config::integer('otp.resend_cooldown_in_seconds') + 1),
            'expires_at' => $expiresAt ?? now()->addMinutes(10),
        ]);
}

function validPasswordResetPayload(
    string $email = 'taylor@example.com',
    string $code = '123456',
    string $deviceId = 'device-1',
): array {
    return [
        'email' => $email,
        'code' => $code,
        'device_id' => $deviceId,
        'password' => 'NewPassword123!',
        'password_confirmation' => 'NewPassword123!',
    ];
}

function validPasswordUpdatePayload(string $currentPassword = 'password'): array
{
    return [
        'current_password' => $currentPassword,
        'password' => 'NewPassword123!',
        'password_confirmation' => 'NewPassword123!',
    ];
}

function validPasswordLoginPayload(string $password): array
{
    return [
        'email' => 'taylor@example.com',
        'password' => $password,
        'device_id' => 'login-device',
        'device_name' => 'iPhone',
    ];
}
