<?php

declare(strict_types=1);

use App\Exceptions\OneTimePasswordException;
use Illuminate\Http\Request;

it('renders cooldown exceptions as API JSON with retry headers', function (): void {
    $response = OneTimePasswordException::cooldown(30)->render(Request::create('/api/v1/email-verification-code', 'POST'));

    expect($response->getStatusCode())->toBe(429)
        ->and($response->headers->get('Retry-After'))->toBe('30')
        ->and($response->getData(true))->toBe([
            'message' => 'Please wait 30 seconds before requesting another code.',
            'code' => 'otp_cooldown',
            'retry_after' => 30,
        ]);
});

it('renders validation-style OTP failures as API JSON', function (OneTimePasswordException $exception, string $code, string $message): void {
    $response = $exception->render(Request::create('/api/v1/verify-email', 'POST'));

    expect($response->getStatusCode())->toBe(422)
        ->and($response->getData(true))->toBe([
            'message' => 'The given data was invalid.',
            'code' => $code,
            'errors' => [
                'code' => [$message],
            ],
        ]);
})->with([
    'invalid' => [OneTimePasswordException::invalid(), 'otp_invalid', 'The provided code is invalid.'],
    'expired' => [OneTimePasswordException::expired(), 'otp_expired', 'The provided code has expired.'],
    'different device' => [OneTimePasswordException::differentDevice(), 'otp_different_device', 'The provided code was requested from a different device.'],
]);

it('does not render non-api non-json requests', function (): void {
    expect(OneTimePasswordException::invalid()->render(Request::create('/profile', 'POST')))->toBeFalse();
});
