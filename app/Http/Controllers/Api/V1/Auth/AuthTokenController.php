<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\Auth\IssueAuthToken;
use App\Http\Requests\Auth\CreateAuthTokenRequest;
use App\Http\Resources\AuthenticatedUserResource;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final readonly class AuthTokenController
{
    public function store(CreateAuthTokenRequest $request, IssueAuthToken $issueAuthToken): AuthenticatedUserResource
    {
        $user = $request->validateCredentials();
        $token = $issueAuthToken->handle(
            $user,
            $request->string('device_name')->value(),
            $request->string('device_id')->value(),
        );

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
