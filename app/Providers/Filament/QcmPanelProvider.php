<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * Panel du module QCM.
 *
 * MVP sans connexion ni rôles (PRD QCM §1.3) : le panel n'a ni page de
 * connexion ni middleware d'authentification. Il est le panel par défaut ;
 * le panel d'administration de laraskel-users reste protégé par la connexion.
 * Ne pas exposer cette version sur Internet.
 */
class QcmPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('qcm')
            ->path('qcm')
            ->default()
            ->brandName('Mentor — QCM')
            ->databaseTransactions()
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverResources(in: app_path('Qcm/Filament/Resources'), for: 'App\Qcm\Filament\Resources')
            ->discoverPages(in: app_path('Qcm/Filament/Pages'), for: 'App\Qcm\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Qcm/Filament/Widgets'), for: 'App\Qcm\Filament\Widgets')
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ]);
    }
}
