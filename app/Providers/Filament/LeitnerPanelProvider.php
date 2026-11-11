<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * Panel du module Leitner.
 *
 * Premiers lots sans connexion ni rôles (plan Leitner, étape 0) : le panel n'a
 * ni page de connexion ni middleware d'authentification. Le panel QCM reste le
 * panel par défaut ; un lien de navigation relie les deux panels.
 * Ne pas exposer cette version sur Internet.
 */
class LeitnerPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('leitner')
            ->path('leitner')
            ->brandName('Mentor — Leitner')
            ->databaseTransactions()
            ->colors([
                'primary' => Color::Teal,
            ])
            ->discoverResources(in: app_path('Leitner/Filament/Resources'), for: 'App\Leitner\Filament\Resources')
            ->discoverPages(in: app_path('Leitner/Filament/Pages'), for: 'App\Leitner\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Leitner/Filament/Widgets'), for: 'App\Leitner\Filament\Widgets')
            ->navigationItems([
                NavigationItem::make('qcm')
                    ->label('Module QCM')
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->url(fn (): string => Dashboard::getUrl(panel: 'qcm'))
                    ->sort(100),
            ])
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
