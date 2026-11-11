<?php

namespace App\Leitner\Sessions;

use App\Leitner\Models\Session;

/**
 * Résultat du lancement d'une séance : la séance (nouvelle ou reprise), le
 * nombre de questions introduites et les messages à afficher.
 */
final readonly class StartResult
{
    /**
     * @param  list<string>  $notices
     */
    public function __construct(
        public ?Session $session,
        public bool $resumed,
        public int $introduced,
        public array $notices,
    ) {}
}
