<?php

declare(strict_types=1);

use App\Actions\Auth\RegisterUser;
use App\Enums\OneTimePasswordPurpose;
use App\Models\OneTimePassword;
use App\Models\PersonalAccessToken;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

it('creates a hashed-password user verification code and auth token for the submitted device', function (): void {
    $registration = resolve(RegisterUser::class)->handle([
        'name' => 'Taylor Otwell',
        'email' => 'taylor@example.com',
    ], 'Password123!', 'iPhone', 'device-1');
    $user = $registration['user'];
    $oneTimePassword = OneTimePassword::query()->whereBelongsTo($user)->firstOrFail();
    [$tokenId, $plainTextToken] = explode('|', $registration['token'], 2);
    $accessToken = PersonalAccessToken::query()->findOrFail($tokenId);

    expect($user)->toBeInstanceOf(User::class)
        ->and($user->name)->toBe('Taylor Otwell')
        ->and($user->email)->toBe('taylor@example.com')
        ->and(Hash::check('Password123!', $user->password))->toBeTrue()
        ->and($user->password)->not->toBe('Password123!')
        ->and($oneTimePassword->purpose)->toBe(OneTimePasswordPurpose::EmailVerification)
        ->and($oneTimePassword->device_id_hash)->toBe(hash('sha256', 'device-1'))
        ->and($accessToken->name)->toBe('iPhone')
        ->and($accessToken->device_id_hash)->toBe(hash('sha256', 'device-1'))
        ->and($accessToken->token)->toBe(hash('sha256', $plainTextToken));
});

it('does not create a user when the database transaction fails', function (): void {
    User::factory()->create(['email' => 'duplicate@example.com']);

    expect(fn () => resolve(RegisterUser::class)->handle([
        'name' => 'Taylor Otwell',
        'email' => 'duplicate@example.com',
    ], 'Password123!', 'iPhone', 'device-1'))->toThrow(QueryException::class);

    expect(User::query()->where('email', 'duplicate@example.com')->count())->toBe(1);
});

it('rolls back the user when one-time password issuance fails', function (): void {
    Config::set('otp.length', -1);

    expect(fn () => resolve(RegisterUser::class)->handle([
        'name' => 'Taylor Otwell',
        'email' => 'taylor@example.com',
    ], 'Password123!', 'iPhone', 'device-1'))->toThrow(TypeError::class);

    $this->assertDatabaseMissing('users', ['email' => 'taylor@example.com']);
    expect(OneTimePassword::query()->count())->toBe(0)
        ->and(PersonalAccessToken::query()->count())->toBe(0);
});

it('rolls back the user and verification code when auth token issuance fails', function (): void {
    Str::createRandomStringsUsing(fn (int $length): string => str_repeat('a', $length));
    PersonalAccessToken::factory()->create([
        'token' => hash('sha256', str_repeat('a', 40).'c95b8a25'),
    ]);

    expect(fn () => resolve(RegisterUser::class)->handle([
        'name' => 'Taylor Otwell',
        'email' => 'taylor@example.com',
    ], 'Password123!', 'iPhone', 'device-1'))->toThrow(QueryException::class);

    $this->assertDatabaseMissing('users', ['email' => 'taylor@example.com']);
    expect(OneTimePassword::query()->count())->toBe(0)
        ->and(PersonalAccessToken::query()->count())->toBe(1);
});
