<?php

declare(strict_types=1);

it('returns the custom json not found response for unknown api routes', function (): void {
    $this->getJson('/api/v1/missing-route')
        ->assertNotFound()
        ->assertJsonPath('message', 'Resource you are looking for was not found.');
});

it('returns json for unsupported methods on known api routes', function (): void {
    $response = $this->getJson('/api/v1/logout')
        ->assertMethodNotAllowed();

    expect($response->headers->get('content-type'))->toContain('application/json');
});

it('returns json validation errors instead of redirects for api routes', function (): void {
    $this->postJson('/api/v1/register')
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'name',
            'email',
            'password',
            'device_id',
            'device_name',
        ])
        ->assertJsonMissingPath('errors.password_confirmation');
});

it('rejects protected routes when a bearer token is missing', function (string $method, string $uri, array $payload): void {
    $this->json($method, $uri, $payload)
        ->assertUnauthorized()
        ->assertJsonPath('message', 'Unauthenticated.');
})->with('protected routes');

it('rejects protected routes when a bearer token is invalid', function (string $method, string $uri, array $payload): void {
    $this->withToken('invalid-token')
        ->json($method, $uri, $payload)
        ->assertUnauthorized()
        ->assertJsonPath('message', 'Unauthenticated.');
})->with('protected routes');

dataset('protected routes', [
    'logout' => ['POST', '/api/v1/logout', []],
    'email verification code' => ['POST', '/api/v1/email-verification-code', ['device_id' => 'device-1']],
    'verify email' => ['POST', '/api/v1/verify-email', ['code' => '123456', 'device_id' => 'device-1']],
    'update password' => ['PUT', '/api/v1/update-password', [
        'current_password' => 'password',
        'password' => 'NewPassword123!',
        'password_confirmation' => 'NewPassword123!',
    ]],
    'update user' => ['PUT', '/api/v1/user', ['name' => 'Taylor Otwell', 'email' => 'taylor@example.com']],
    'delete user' => ['DELETE', '/api/v1/user', ['password' => 'password']],
]);
