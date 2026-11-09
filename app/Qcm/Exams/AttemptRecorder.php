<?php

namespace App\Qcm\Exams;

use App\Qcm\Models\Attempt;
use App\Qcm\Models\AttemptItem;

/**
 * Réponses et marque-pages pendant un passage d'examen (PRD QCM UC-12,
 * étapes 4 à 6). Toute action sur un passage terminé est refusée ; un
 * passage dont l'échéance est atteinte est d'abord terminé.
 */
final class AttemptRecorder
{
    public function __construct(private readonly AttemptFinisher $finisher = new AttemptFinisher) {}

    /**
     * Enregistre la réponse choisie ; une nouvelle réponse remplace la
     * précédente (UC-12, 4a).
     *
     * @throws AttemptException passage terminé ou échu, réponse non proposée
     */
    public function answer(AttemptItem $item, int $answerId): void
    {
        $this->ensureOpen($item->attempt);

        if (! in_array($answerId, array_map(intval(...), $item->answer_order), true)) {
            throw AttemptException::unknownAnswer($item->position);
        }

        $item->update(['chosen_answer_id' => $answerId, 'answered_at' => now()]);
    }

    /**
     * Pose ou retire le marque-page de la question (UC-12, 6).
     *
     * @throws AttemptException passage terminé ou échu
     */
    public function bookmark(AttemptItem $item, bool $bookmarked): void
    {
        $this->ensureOpen($item->attempt);

        $item->update(['bookmarked' => $bookmarked]);
    }

    /**
     * @throws AttemptException
     */
    private function ensureOpen(Attempt $attempt): void
    {
        $attempt->refresh();

        if ($this->finisher->closeIfOverdue($attempt)) {
            throw AttemptException::timeUp();
        }

        if (! $attempt->isInProgress()) {
            throw AttemptException::closed($attempt);
        }
    }
}
