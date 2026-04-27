<?php

declare(strict_types=1);

use App\Actions\Auth\IssueAuthToken;
use App\Actions\DeleteUser;
use App\Enums\OneTimePasswordPurpose;
use App\Models\OneTimePassword;
use App\Models\PersonalAccessToken;
use App\Models\User;

it('deletes the user and related auth records', function (): void {
    $user = User::factory()->create();
    resolve(IssueAuthToken::class)->handle($user, 'iPhone', 'device-1');
    OneTimePassword::factory()->for($user)->create([
        'purpose' => OneTimePasswordPurpose::EmailVerification,
    ]);

    resolve(DeleteUser::class)->handle($user);

    $this->assertDatabaseMissing('users', ['id' => $user->id]);
    expect(PersonalAccessToken::query()->where('tokenable_id', $user->id)->count())->toBe(0)
        ->and(OneTimePassword::query()->where('user_id', $user->id)->count())->toBe(0);
});
