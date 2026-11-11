<?php

namespace App\Leitner\Engine;

/**
 * Résultat d'un mouvement entre boîtes : nouvelle boîte (nulle si la
 * question est maîtrisée) et nombre d'échecs consécutifs.
 */
final readonly class Move
{
    public function __construct(
        public ?int $box,
        public bool $mastered,
        public int $consecutiveFailures,
    ) {}
}
