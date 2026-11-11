<?php

namespace App\Leitner\Sessions;

use App\Leitner\Models\Session;
use App\Leitner\Models\SessionEntry;

/**
 * Contrôles communs aux actions sur une entrée de séance.
 */
final class SessionGuard
{
    /**
     * @throws SessionException
     */
    public static function assertAnswerable(Session $session, SessionEntry $entry): void
    {
        if ($entry->session_id !== $session->id) {
            throw new SessionException('Cette question n\'appartient pas à la séance en cours : rechargez la page.');
        }

        if (! $session->isInProgress()) {
            throw new SessionException('La séance est terminée : lancez une nouvelle séance pour continuer.');
        }

        if ($entry->isDone()) {
            throw new SessionException('Cette question a déjà été traitée dans la séance : passez à la suivante.');
        }
    }
}
