<?php

declare(strict_types=1);

use App\Enums\OneTimePasswordPurpose;
use App\Models\OneTimePassword;
use App\Models\User;
use App\Notifications\OneTimePasswordNotification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;

it('builds the email verification mail message', function (): void {
    Config::set('otp.length', 6);
    Config::set('otp.expires_in_minutes', 10);
    $notification = new OneTimePasswordNotification(OneTimePasswordPurpose::EmailVerification, '123456', 1, 'hash');

    $mailMessage = $notification->toMail(User::factory()->make());

    expect($mailMessage)->toBeInstanceOf(MailMessage::class)
        ->and($mailMessage->subject)->toBe('Verify your email')
        ->and($mailMessage->introLines)->toContain('Use this 6-digit code to verify your email address: 123456')
        ->and($mailMessage->introLines)->toContain('This code expires in 10 minutes.');
});

it('builds the password reset mail message', function (): void {
    Config::set('otp.length', 6);
    Config::set('otp.expires_in_minutes', 1);
    $notification = new OneTimePasswordNotification(OneTimePasswordPurpose::PasswordReset, '654321', 1, 'hash');

    $mailMessage = $notification->toMail(User::factory()->make());

    expect($mailMessage->subject)->toBe('Reset your password')
        ->and($mailMessage->introLines)->toContain('Use this 6-digit code to reset your password: 654321')
        ->and($mailMessage->introLines)->toContain('This code expires in 1 minute.');
});

it('deletes only the matching OTP when notification delivery fails', function (): void {
    $user = User::factory()->create();
    $matchingHash = Hash::make('123456');
    $otherHash = Hash::make('123456');
    $matching = OneTimePassword::factory()->for($user)->create([
        'purpose' => OneTimePasswordPurpose::EmailVerification,
        'code_hash' => $matchingHash,
    ]);
    $wrongPurpose = OneTimePassword::factory()->for($user)->create([
        'purpose' => OneTimePasswordPurpose::PasswordReset,
        'code_hash' => $matchingHash,
    ]);
    $wrongHash = OneTimePassword::factory()->create([
        'purpose' => OneTimePasswordPurpose::EmailVerification,
        'code_hash' => $otherHash,
    ]);

    new OneTimePasswordNotification(
        OneTimePasswordPurpose::EmailVerification,
        '123456',
        $matching->id,
        $matchingHash,
    )->failed(null);

    expect($matching->fresh())->toBeNull()
        ->and($wrongPurpose->fresh())->not->toBeNull()
        ->and($wrongHash->fresh())->not->toBeNull();
});
