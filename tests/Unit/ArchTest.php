<?php

declare(strict_types=1);

arch()->preset()->php();
arch()->preset()->strict();
arch()->preset()->laravel()->ignoring([
    'App\Http\Requests\Concerns',
]);
arch()->preset()->security()->ignoring([
    'assert',
]);

arch('controllers')
    ->expect('App\Http\Controllers')
    ->not->toBeUsed()
    ->toHaveSuffix('Controller');

arch('actions are application layer objects')
    ->expect('App\Actions')
    ->classes()
    ->toHaveMethod('handle')
    ->not->toHavePublicMethodsBesides(['handle', '__construct']);
