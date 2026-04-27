<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\Auth\RegisterUser;
use App\Actions\DeleteUser;
use App\Actions\UpdateUser;
use App\Http\Requests\Auth\CreateUserRequest;
use App\Http\Requests\DeleteUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\AuthenticatedUserResource;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;

final readonly class UserController
{
    public function store(CreateUserRequest $request, RegisterUser $registerUser): AuthenticatedUserResource
    {
        /** @var array<string, mixed> $attributes */
        $attributes = $request->safe()->except('device_id', 'device_name', 'password');

        $registration = $registerUser->handle(
            $attributes,
            $request->string('password')->value(),
            $request->string('device_name')->value(),
            $request->string('device_id')->value(),
        );

        return new AuthenticatedUserResource($registration['user'], $registration['token']);
    }

    public function update(UpdateUserRequest $request, UpdateUser $updateUser, #[CurrentUser] User $user): JsonResource
    {
        /** @var array<string, mixed> $attributes */
        $attributes = $request->safe()->except('device_id');

        $user = $updateUser->handle(
            $user,
            $attributes,
            $request->string('device_id')->value(),
        );

        return $user->toResource();
    }

    public function destroy(DeleteUserRequest $request, DeleteUser $deleteUser, #[CurrentUser] User $user): JsonResponse
    {
        $deleteUser->handle($user);

        return response()->json([
            'message' => 'User deleted successfully',
        ]);
    }
}
