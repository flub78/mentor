<?php

namespace App\Leitner\Sessions;

use App\Leitner\Models\Session;
use Illuminate\Support\Facades\DB;

/**
 * Fin d'une séance (PRD Leitner UC-08, étape 6 et 6a). Une séance sans
 * aucune réponse est supprimée et ne compte pas dans la numérotation.
 */
final class SessionFinisher
{
    /**
     * Termine la séance et renvoie son bilan, ou null si elle n'avait
     * aucune réponse (elle est alors supprimée).
     */
    public function finish(Session $session): ?SessionSummary
    {
        return $this->close($session, SessionStatus::Terminee);
    }

    /**
     * Abandonne une séance interrompue : les réponses déjà données restent
     * acquises, les questions non posées restent dans leur boîte.
     */
    public function abandon(Session $session): ?SessionSummary
    {
        return $this->close($session, SessionStatus::Abandonnee);
    }

    private function close(Session $session, SessionStatus $status): ?SessionSummary
    {
        if (! $session->isInProgress()) {
            throw new SessionException('Cette séance est déjà terminée.');
        }

        return DB::transaction(function () use ($session, $status): ?SessionSummary {
            if (! $session->entries()->whereNotNull('answered_at')->exists()) {
                $progression = $session->progression;

                if ($session->number !== null && $progression->last_session_number === $session->number) {
                    $progression->decrement('last_session_number');
                }

                $session->delete();

                return null;
            }

            $session->update([
                'status' => $status,
                'finished_at' => now(),
                'undo_available' => false,
            ]);

            return SessionSummary::for($session);
        });
    }
}
