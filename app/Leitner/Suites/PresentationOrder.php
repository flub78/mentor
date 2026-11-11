<?php

namespace App\Leitner\Suites;

use Filament\Support\Contracts\HasLabel;

/**
 * Ordre de présentation des questions d'une séance (PRD Leitner §7.4).
 */
enum PresentationOrder: string implements HasLabel
{
    case Melange = 'melange';
    case BoiteUnDAbord = 'boite_un_d_abord';
    case BoitesHautesDAbord = 'boites_hautes_d_abord';

    public function getLabel(): string
    {
        return match ($this) {
            self::Melange => 'Mélangé',
            self::BoiteUnDAbord => 'Boîte 1 d\'abord',
            self::BoitesHautesDAbord => 'Boîtes hautes d\'abord',
        };
    }
}
