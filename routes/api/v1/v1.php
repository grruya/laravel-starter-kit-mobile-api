<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

require __DIR__.'/auth.php';

Route::get('welcome', fn () => response()->json(['message' => 'Welcome to the API']));

Route::middleware(['auth:sanctum', 'verified'])->group(function (): void {
    //
});
