<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\OneTimePasswordPurpose;
use App\Models\User;

final readonly class UpdateUser
{
    public function __construct(private IssueOneTimePassword $issueOneTimePassword) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(User $user, array $attributes): User
    {
        $emailChanged = isset($attributes['email']) && $user->email !== $attributes['email'];

        $user->update([
            ...$attributes,
            ...($emailChanged ? ['email_verified_at' => null] : []),
        ]);

        if ($emailChanged) {
            $user->oneTimePasswords()->delete();
            $this->issueOneTimePassword->handle($user, OneTimePasswordPurpose::EmailVerification);
        }

        return $user;
    }
}
