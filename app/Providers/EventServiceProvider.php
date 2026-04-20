<?php

declare(strict_types=1);

namespace App\Providers;

use App\Listeners\SendEmailVerificationCodeNotification;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

final class EventServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->app->booted(function (): void {
            Event::forget(Registered::class);
            Event::listen(Registered::class, SendEmailVerificationCodeNotification::class);
        });
    }
}
