<?php

declare(strict_types=1);

namespace App\Enums;

enum OneTimePasswordPurpose: string
{
    case EmailVerification = 'email_verification';
    case PasswordReset = 'password_reset';
}
