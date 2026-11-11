<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\LeitnerPanelProvider;
use App\Providers\Filament\QcmPanelProvider;

return [
    AppServiceProvider::class,
    QcmPanelProvider::class,
    LeitnerPanelProvider::class,
];
