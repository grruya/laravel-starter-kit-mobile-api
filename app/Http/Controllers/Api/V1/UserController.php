<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Auth\CreateUser;
use App\Actions\Auth\IssueAuthToken;
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
    public function store(CreateUserRequest $request, CreateUser $createUser, IssueAuthToken $issueAuthToken): AuthenticatedUserResource
    {
        /** @var array<string, mixed> $attributes */
        $attributes = $request->safe()->only('name', 'email');

        $user = $createUser->handle(
            $attributes,
            $request->string('password')->value(),
        );

        $token = $issueAuthToken->handle(
            $user,
            $request->string('device_name')->value(),
            $request->string('device_id')->value(),
        );

        return new AuthenticatedUserResource($user, $token);
    }

    public function update(UpdateUserRequest $request, #[CurrentUser] User $user, UpdateUser $updateUser): JsonResource
    {
        $user = $updateUser->handle($user, $request->validated());

        return $user->toResource();
    }

    public function destroy(DeleteUserRequest $request, #[CurrentUser] User $user, DeleteUser $deleteUser): JsonResponse
    {
        $user->tokens()->delete();
        $deleteUser->handle($user);

        return response()->json([
            'message' => 'User deleted successfully',
        ]);
    }
}
