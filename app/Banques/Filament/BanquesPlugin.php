<?php

namespace App\Banques\Filament;

use App\Banques\Filament\Resources\Banks\BankResource;
use Filament\Contracts\Plugin;
use Filament\Panel;

/**
 * Gestion des banques de questions (PRD Banques UC-01 à UC-05), enregistrée
 * dans les panels des modules qui utilisent les banques : l'utilisateur gère
 * les banques depuis le module où il se trouve (plan Banques §3).
 */
class BanquesPlugin implements Plugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'banques';
    }

    public function register(Panel $panel): void
    {
        $panel->resources([
            BankResource::class,
        ]);
    }

    public function boot(Panel $panel): void {}
}
