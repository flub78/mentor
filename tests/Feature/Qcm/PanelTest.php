<?php

namespace Tests\Feature\Qcm;

use Filament\Facades\Filament;
use Tests\TestCase;

/**
 * Étape 0 du plan QCM : panel sans connexion (PRD QCM §1.3).
 */
class PanelTest extends TestCase
{
    public function test_root_redirects_to_qcm_panel(): void
    {
        $this->get('/')->assertRedirect('/qcm');
    }

    public function test_qcm_panel_is_reachable_without_login(): void
    {
        $this->assertGuest();

        $this->get('/qcm')
            ->assertOk()
            ->assertSee('Mentor — QCM');
    }

    public function test_qcm_panel_is_the_default_panel(): void
    {
        $this->assertSame('qcm', Filament::getDefaultPanel()->getId());
    }

    public function test_admin_panel_still_requires_login(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_application_is_in_french(): void
    {
        $this->assertSame('fr', app()->getLocale());
    }
}
