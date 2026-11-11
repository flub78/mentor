<?php

namespace App\Qcm\Models;

/**
 * Réponse proposée pour une question QCM (PRD QCM §3), calculée depuis la
 * réponse attendue de la question dans le module Banques. Elle n'est pas
 * enregistrée : une clé stable l'identifie (`bonne`, `fausse-0`… pour un QCM,
 * `vrai` et `faux` pour un vrai / faux).
 */
final readonly class Answer
{
    public function __construct(
        public string $key,
        public string $text,
        public bool $is_correct,
        public ?string $explanation,
        public int $position,
    ) {}
}
