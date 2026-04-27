<?php

declare(strict_types=1);

use App\Actions\UpdateUser;
use App\Enums\OneTimePasswordPurpose;
use App\Models\OneTimePassword;
use App\Models\User;
use App\Notifications\OneTimePasswordNotification;
use Illuminate\Support\Facades\Notification;

it('updates name and email clears verification and issues an OTP when email changes', function (): void {
    Notification::fake();
    $user = User::factory()->create([
        'name' => 'Old Name',
        'email' => 'old@example.com',
        'email_verified_at' => now(),
    ]);
    OneTimePassword::factory()->for($user)->create([
        'purpose' => OneTimePasswordPurpose::EmailVerification,
    ]);

    $updatedUser = resolve(UpdateUser::class)->handle($user, [
        'name' => 'New Name',
        'email' => 'new@example.com',
    ], 'device-1');
    $oneTimePassword = OneTimePassword::query()->where('user_id', $user->id)->firstOrFail();

    expect($updatedUser->name)->toBe('New Name')
        ->and($updatedUser->email)->toBe('new@example.com')
        ->and($updatedUser->email_verified_at)->toBeNull()
        ->and($oneTimePassword->purpose)->toBe(OneTimePasswordPurpose::EmailVerification)
        ->and($oneTimePassword->device_id_hash)->toBe(hash('sha256', 'device-1'))
        ->and(OneTimePassword::query()->where('user_id', $user->id)->count())->toBe(1);

    Notification::assertSentTo($user, OneTimePasswordNotification::class);
});

it('keeps verification and does not issue OTP when updating name only or the same email', function (array $attributes): void {
    Notification::fake();
    $verifiedAt = now();
    $user = User::factory()->create([
        'name' => 'Old Name',
        'email' => 'same@example.com',
        'email_verified_at' => $verifiedAt,
    ]);

    $updatedUser = resolve(UpdateUser::class)->handle($user, [
        'name' => $attributes['name'] ?? $user->name,
        'email' => $attributes['email'] ?? $user->email,
    ], 'device-1');

    expect($updatedUser->email_verified_at?->timestamp)->toBe($verifiedAt->timestamp)
        ->and(OneTimePassword::query()->where('user_id', $user->id)->count())->toBe(0);

    Notification::assertNothingSent();
})->with([
    'name only' => [['name' => 'New Name']],
    'same email' => [['name' => 'New Name', 'email' => 'same@example.com']],
]);
