<?php

declare(strict_types=1);

/**
 * For specific API version routes, see the `routes/api/v{number}.php` file.
 */

use App\Routing\ApiVersionRegistrar;

new ApiVersionRegistrar()->register();
