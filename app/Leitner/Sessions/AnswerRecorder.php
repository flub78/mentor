<?php

namespace App\Leitner\Sessions;

use App\Leitner\Engine\BoxMover;
use App\Leitner\Models\ProgressItem;
use App\Leitner\Models\Session;
use App\Leitner\Models\SessionEntry;
use App\Leitner\Questions\InvalidResponse;
use App\Leitner\Questions\Verdict;
use Illuminate\Support\Facades\DB;

/**
 * Enregistrement d'une réponse (PRD Leitner UC-08 étape 4, §7.2) : verdict,
 * numéro de séance à la première réponse, mouvement de boîte pour une
 * première réponse, reprise en fin de séance d'une question échouée.
 */
final class AnswerRecorder
{
    /**
     * @throws InvalidResponse si la réponse ne peut pas être corrigée (rien n'est enregistré)
     * @throws SessionException si la réponse est refusée
     */
    public function record(Session $session, SessionEntry $entry, mixed $response, ?int $durationMs = null): SessionEntry
    {
        SessionGuard::assertAnswerable($session, $entry);

        $question = $entry->question;

        if ($question === null) {
            throw new SessionException('Cette question a été supprimée pendant la séance : passez à la suivante.');
        }

        $evaluation = $question->evaluate($response);

        return DB::transaction(function () use ($session, $entry, $response, $durationMs, $evaluation): SessionEntry {
            $progression = $session->progression;
            $suite = $progression->suite;

            if ($session->number === null) {
                $progression->increment('last_session_number');
                $session->number = $progression->last_session_number;
            }

            $attributes = [
                'response' => $response,
                'verdict' => $evaluation->verdict,
                'detail' => $evaluation->detail,
                'answered_at' => now(),
                'duration_ms' => $durationMs,
            ];

            $item = $entry->is_retry ? null : ProgressItem::query()
                ->where('progression_id', $progression->id)
                ->where('question_id', $entry->question_id)
                ->first();

            if ($item !== null && $item->box !== null && $item->mastered_at === null) {
                $move = BoxMover::move($item->box, $item->consecutive_failures, $evaluation->verdict, $suite->box_count);
                $attributes['box_before'] = $item->box;
                $attributes['failures_before'] = $item->consecutive_failures;
                $attributes['box_after'] = $move->box;
                $attributes['mastered'] = $move->mastered;

                $item->update([
                    'box' => $move->mastered ? $item->box : $move->box,
                    'mastered_at' => $move->mastered ? now() : null,
                    'consecutive_failures' => $move->consecutiveFailures,
                ]);
            }

            $entry->update($attributes);

            if ($evaluation->verdict === Verdict::Fausse && $suite->retry_errors) {
                $session->entries()->create([
                    'question_id' => $entry->question_id,
                    'retry_of_id' => $entry->id,
                    'position' => (int) $session->entries()->max('position') + 1,
                    'is_retry' => true,
                ]);
            }

            $session->undo_available = true;
            $session->save();

            return $entry;
        });
    }
}
