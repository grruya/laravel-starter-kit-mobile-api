<?php

declare(strict_types=1);

use App\Actions\Auth\IssueOneTimePassword;
use App\Enums\OneTimePasswordPurpose;
use App\Exceptions\OneTimePasswordException;
use App\Models\OneTimePassword;
use App\Models\User;
use App\Notifications\OneTimePasswordNotification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

it('creates a device-bound OTP with the configured expiry and sends the notification', function (): void {
    Notification::fake();
    Config::set('otp.expires_in_minutes', 10);
    $user = User::factory()->create();

    resolve(IssueOneTimePassword::class)->handle($user, OneTimePasswordPurpose::EmailVerification, 'device-1');

    $oneTimePassword = OneTimePassword::query()->whereBelongsTo($user)->firstOrFail();

    expect($oneTimePassword->purpose)->toBe(OneTimePasswordPurpose::EmailVerification)
        ->and($oneTimePassword->device_id_hash)->toBe(hash('sha256', 'device-1'))
        ->and($oneTimePassword->attempts)->toBe(0)
        ->and($oneTimePassword->sent_at->timestamp)->toBe(now()->timestamp)
        ->and($oneTimePassword->expires_at->timestamp)->toBe(now()->addMinutes(10)->timestamp);

    Notification::assertSentTo($user, OneTimePasswordNotification::class, function (OneTimePasswordNotification $notification) use ($oneTimePassword, $user): bool {
        $code = codeFromNotification($notification, $user);

        return mb_strlen($code) === 6
            && Hash::check($code, $oneTimePassword->code_hash);
    });
});

it('enforces cooldown before replacing an existing OTP', function (): void {
    Notification::fake();
    $user = User::factory()->create();
    OneTimePassword::factory()->for($user)->create([
        'purpose' => OneTimePasswordPurpose::EmailVerification,
        'sent_at' => now(),
    ]);

    try {
        resolve(IssueOneTimePassword::class)->handle($user, OneTimePasswordPurpose::EmailVerification, 'device-1');

        $this->fail('Expected the OTP action to throw a cooldown exception.');
    } catch (OneTimePasswordException $oneTimePasswordException) {
        expect($oneTimePasswordException->errorCode)->toBe('otp_cooldown')
            ->and($oneTimePasswordException->status)->toBe(429)
            ->and($oneTimePasswordException->retryAfterInSeconds)->toBe(Config::integer('otp.resend_cooldown_in_seconds'));
    }

    Notification::assertNothingSent();
});

it('generates six-digit codes including leading-zero cases', function (): void {
    Notification::fake();
    $sawLeadingZero = false;

    for ($attempt = 0; $attempt < 150; $attempt++) {
        $user = User::factory()->create();

        resolve(IssueOneTimePassword::class)->handle($user, OneTimePasswordPurpose::EmailVerification, 'device-'.$attempt);

        Notification::assertSentTo($user, OneTimePasswordNotification::class, function (OneTimePasswordNotification $notification) use ($user, &$sawLeadingZero): bool {
            $code = codeFromNotification($notification, $user);

            expect($code)->toMatch('/^\d{6}$/');

            $sawLeadingZero = $sawLeadingZero || str_starts_with($code, '0');

            return true;
        });

        if ($sawLeadingZero) {
            break;
        }
    }

    expect($sawLeadingZero)->toBeTrue();
});

it('replaces an old OTP after cooldown while preserving one row per user and purpose', function (): void {
    Notification::fake();
    $user = User::factory()->create();
    $oldOneTimePassword = OneTimePassword::factory()->for($user)->create([
        'purpose' => OneTimePasswordPurpose::PasswordReset,
        'device_id_hash' => hash('sha256', 'old-device'),
        'sent_at' => now()->subSeconds(Config::integer('otp.resend_cooldown_in_seconds') + 1),
        'attempts' => 3,
    ]);

    resolve(IssueOneTimePassword::class)->handle($user, OneTimePasswordPurpose::PasswordReset, 'new-device');

    $oneTimePassword = OneTimePassword::query()->whereBelongsTo($user)->where('purpose', OneTimePasswordPurpose::PasswordReset)->firstOrFail();

    expect($oneTimePassword->id)->toBe($oldOneTimePassword->id)
        ->and($oneTimePassword->attempts)->toBe(0)
        ->and($oneTimePassword->device_id_hash)->toBe(hash('sha256', 'new-device'))
        ->and(OneTimePassword::query()->whereBelongsTo($user)->where('purpose', OneTimePasswordPurpose::PasswordReset)->count())->toBe(1);

    Notification::assertSentTo($user, OneTimePasswordNotification::class);
});

function codeFromNotification(OneTimePasswordNotification $notification, User $user): string
{
    $mailMessage = $notification->toMail($user);

    expect($mailMessage)->toBeInstanceOf(MailMessage::class);

    preg_match('/:\s*(\d{6})$/', (string) $mailMessage->introLines[0], $matches);

    return $matches[1] ?? '';
}
