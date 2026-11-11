<?php

namespace App\Qcm\Migration;

use App\Banques\Models\Bank;
use App\Banques\Models\Question;
use App\Banques\Questions\InvalidQuestion;
use App\Banques\Questions\QuestionType;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Reprise des banques et des questions du module QCM dans le module Banques
 * de questions (plan Banques, étape B2), appelée par la migration de bascule
 * du module QCM :
 *
 * - chaque banque QCM devient une banque commune (nom suffixé « (QCM) » s'il
 *   est déjà pris) ;
 * - chaque question devient une question de type QCM, dans la banque reprise
 *   ou, pour une question propre à un questionnaire, dans la banque par
 *   défaut ; réponses, explications, module (devenu thème), image et dates
 *   sont conservés ;
 * - les passages en cours ne sont pas convertis : la reprise est refusée
 *   tant qu'il en existe.
 *
 * Lit les tables `qcm_*` sans les modifier ; renvoie la correspondance des
 * identifiants de questions, qui sert à réécrire les liens des questionnaires.
 */
final class QcmToBanquesTransfer
{
    /** Longueur de l'énoncé reprise dans la description d'une image. */
    private const ALT_LENGTH = 80;

    /**
     * @return array<int, int> identifiant de question QCM → identifiant de question commune
     *
     * @throws RuntimeException si un passage est en cours ou si une question est incomplète
     */
    public function run(): array
    {
        $inProgress = DB::table('qcm_attempts')->where('status', 'in_progress')->count();

        if ($inProgress > 0) {
            throw new RuntimeException("{$inProgress} passage(s) d'examen en cours : terminez-les (ou laissez leur temps s'écouler) avant la reprise des questions QCM dans le module Banques, qui ne convertit pas les passages en cours.");
        }

        return DB::transaction(function (): array {
            $bankMap = $this->copyBanks();
            $defaultBankId = Bank::default()->id;
            $questionMap = [];

            foreach (DB::table('qcm_questions')->orderBy('id')->get() as $row) {
                $bankId = $row->bank_id === null ? $defaultBankId : $bankMap[$row->bank_id];
                $questionMap[(int) $row->id] = $this->copyQuestion($row, $bankId);
            }

            return $questionMap;
        });
    }

    /**
     * @return array<int, int> identifiant de banque QCM → identifiant de banque commune
     */
    private function copyBanks(): array
    {
        $map = [];

        foreach (DB::table('qcm_banks')->orderBy('id')->get() as $row) {
            $bank = Bank::query()->create([
                'name' => $this->freeName((string) $row->name),
                'description' => $row->description,
            ]);
            $this->keepTrace('banques_banks', $bank->id, $row);
            $map[(int) $row->id] = $bank->id;
        }

        return $map;
    }

    private function copyQuestion(object $row, int $bankId): int
    {
        $answers = DB::table('qcm_answers')->where('question_id', $row->id)->orderBy('position')->get();
        $correct = $answers->firstWhere('is_correct', true);

        try {
            $question = Question::query()->create([
                'bank_id' => $bankId,
                'type' => QuestionType::Qcm,
                'statement' => $row->statement,
                'payload' => [
                    'correct' => $correct?->text,
                    'wrong' => $answers->where('is_correct', false)->values()->map(fn (object $answer): array => [
                        'text' => $answer->text,
                        'explanation' => $answer->explanation,
                    ])->all(),
                    'shuffle' => true,
                ],
                'explanation' => $row->explanation,
                'theme' => $row->module,
                'image_path' => $row->image_path,
                'image_alt' => $row->image_path === null ? null : $this->imageAlt((string) $row->statement),
            ]);
        } catch (InvalidQuestion $exception) {
            throw new RuntimeException("Question QCM n° {$row->id} (« ".mb_strimwidth((string) $row->statement, 0, 60, '…')." ») non reprise : {$exception->getMessage()} Corrigez-la dans le module QCM, puis relancez la migration.", 0, $exception);
        }

        $this->keepTrace('banques_questions', $question->id, $row);

        return $question->id;
    }

    /**
     * Conserve les dates et les auteurs d'origine.
     */
    private function keepTrace(string $table, int $id, object $row): void
    {
        DB::table($table)->where('id', $id)->update([
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
            'created_by' => $row->created_by,
            'updated_by' => $row->updated_by,
        ]);
    }

    private function freeName(string $name): string
    {
        $candidate = $name;

        for ($suffix = 1; Bank::query()->where('name', $candidate)->exists(); $suffix++) {
            $candidate = $suffix === 1 ? "{$name} (QCM)" : "{$name} (QCM {$suffix})";
        }

        return $candidate;
    }

    /**
     * Description par défaut d'une image reprise (PRD Banques §8.1).
     */
    private function imageAlt(string $statement): string
    {
        return 'Illustration : '.mb_strimwidth(trim($statement), 0, self::ALT_LENGTH, '…');
    }
}
