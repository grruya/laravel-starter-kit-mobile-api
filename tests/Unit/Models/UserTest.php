<?php

declare(strict_types=1);

use App\Models\User;

it('hides password and remember token when serialized', function (): void {
    $user = User::factory()->create();

    expect($user->toArray())->not->toHaveKeys(['password', 'remember_token']);
});
