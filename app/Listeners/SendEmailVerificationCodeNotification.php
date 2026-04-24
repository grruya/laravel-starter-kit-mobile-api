<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Actions\Auth\IssueOneTimePassword;
use App\Enums\OneTimePasswordPurpose;
use App\Models\User;
use Illuminate\Auth\Events\Registered;

final readonly class SendEmailVerificationCodeNotification
{
    public function __construct(
        private IssueOneTimePassword $issueOneTimePassword,
    ) {}

    public function handle(Registered $event): void
    {
        if (! $event->user instanceof User || $event->user->hasVerifiedEmail()) {
            return;
        }

        $this->issueOneTimePassword->handle($event->user, OneTimePasswordPurpose::EmailVerification);
    }
}
