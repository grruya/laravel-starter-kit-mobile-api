<?php

declare(strict_types=1);

use App\Enums\OneTimePasswordPurpose;
use App\Models\OneTimePassword;
use App\Models\User;
use App\Notifications\OneTimePasswordNotification;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

it('may request an email verification code', function (): void {
    Notification::fake();
    $user = User::factory()->unverified()->create();

    $this->withToken(issueApiToken($user))
        ->postJson('/api/v1/email-verification-code', ['device_id' => 'device-1'])
        ->assertOk()
        ->assertJsonPath('message', 'Verification code sent');

    $oneTimePassword = OneTimePassword::query()->whereBelongsTo($user)->firstOrFail();

    expect($oneTimePassword->purpose)->toBe(OneTimePasswordPurpose::EmailVerification)
        ->and($oneTimePassword->device_id_hash)->toBe(hash('sha256', 'device-1'));

    Notification::assertSentTo($user, OneTimePasswordNotification::class);
});

it('validates email verification code request payloads', function (array $payload, string $field): void {
    Notification::fake();
    $user = User::factory()->unverified()->create();

    $this->withToken(issueApiToken($user))
        ->postJson('/api/v1/email-verification-code', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'missing device id' => [[], 'device_id'],
    'overlong device id' => [['device_id' => str_repeat('a', 256)], 'device_id'],
]);

it('does not issue a verification code for already verified users', function (): void {
    Notification::fake();
    $user = User::factory()->create();

    $this->withToken(issueApiToken($user))
        ->postJson('/api/v1/email-verification-code', ['device_id' => 'device-1'])
        ->assertOk()
        ->assertJsonPath('message', 'Email already verified');

    expect(OneTimePassword::query()->count())->toBe(0);
    Notification::assertNothingSent();
});

it('returns cooldown details when requesting another verification code too soon', function (): void {
    Notification::fake();
    $user = User::factory()->unverified()->create();
    emailVerificationOtp($user, sentAt: now());

    $this->withToken(issueApiToken($user))
        ->postJson('/api/v1/email-verification-code', ['device_id' => 'device-1'])
        ->assertTooManyRequests()
        ->assertHeader('Retry-After', (string) Config::integer('otp.resend_cooldown_in_seconds'))
        ->assertJsonPath('code', 'otp_cooldown')
        ->assertJsonPath('retry_after', Config::integer('otp.resend_cooldown_in_seconds'));

    Notification::assertNothingSent();
});

it('may verify an email with a valid code once', function (): void {
    Event::fake([Verified::class]);
    $user = User::factory()->unverified()->create();
    emailVerificationOtp($user);

    $this->withToken(issueApiToken($user))
        ->postJson('/api/v1/verify-email', ['code' => '123456', 'device_id' => 'device-1'])
        ->assertOk()
        ->assertJsonPath('message', 'Email verified successfully');

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue()
        ->and(OneTimePassword::query()->count())->toBe(0);
    Event::assertDispatched(Verified::class);

    $this->withToken(issueApiToken($user->fresh()))
        ->postJson('/api/v1/verify-email', ['code' => '123456', 'device_id' => 'device-1'])
        ->assertOk()
        ->assertJsonPath('message', 'Email already verified');
});

it('validates verify email payloads', function (array $payload, string $field): void {
    $user = User::factory()->unverified()->create();

    $this->withToken(issueApiToken($user))
        ->postJson('/api/v1/verify-email', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'missing code' => [['device_id' => 'device-1'], 'code'],
    'non numeric code' => [['code' => 'abcdef', 'device_id' => 'device-1'], 'code'],
    'short code' => [['code' => '12345', 'device_id' => 'device-1'], 'code'],
    'long code' => [['code' => '1234567', 'device_id' => 'device-1'], 'code'],
    'missing device id' => [['code' => '123456'], 'device_id'],
    'overlong device id' => [['code' => '123456', 'device_id' => str_repeat('a', 256)], 'device_id'],
]);

it('increments attempts for wrong verification codes and deletes after the max attempts', function (): void {
    $user = User::factory()->unverified()->create();
    $oneTimePassword = emailVerificationOtp($user);
    $token = issueApiToken($user);

    $this->withToken($token)
        ->postJson('/api/v1/verify-email', ['code' => '000000', 'device_id' => 'device-1'])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'otp_invalid');

    expect($oneTimePassword->fresh()->attempts)->toBe(1);

    foreach (range(2, Config::integer('otp.max_attempts')) as $attempt) {
        $this->withToken($token)
            ->postJson('/api/v1/verify-email', ['code' => '000000', 'device_id' => 'device-1'])
            ->assertUnprocessable();
    }

    expect($oneTimePassword->fresh())->toBeNull();
});

it('deletes expired verification codes', function (): void {
    $user = User::factory()->unverified()->create();
    $oneTimePassword = emailVerificationOtp($user, expiresAt: now()->subSecond());

    $this->withToken(issueApiToken($user))
        ->postJson('/api/v1/verify-email', ['code' => '123456', 'device_id' => 'device-1'])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'otp_expired');

    expect($oneTimePassword->fresh())->toBeNull();
});

it('rejects codes from a different device without deleting them', function (): void {
    $user = User::factory()->unverified()->create();
    $oneTimePassword = emailVerificationOtp($user, deviceId: 'device-1');

    $this->withToken(issueApiToken($user))
        ->postJson('/api/v1/verify-email', ['code' => '123456', 'device_id' => 'device-2'])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'otp_different_device');

    expect($oneTimePassword->fresh())->not->toBeNull();
});

it('returns invalid when no verification code exists', function (): void {
    $user = User::factory()->unverified()->create();

    $this->withToken(issueApiToken($user))
        ->postJson('/api/v1/verify-email', ['code' => '123456', 'device_id' => 'device-1'])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'otp_invalid');
});

function emailVerificationOtp(
    User $user,
    string $deviceId = 'device-1',
    mixed $sentAt = null,
    mixed $expiresAt = null,
): OneTimePassword {
    return OneTimePassword::factory()
        ->for($user)
        ->create([
            'purpose' => OneTimePasswordPurpose::EmailVerification,
            'device_id_hash' => hash('sha256', $deviceId),
            'sent_at' => $sentAt ?? now()->subSeconds(Config::integer('otp.resend_cooldown_in_seconds') + 1),
            'expires_at' => $expiresAt ?? now()->addMinutes(10),
        ]);
}
