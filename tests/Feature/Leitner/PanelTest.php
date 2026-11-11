<?php

namespace Tests\Feature\Leitner;

use Filament\Facades\Filament;
use Tests\TestCase;

/**
 * Étape 0 du plan Leitner : panel sans connexion, à côté du panel QCM.
 */
class PanelTest extends TestCase
{
    public function test_leitner_panel_is_reachable_without_login(): void
    {
        $this->assertGuest();

        $this->get('/leitner')
            ->assertOk()
            ->assertSee('Mentor — Leitner');
    }

    public function test_qcm_panel_remains_the_default_panel(): void
    {
        $this->assertSame('qcm', Filament::getDefaultPanel()->getId());
        $this->get('/')->assertRedirect('/qcm');
    }

    public function test_leitner_panel_links_to_qcm_panel(): void
    {
        $this->get('/leitner')
            ->assertOk()
            ->assertSee('Module QCM')
            ->assertSee(url('/qcm'), escape: false);
    }

    public function test_qcm_panel_links_to_leitner_panel(): void
    {
        $this->get('/qcm')
            ->assertOk()
            ->assertSee('Module Leitner')
            ->assertSee(url('/leitner'), escape: false);
    }

    public function test_leitner_panel_is_in_french(): void
    {
        $this->assertSame('fr', app()->getLocale());

        $this->get('/leitner')
            ->assertOk()
            ->assertSee('lang="fr"', escape: false)
            ->assertSee('Tableau de bord');
    }

    public function test_leitner_defaults_are_configured(): void
    {
        $this->assertSame(5, config('leitner.defaults.box_count'));
        $this->assertSame(10, config('leitner.defaults.min_box_one'));
        $this->assertSame('melange', config('leitner.defaults.presentation_order'));
        $this->assertTrue(config('leitner.defaults.retry_errors'));
        $this->assertSame(7, config('leitner.regularity.window_days'));
        $this->assertSame(3, config('leitner.regularity.min_sessions'));
    }
}
