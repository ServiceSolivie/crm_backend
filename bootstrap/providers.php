<?php

use App\Providers\AppServiceProvider;
use App\Providers\RepositoryServiceProvider;
use Illuminate\Broadcasting\BroadcastServiceProvider;

return [
    AppServiceProvider::class,
    RepositoryServiceProvider::class,
    BroadcastServiceProvider::class,
];
