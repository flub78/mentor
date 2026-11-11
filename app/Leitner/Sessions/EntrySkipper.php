<?php

namespace App\Leitner\Sessions;

use App\Leitner\Models\Session;
use App\Leitner\Models\SessionEntry;

/**
 * Question passée (PRD Leitner UC-08, 4a) : elle garde sa boîte et sera
 * reposée à une prochaine séance.
 */
final class EntrySkipper
{
    /**
     * @throws SessionException
     */
    public function skip(Session $session, SessionEntry $entry): void
    {
        SessionGuard::assertAnswerable($session, $entry);

        $entry->update(['skipped_at' => now()]);
        $session->update(['undo_available' => false]);
    }

    /**
     * Passe les entrées dont la question a été supprimée pendant la séance.
     *
     * @return int nombre d'entrées passées
     */
    public function skipDeletedQuestions(Session $session): int
    {
        return $session->entries()
            ->whereNull('question_id')
            ->whereNull('answered_at')
            ->whereNull('skipped_at')
            ->update(['skipped_at' => now()]);
    }
}
