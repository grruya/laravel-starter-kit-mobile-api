<?php

declare(strict_types=1);

use App\Models\OneTimePassword;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function (): void {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('model:prune', [
    '--model' => [OneTimePassword::class],
])->everyThirtyMinutes()
    ->withoutOverlapping();
