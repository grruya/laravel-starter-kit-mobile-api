<?php

declare(strict_types=1);

use App\Actions\Auth\IssueAuthToken;
use App\Actions\Auth\ResetUserPassword;
use App\Models\PersonalAccessToken;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;

it('hashes the password changes the remember token deletes tokens and dispatches password reset', function (): void {
    Event::fake([PasswordReset::class]);
    $user = User::factory()->create([
        'password' => 'OldPassword123!',
        'remember_token' => 'old-token',
    ]);
    resolve(IssueAuthToken::class)->handle($user, 'iPhone', 'device-1');
    resolve(IssueAuthToken::class)->handle($user, 'iPad', 'device-2');

    resolve(ResetUserPassword::class)->handle($user, 'NewPassword123!');

    $user->refresh();

    expect(Hash::check('NewPassword123!', $user->password))->toBeTrue()
        ->and(Hash::check('OldPassword123!', $user->password))->toBeFalse()
        ->and($user->remember_token)->not->toBe('old-token')
        ->and(PersonalAccessToken::query()->where('tokenable_id', $user->id)->count())->toBe(0);

    Event::assertDispatched(PasswordReset::class, fn (PasswordReset $event): bool => $event->user->is($user));
});
