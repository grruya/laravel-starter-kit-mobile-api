<?php

declare(strict_types=1);

use App\Actions\Auth\ConsumeOneTimePassword;
use App\Enums\OneTimePasswordPurpose;
use App\Exceptions\OneTimePasswordException;
use App\Models\OneTimePassword;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;

it('accepts a valid OTP and deletes it', function (): void {
    $user = User::factory()->create();
    $oneTimePassword = createConsumableOtp($user);

    resolve(ConsumeOneTimePassword::class)->handle($user, OneTimePasswordPurpose::EmailVerification, '123456', 'device-1');

    expect($oneTimePassword->fresh())->toBeNull();
});

it('rejects invalid OTPs and increments attempts', function (): void {
    $user = User::factory()->create();
    $oneTimePassword = createConsumableOtp($user);

    expect(fn () => resolve(ConsumeOneTimePassword::class)->handle($user, OneTimePasswordPurpose::EmailVerification, '000000', 'device-1'))
        ->toThrow(OneTimePasswordException::class);

    expect($oneTimePassword->fresh()->attempts)->toBe(1);
});

it('deletes expired OTPs and reports expiration', function (): void {
    $user = User::factory()->create();
    $oneTimePassword = createConsumableOtp($user, ['expires_at' => now()->subSecond()]);

    assertOtpException('otp_expired', fn () => resolve(ConsumeOneTimePassword::class)->handle(
        $user,
        OneTimePasswordPurpose::EmailVerification,
        '123456',
        'device-1',
    ));

    expect($oneTimePassword->fresh())->toBeNull();
});

it('rejects valid codes from a different device without incrementing attempts', function (): void {
    $user = User::factory()->create();
    $oneTimePassword = createConsumableOtp($user);

    assertOtpException('otp_different_device', fn () => resolve(ConsumeOneTimePassword::class)->handle(
        $user,
        OneTimePasswordPurpose::EmailVerification,
        '123456',
        'other-device',
    ));

    expect($oneTimePassword->fresh()->attempts)->toBe(0);
});

it('rejects missing OTPs', function (): void {
    $user = User::factory()->create();

    assertOtpException('otp_invalid', fn () => resolve(ConsumeOneTimePassword::class)->handle(
        $user,
        OneTimePasswordPurpose::EmailVerification,
        '123456',
        'device-1',
    ));
});

it('deletes an OTP at max wrong attempts and the next try is missing', function (): void {
    $user = User::factory()->create();
    $oneTimePassword = createConsumableOtp($user, [
        'attempts' => Config::integer('otp.max_attempts') - 1,
    ]);

    assertOtpException('otp_invalid', fn () => resolve(ConsumeOneTimePassword::class)->handle(
        $user,
        OneTimePasswordPurpose::EmailVerification,
        '000000',
        'device-1',
    ));

    expect($oneTimePassword->fresh())->toBeNull();

    assertOtpException('otp_invalid', fn () => resolve(ConsumeOneTimePassword::class)->handle(
        $user,
        OneTimePasswordPurpose::EmailVerification,
        '123456',
        'device-1',
    ));
});

it('allows any device when an OTP has no device hash', function (): void {
    $user = User::factory()->create();
    $oneTimePassword = createConsumableOtp($user, ['device_id_hash' => null]);

    resolve(ConsumeOneTimePassword::class)->handle($user, OneTimePasswordPurpose::EmailVerification, '123456', 'any-device');

    expect($oneTimePassword->fresh())->toBeNull();
});

function createConsumableOtp(User $user, array $attributes = []): OneTimePassword
{
    return OneTimePassword::factory()->for($user)->create([
        'purpose' => OneTimePasswordPurpose::EmailVerification,
        'code_hash' => Hash::make('123456'),
        'device_id_hash' => hash('sha256', 'device-1'),
        'attempts' => 0,
        'expires_at' => now()->addMinutes(10),
        ...$attributes,
    ]);
}

function assertOtpException(string $errorCode, Closure $callback): void
{
    try {
        $callback();

        test()->fail('Expected an OTP exception to be thrown.');
    } catch (OneTimePasswordException $oneTimePasswordException) {
        expect($oneTimePasswordException->errorCode)->toBe($errorCode);
    }
}
