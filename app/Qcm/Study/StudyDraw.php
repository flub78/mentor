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
     * @return array{questionIds: list<int>, reduced: array<string, int>} questions tirées, et modules
     *                                                                    dont la demande dépassait le disponible (clé => nombre retenu)
     */
    public function draw(array $bankIds, array $requested): array
    {
        $byModule = [];

        foreach (Question::query()->whereIn('bank_id', $bankIds)->get(['id', 'module']) as $question) {
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
     * @param  list<int>  $answerIds
     * @return list<int>
     */
    public function shuffleAnswers(array $answerIds): array
    {
        return $this->randomizer->shuffleArray($answerIds);
    }
}
