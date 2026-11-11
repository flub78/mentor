<?php

namespace App\Qcm\Study;

use App\Qcm\Models\Question;
use Random\Randomizer;

/**
 * Tirage au hasard des questions d'une session d'étude (PRD QCM UC-11).
 */
final class StudyDraw
{
    public function __construct(private readonly Randomizer $randomizer = new Randomizer) {}

    /**
     * Tire, pour chaque module, le nombre de questions demandé, sans doublon,
     * puis mélange l'ensemble.
     *
     * @param  list<int>  $bankIds
     * @param  array<string, int|null>  $requested  nombre demandé par clé de module ; null = toutes
     * @param  list<int>  $excludedQuestionIds  questions à ne pas tirer (déjà présentes dans un questionnaire)
     * @return array{questionIds: list<int>, reduced: array<string, int>} questions tirées, et modules
     *                                                                    dont la demande dépassait le disponible (clé => nombre retenu)
     */
    public function draw(array $bankIds, array $requested, array $excludedQuestionIds = []): array
    {
        $byModule = [];
        $questions = Question::query()
            ->whereIn('bank_id', $bankIds)
            ->when($excludedQuestionIds !== [], fn ($query) => $query->whereKeyNot($excludedQuestionIds))
            ->get(['id', 'type', 'theme']);

        foreach ($questions as $question) {
            $byModule[ModuleCatalog::key($question->module)][] = $question->id;
        }

        $drawn = [];
        $reduced = [];

        foreach ($requested as $key => $count) {
            $available = $byModule[(string) $key] ?? [];

            if ($count !== null && $count <= 0) {
                continue;
            }

            if ($count !== null && $count > count($available)) {
                $reduced[(string) $key] = count($available);
            }

            $take = $count === null ? count($available) : min($count, count($available));

            if ($take === 0) {
                continue;
            }

            $picked = $this->randomizer->pickArrayKeys($available, $take);

            foreach ($picked as $index) {
                $drawn[] = $available[$index];
            }
        }

        return [
            'questionIds' => $this->randomizer->shuffleArray($drawn),
            'reduced' => $reduced,
        ];
    }

    /**
     * Ordre d'affichage mélangé des réponses d'une question.
     *
     * @param  list<string>  $answerKeys
     * @return list<string>
     */
    public function shuffleAnswers(array $answerKeys): array
    {
        return $this->randomizer->shuffleArray($answerKeys);
    }
}
