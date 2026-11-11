<?php

namespace App\Qcm\Questionnaires;

use App\Qcm\Models\Question;
use App\Qcm\Models\Questionnaire;
use App\Qcm\Study\StudyDraw;
use Illuminate\Support\Facades\DB;

/**
 * Composition d'un questionnaire (PRD QCM UC-08) : ajout de questions des
 * banques, choisies ou tirées au hasard, et duplication. Seules les questions
 * QCM et vrai / faux sont utilisées (UsableQuestions).
 */
final class QuestionnaireComposer
{
    public function __construct(private readonly StudyDraw $draw = new StudyDraw) {}

    /**
     * Ajoute à la fin de la liste les questions de banque données, sauf celles
     * déjà présentes.
     *
     * @param  list<int>  $questionIds
     * @return int nombre de questions ajoutées
     */
    public function addBankQuestions(Questionnaire $questionnaire, array $questionIds): int
    {
        $present = $questionnaire->questions()->pluck('banques_questions.id')->all();
        $ids = Question::query()
            ->whereKey($questionIds)
            ->whereKeyNot($present)
            ->pluck('id')
            ->all();

        // Ordre de la demande conservé.
        $ids = array_values(array_filter($questionIds, fn (int $id): bool => in_array($id, $ids, true)));

        DB::transaction(function () use ($questionnaire, $ids): void {
            foreach (array_unique($ids) as $id) {
                $questionnaire->questions()->attach($id);
            }
        });

        return count(array_unique($ids));
    }

    /**
     * Tire au hasard des questions de banque par module et les ajoute
     * (PRD QCM UC-08, étape 2 et variantes 1a, 2b, 2c).
     *
     * @param  list<int>  $bankIds
     * @param  array<string, int|null>  $requested  nombre par clé de module ; null = toutes
     * @return array{added: int, reduced: array<string, int>} questions ajoutées, et modules
     *                                                        ramenés au disponible (clé => nombre tiré)
     */
    public function addDrawnQuestions(Questionnaire $questionnaire, array $bankIds, array $requested): array
    {
        $present = $questionnaire->questions()->pluck('banques_questions.id')->all();
        $result = $this->draw->draw($bankIds, $requested, $present);

        return [
            'added' => $this->addBankQuestions($questionnaire, $result['questionIds']),
            'reduced' => $result['reduced'],
        ];
    }

    /**
     * Copie du questionnaire : mêmes questions, partagées avec leurs banques,
     * même ordre (PRD QCM UC-08, 3a).
     */
    public function duplicate(Questionnaire $questionnaire): Questionnaire
    {
        return DB::transaction(function () use ($questionnaire): Questionnaire {
            // Champs enregistrés seulement : le modèle peut porter des compteurs
            // calculés par une liste (questions_count…).
            $copy = Questionnaire::create([
                ...$questionnaire->only($questionnaire->getFillable()),
                'title' => $questionnaire->title.' (copie)',
            ]);

            foreach ($questionnaire->questions()->pluck('banques_questions.id') as $id) {
                $copy->questions()->attach($id);
            }

            return $copy;
        });
    }
}
