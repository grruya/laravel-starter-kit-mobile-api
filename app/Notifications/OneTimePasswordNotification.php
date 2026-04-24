<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\OneTimePasswordPurpose;
use App\Models\OneTimePassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Support\Facades\Config;
use SensitiveParameter;
use Throwable;

#[Tries(1)]
final class OneTimePasswordNotification extends Notification implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly OneTimePasswordPurpose $purpose,
        #[SensitiveParameter] private readonly string $code,
        private readonly int $oneTimePasswordId,
        private readonly string $codeHash,
    ) {
        $this->afterCommit();
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $codeLength = Config::integer('otp.length');
        $expiresInMinutes = Config::integer('otp.expires_in_minutes');
        $minuteLabel = $expiresInMinutes === 1 ? 'minute' : 'minutes';

        [$subject, $line] = match ($this->purpose) {
            OneTimePasswordPurpose::EmailVerification => [
                'Verify your email',
                sprintf('Use this %d-digit code to verify your email address: %s', $codeLength, $this->code),
            ],
            OneTimePasswordPurpose::PasswordReset => [
                'Reset your password',
                sprintf('Use this %d-digit code to reset your password: %s', $codeLength, $this->code),
            ],
        };

        return (new MailMessage)
            ->subject($subject)
            ->line($line)
            ->line(sprintf('This code expires in %d %s.', $expiresInMinutes, $minuteLabel))
            ->line('If you did not request this code, you can ignore this email.');
    }

    public function failed(?Throwable $exception): void
    {
        OneTimePassword::query()
            ->whereKey($this->oneTimePasswordId)
            ->where('purpose', $this->purpose->value)
            ->where('code_hash', $this->codeHash)
            ->delete();
    }

    /**
     * @return array<string, string>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'purpose' => $this->purpose->value,
        ];
    }
}
