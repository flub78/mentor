<?php

namespace App\Providers;

use App\Banques\Usage\UsageRegistry;
use App\Leitner\Suites\LeitnerQuestionUsage;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Utilisations des questions déclarées au module Banques (PRD Banques §3).
        $this->app->tag([LeitnerQuestionUsage::class], UsageRegistry::TAG);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
