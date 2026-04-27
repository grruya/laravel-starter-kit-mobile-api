<?php

declare(strict_types=1);

use App\Enums\OneTimePasswordPurpose;
use App\Models\OneTimePassword;
use App\Models\PersonalAccessToken;
use App\Models\User;
use App\Notifications\OneTimePasswordNotification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

it('may register a user and return a working token', function (): void {
    Notification::fake();

    $response = $this->postJson('/api/v1/register', validRegistrationPayload())
        ->assertCreated()
        ->assertJsonPath('data.user.name', 'Taylor Otwell')
        ->assertJsonPath('data.user.email', 'taylor@example.com')
        ->assertJsonStructure(['data' => ['user' => ['id', 'name', 'email', 'created_at', 'updated_at'], 'token']]);

    $user = User::query()->where('email', 'taylor@example.com')->firstOrFail();
    $token = $response->json('data.token');

    expect($user->email_verified_at)->toBeNull()
        ->and(Hash::check('Password123!', $user->password))->toBeTrue()
        ->and(PersonalAccessToken::query()->count())->toBe(1)
        ->and(OneTimePassword::query()->where('user_id', $user->id)->where('purpose', OneTimePasswordPurpose::EmailVerification)->count())->toBe(1);

    $this->withToken($token)
        ->postJson('/api/v1/logout')
        ->assertOk();

    Notification::assertSentTo($user, OneTimePasswordNotification::class);
});

it('creates a device-bound email verification code during registration', function (): void {
    Notification::fake();

    $this->postJson('/api/v1/register', validRegistrationPayload(deviceId: 'registration-device'))
        ->assertCreated();

    $user = User::query()->firstOrFail();
    $oneTimePassword = OneTimePassword::query()->whereBelongsTo($user)->firstOrFail();

    expect($oneTimePassword->purpose)->toBe(OneTimePasswordPurpose::EmailVerification)
        ->and($oneTimePassword->device_id_hash)->toBe(hash('sha256', 'registration-device'));
});

it('hides sensitive fields from the registration response', function (): void {
    Notification::fake();

    $this->postJson('/api/v1/register', validRegistrationPayload())
        ->assertCreated()
        ->assertJsonMissingPath('data.user.password')
        ->assertJsonMissingPath('data.user.remember_token')
        ->assertJsonMissingPath('data.user.tokens')
        ->assertJsonMissingPath('data.user.one_time_passwords')
        ->assertJsonMissingPath('data.user.token')
        ->assertJsonMissingPath('data.token.token');
});

it('requires registration fields', function (string $field): void {
    Notification::fake();
    $payload = validRegistrationPayload();
    unset($payload[$field]);

    $this->postJson('/api/v1/register', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'name' => 'name',
    'email' => 'email',
    'password' => 'password',
    'password confirmation' => 'password',
    'device id' => 'device_id',
    'device name' => 'device_name',
]);

it('rejects invalid registration payloads', function (array $overrides, string $field): void {
    Notification::fake();

    $this->postJson('/api/v1/register', [...validRegistrationPayload(), ...$overrides])
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'malformed email' => [['email' => 'not-an-email'], 'email'],
    'weak password' => [['password' => 'short', 'password_confirmation' => 'short'], 'password'],
    'confirmation mismatch' => [['password_confirmation' => 'Different123!'], 'password'],
    'overlong device id' => [['device_id' => str_repeat('a', 256)], 'device_id'],
    'overlong device name' => [['device_name' => str_repeat('a', 256)], 'device_name'],
]);

it('requires a unique registration email', function (): void {
    Notification::fake();

    User::factory()->create(['email' => 'taken@example.com']);

    $this->postJson('/api/v1/register', validRegistrationPayload(email: 'taken@example.com'))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');
});

it('rejects verifying the registration code from a different device', function (): void {
    Notification::fake();

    $token = $this->postJson('/api/v1/register', validRegistrationPayload(deviceId: 'registration-device'))
        ->assertCreated()
        ->json('data.token');

    $oneTimePassword = OneTimePassword::query()->firstOrFail();
    $oneTimePassword->update(['code_hash' => Hash::make('123456')]);

    $this->withToken($token)
        ->postJson('/api/v1/verify-email', [
            'code' => '123456',
            'device_id' => 'different-device',
        ])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'otp_different_device');

    expect($oneTimePassword->fresh())->not->toBeNull();
});

