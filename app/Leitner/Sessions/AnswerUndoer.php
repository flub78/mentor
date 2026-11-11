<?php

namespace App\Leitner\Sessions;

use App\Leitner\Models\ProgressItem;
use App\Leitner\Models\Session;
use App\Leitner\Models\SessionEntry;
use Illuminate\Support\Facades\DB;

/**
 * Annulation de la dernière réponse (PRD Leitner UC-08, 4b) : la boîte et
 * les échecs consécutifs sont restaurés, la reprise ajoutée est retirée.
 * Une seule annulation, juste après la réponse.
 */
final class AnswerUndoer
{
    /**
     * @return SessionEntry l'entrée rouverte, à reposer
     *
     * @throws SessionException
     */
    public function undo(Session $session): SessionEntry
    {
        if (! $session->isInProgress()) {
            throw new SessionException('La séance est terminée : sa dernière réponse ne peut plus être annulée.');
        }

        $last = $session->entries()->reorder()->whereNotNull('answered_at')->orderByDesc('answered_at')->orderByDesc('id')->first();

        if (! $session->undo_available || $last === null) {
            throw new SessionException('Seule la dernière réponse peut être annulée, une seule fois, avant de répondre à la question suivante.');
        }

        return DB::transaction(function () use ($session, $last): SessionEntry {
            if (! $last->is_retry && $last->box_before !== null) {
                ProgressItem::query()
                    ->where('progression_id', $session->progression_id)
                    ->where('question_id', $last->question_id)
                    ->first()
                    ?->update([
                        'box' => $last->box_before,
                        'mastered_at' => null,
                        'consecutive_failures' => (int) $last->failures_before,
                    ]);
            }

            $session->entries()->where('retry_of_id', $last->id)->delete();

            $last->update([
                'response' => null,
                'verdict' => null,
                'detail' => null,
                'box_after' => null,
                'mastered' => false,
                'failures_before' => null,
                'answered_at' => null,
                'duration_ms' => null,
            ]);

            $session->update(['undo_available' => false]);

            return $last;
        });
    }
}
