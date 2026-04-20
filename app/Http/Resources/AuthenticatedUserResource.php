<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class AuthenticatedUserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function __construct(User $resource, private readonly string $token)
    {
        parent::__construct($resource);
    }

    /**
     * @return array{user: JsonResource, token: string}
     */
    public function toArray(Request $request): array
    {
        /** @var User $user */
        $user = $this->resource;

        return [
            'user' => $user->toResource(),
            'token' => $this->token,
        ];
    }
}
