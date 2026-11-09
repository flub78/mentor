<?php

namespace App\Qcm\Exams;

use App\Qcm\Models\Attempt;
use App\Qcm\Models\Exam;
use RuntimeException;

/**
 * Action refusée sur un passage d'examen (PRD QCM UC-12) ; le message
 * s'adresse au candidat et dit quoi faire.
 */
final class AttemptException extends RuntimeException
{
    public static function emptyQuestionnaire(Exam $exam): self
    {
        return new self(sprintf(
            'L\'examen « %s » ne peut pas commencer : son questionnaire « %s » ne contient aucune question. Le formateur doit y ajouter des questions.',
            $exam->title,
            $exam->questionnaire->title,
        ));
    }

    public static function maxAttemptsReached(Exam $exam, int $count): self
    {
        return new self(sprintf(
            'Vous avez déjà passé l\'examen « %s » %d fois, le nombre maximal de passages autorisé (%d). Adressez-vous au formateur.',
            $exam->title,
            $count,
            (int) $exam->max_attempts,
        ));
    }

    public static function closed(Attempt $attempt): self
    {
        return new self(sprintf(
            'Ce passage est terminé depuis le %s : les réponses ne peuvent plus être modifiées.',
            $attempt->finished_at?->format('d/m/Y à H:i') ?? '(date inconnue)',
        ));
    }

    public static function timeUp(): self
    {
        return new self('Le temps imparti est écoulé : le passage a été terminé avec les réponses déjà données.');
    }

    public static function unknownAnswer(int $position): self
    {
        return new self(sprintf(
            'La réponse choisie n\'est pas l\'une de celles proposées à la question %d. Rechargez la page et choisissez à nouveau.',
            $position + 1,
        ));
    }
}
