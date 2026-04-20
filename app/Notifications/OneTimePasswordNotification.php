<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\OneTimePasswordPurpose;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Config;
use SensitiveParameter;

final class OneTimePasswordNotification extends Notification
{
    use Queueable;

    public function __construct(
        public OneTimePasswordPurpose $purpose,
        #[SensitiveParameter] public string $code,
    ) {}

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
