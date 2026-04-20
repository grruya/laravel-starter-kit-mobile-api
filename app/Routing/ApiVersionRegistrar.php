<?php

declare(strict_types=1);

namespace App\Routing;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;

final class ApiVersionRegistrar
{
    public function register(): void
    {
        $versions = Config::array('api_versioning.versions');

        foreach ($versions as $version => $configuration) {
            if (! is_string($version) || ! is_array($configuration)) {
                continue;
            }

            $this->registerVersion($version, $configuration);
        }
    }

    /**
     * @param  array{routes?: mixed, middleware?: mixed}  $configuration
     */
    private function registerVersion(string $version, array $configuration): void
    {
        $routeFile = $configuration['routes'] ?? null;

        if (! is_string($routeFile) || ! is_file($routeFile)) {
            return;
        }

        $middleware = $configuration['middleware'] ?? [];

        if (! is_array($middleware)) {
            $middleware = [];
        }

        Route::prefix($version)
            ->name("api.{$version}.")
            ->middleware($middleware)
            ->group($routeFile);
    }
}
