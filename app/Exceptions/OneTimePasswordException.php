<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class OneTimePasswordException extends Exception implements ShouldntReport
{
    /**
     * @param  array<string, string>  $headers
     */
    private function __construct(
        string $message,
        public readonly string $errorCode,
        public readonly int $status,
        public readonly array $headers = [],
        public readonly ?int $retryAfterInSeconds = null,
    ) {
        parent::__construct($message);
    }

    public static function cooldown(int $retryAfterInSeconds): self
    {
        return new self(
            sprintf('Please wait %d seconds before requesting another code.', $retryAfterInSeconds),
            'otp_cooldown',
            429,
            ['Retry-After' => (string) $retryAfterInSeconds],
            $retryAfterInSeconds,
        );
    }

    public static function differentDevice(): self
    {
        return new self(
            'The provided code was requested from a different device.',
            'otp_different_device',
            422,
        );
    }

    public static function expired(): self
    {
        return new self(
            'The provided code has expired.',
            'otp_expired',
            422,
        );
    }

    public static function invalid(): self
    {
        return new self(
            'The provided code is invalid.',
            'otp_invalid',
            422,
        );
    }

    public function isCooldown(): bool
    {
        return $this->errorCode === 'otp_cooldown';
    }

    public function render(Request $request): JsonResponse|bool
    {
        if (! $request->is('api/*') && ! $request->expectsJson()) {
            return false;
        }

        if ($this->isCooldown()) {
            return response()->json([
                'message' => $this->getMessage(),
                'code' => $this->errorCode,
                'retry_after' => $this->retryAfterInSeconds,
            ], $this->status, $this->headers);
        }

        return response()->json([
            'message' => 'The given data was invalid.',
            'code' => $this->errorCode,
            'errors' => [
                'code' => [$this->getMessage()],
            ],
        ], $this->status, $this->headers);
    }
}
