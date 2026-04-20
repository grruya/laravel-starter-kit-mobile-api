<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\CreateUser;
use App\Actions\DeleteUser;
use App\Actions\UpdateUser;
use App\Http\Requests\CreateUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\AuthenticatedUserResource;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final readonly class UserController
{
    public function store(CreateUserRequest $request, CreateUser $createUser): AuthenticatedUserResource
    {
        /** @var array<string, mixed> $attributes */
        $attributes = $request->safe()->except('password', 'device_name');

        $user = $createUser->handle(
            $attributes,
            $request->string('password')->value(),
        );

        $token = $user->createToken($request->string('device_name')->value())->plainTextToken;

        return new AuthenticatedUserResource($user, $token);
    }

    public function update(UpdateUserRequest $request, #[CurrentUser] User $user, UpdateUser $updateUser): JsonResource
    {
        $user = $updateUser->handle($user, $request->validated());

        return $user->toResource();
    }

    public function destroy(Request $request, #[CurrentUser] User $user, DeleteUser $deleteUser): JsonResponse
    {
        $request->validate(['password' => ['required', 'current_password:sanctum']]);

        $user->currentAccessToken()->delete();
        $deleteUser->handle($user);

        return response()->json([
            'message' => 'User deleted successfully',
        ]);
    }
}
