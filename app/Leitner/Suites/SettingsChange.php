<?php

namespace App\Leitner\Suites;

/**
 * Effets d'une modification des paramètres d'une suite sur la progression
 * (PRD Leitner UC-05, 3a), annoncés avant confirmation.
 */
final readonly class SettingsChange
{
    /**
     * @param  list<int>  $removedBankIds  banques retirées des sources
     * @param  int  $lostItems  questions introduites qui perdent leur progression
     * @param  int  $movedItems  questions placées dans la nouvelle dernière boîte
     */
    public function __construct(
        public array $removedBankIds,
        public int $lostItems,
        public int $movedItems,
        public int $newLastBox,
    ) {}

    public function isDestructive(): bool
    {
        return $this->lostItems > 0 || $this->movedItems > 0;
    }

    /**
     * Phrases à afficher dans la confirmation.
     *
     * @return list<string>
     */
    public function describe(): array
    {
        $lines = [];

        if ($this->lostItems > 0) {
            $lines[] = $this->lostItems === 1
                ? 'Retirer une banque fait perdre sa progression à 1 question déjà introduite (elle disparaît des boîtes).'
                : "Retirer une banque fait perdre leur progression à {$this->lostItems} questions déjà introduites (elles disparaissent des boîtes).";
        }

        if ($this->movedItems > 0) {
            $lines[] = $this->movedItems === 1
                ? "Réduire le nombre de boîtes place 1 question dans la boîte {$this->newLastBox}."
                : "Réduire le nombre de boîtes place {$this->movedItems} questions dans la boîte {$this->newLastBox}.";
        }

        return $lines;
    }
}
