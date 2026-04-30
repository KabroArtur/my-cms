<?php

use App\Core\Support\CoreServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\PluginServiceProvider;

return [
    CoreServiceProvider::class,
    AppServiceProvider::class,
    PluginServiceProvider::class,
];