it('does not leave a user or one-time password behind when token issuance fails', function (): void {
    Notification::fake();

    Str::createRandomStringsUsing(fn (int $length): string => str_repeat('a', $length));
    PersonalAccessToken::factory()->create([
        'token' => hash('sha256', str_repeat('a', 40).'c95b8a25'),
    ]);

    $this->postJson('/api/v1/register', validRegistrationPayload())
        ->assertServerError();

    $this->assertDatabaseMissing('users', ['email' => 'taylor@example.com']);
    expect(OneTimePassword::query()->count())->toBe(0)
        ->and(PersonalAccessToken::query()->count())->toBe(1);
});

it('rejects uppercase registration emails', function (): void {
    Notification::fake();

    $this->postJson('/api/v1/register', validRegistrationPayload(email: 'TEST@EXAMPLE.COM'))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');

    $this->assertDatabaseMissing('users', ['email' => 'test@example.com']);
});

it('forbids registration when a valid bearer token is already present', function (): void {
    Notification::fake();
    $existingUser = User::factory()->create();
    $token = issueApiToken($existingUser);

    $this->withToken($token)
        ->postJson('/api/v1/register', validRegistrationPayload(email: 'new@example.com'))
        ->assertForbidden();

    $this->assertDatabaseMissing('users', ['email' => 'new@example.com']);
    expect(PersonalAccessToken::query()->count())->toBe(1)
        ->and(OneTimePassword::query()->count())->toBe(0);
});

it('may delete the authenticated user and their tokens with the correct password', function (): void {
    $user = User::factory()->create(['password' => 'Password123!']);
    $token = issueApiToken($user);
    issueApiToken($user, 'device-2', 'iPad');

    $this->withToken($token)
        ->deleteJson('/api/v1/user', validDeleteUserPayload())
        ->assertOk()
        ->assertJsonPath('message', 'User deleted successfully');

    $this->assertDatabaseMissing('users', ['id' => $user->id]);
    expect(PersonalAccessToken::query()->where('tokenable_id', $user->id)->count())->toBe(0);
});

it('fails to delete the user with the wrong password', function (): void {
    $user = User::factory()->create(['password' => 'Password123!']);
    $token = issueApiToken($user);

    $this->withToken($token)
        ->deleteJson('/api/v1/user', validDeleteUserPayload(password: 'WrongPassword123!'))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('password');

    $this->assertDatabaseHas('users', ['id' => $user->id]);
    expect(PersonalAccessToken::query()->where('tokenable_id', $user->id)->count())->toBe(1);
});

it('requires a password before deleting the user', function (): void {
    $user = User::factory()->create(['password' => 'Password123!']);
    $token = issueApiToken($user);

    $this->withToken($token)
        ->deleteJson('/api/v1/user', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('password');

    $this->assertDatabaseHas('users', ['id' => $user->id]);
    expect(PersonalAccessToken::query()->where('tokenable_id', $user->id)->count())->toBe(1);
});

it('rejects the deleted token after user deletion', function (): void {
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'password' => 'Password123!',
    ]);
    $token = issueApiToken($user);

    $this->withToken($token)
        ->deleteJson('/api/v1/user', validDeleteUserPayload())
        ->assertOk();

    Auth::forgetGuards();
    Auth::setDefaultDriver('web');

    $this->withToken($token)
        ->putJson('/api/v1/user', [])
        ->assertUnauthorized();
});

it('deletes the users one-time passwords when deleting the user', function (): void {
    $user = User::factory()->create(['password' => 'Password123!']);
    $token = issueApiToken($user);

    OneTimePassword::factory()->for($user)->create([
        'purpose' => OneTimePasswordPurpose::EmailVerification,
    ]);
    OneTimePassword::factory()->for($user)->create([
        'purpose' => OneTimePasswordPurpose::PasswordReset,
    ]);

    $this->withToken($token)
        ->deleteJson('/api/v1/user', validDeleteUserPayload())
        ->assertOk();

    expect(OneTimePassword::query()->where('user_id', $user->id)->count())->toBe(0);
});

function validRegistrationPayload(
    string $email = 'taylor@example.com',
    string $deviceId = 'device-1',
    string $deviceName = 'iPhone',
): array {
    return [
        'name' => 'Taylor Otwell',
        'email' => $email,
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
        'device_id' => $deviceId,
        'device_name' => $deviceName,
    ];
}

function validDeleteUserPayload(string $password = 'Password123!'): array
{
    return [
        'password' => $password,
    ];
}
