<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\CreateAuthTokenRequest;
use App\Http\Resources\AuthenticatedUserResource;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final readonly class AuthTokenController
{
    public function store(CreateAuthTokenRequest $request): AuthenticatedUserResource
    {
        $user = $request->validateCredentials();
        $token = $user->createToken($request->string('device_name')->value())->plainTextToken;

        return new AuthenticatedUserResource($user, $token);
    }

    public function destroy(#[CurrentUser] User $user): JsonResponse
    {
        $user->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully',
        ]);
    }
}
