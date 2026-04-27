<?php

declare(strict_types=1);

use App\Models\OneTimePassword;

it('prunes expired OTPs only', function (): void {
    $expired = OneTimePassword::factory()->create([
        'expires_at' => now()->subSecond(),
    ]);
    $unexpired = OneTimePassword::factory()->create([
        'expires_at' => now()->addSecond(),
    ]);

    $prunableIds = (new OneTimePassword)->prunable()->pluck('id')->all();

    expect($prunableIds)->toContain($expired->id)
        ->and($prunableIds)->not->toContain($unexpired->id);
});
