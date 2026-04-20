<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ApiNotFoundException extends Exception
{
    public static function from(NotFoundHttpException $exception): self
    {
        return new self(
            message: $exception->getMessage(),
            code: $exception->getCode(),
            previous: $exception,
        );
    }

    public function render(Request $request): JsonResponse|bool
    {
        if (! $request->is('api/*') && ! $request->expectsJson()) {
            return false;
        }

        return response()->json([
            'message' => 'Resource you are looking for was not found.',
        ], 404);
    }
}
