<?php

namespace App\Banques\Questions;

use App\Banques\Models\Bank;
use App\Banques\Models\Question;
use App\Banques\Usage\UsageRegistry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Déplacement et copie de questions d'une banque à l'autre (PRD Banques
 * UC-03) : un déplacement garde la question et ses utilisations (les modules
 * en tirent les conséquences par le registre des utilisations) ; une copie
 * est une nouvelle question indépendante, image comprise, sans utilisation.
 */
final class QuestionMover
{
    public function __construct(private readonly UsageRegistry $usages) {}

    /**
     * Ce que le déplacement changerait dans les modules, pour la confirmation.
     *
     * @param  iterable<Question>  $questions
     */
    public function moveNotice(iterable $questions, Bank $target): string
    {
        $notices = [];

        foreach ($questions as $question) {
            if ($question->bank_id === $target->id) {
                continue;
            }

            $notice = $this->usages->moveNotice($question, $target);

            if ($notice !== '') {
                $notices[] = '« '.Str::limit($question->statement, 60).' » : '.$notice;
            }
        }

        return implode(' ', $notices);
    }

    /**
     * @param  iterable<Question>  $questions
     * @return array{moved: int, report: string} questions déplacées (celles déjà dans la cible ne comptent pas), et compte rendu des modules
     */
    public function move(iterable $questions, Bank $target): array
    {
        return DB::transaction(function () use ($questions, $target): array {
            $moved = 0;
            $reports = [];

            foreach ($questions as $question) {
                if ($question->bank_id === $target->id) {
                    continue;
                }

                $question->update(['bank_id' => $target->id]);
                $moved++;
                $report = $this->usages->applyMove($question);

                if ($report !== '') {
                    $reports[$report] = ($reports[$report] ?? 0) + 1;
                }
            }

            $lines = [];
            foreach ($reports as $report => $count) {
                $lines[] = $count === 1 ? $report : "{$report} (× {$count})";
            }

            return ['moved' => $moved, 'report' => implode(' ', $lines)];
        });
    }

    /**
     * @param  iterable<Question>  $questions
     * @return Collection<int, Question> copies créées
     */
    public function copy(iterable $questions, Bank $target): Collection
    {
        $disk = Storage::disk(Question::IMAGE_DISK);
        $copiedImages = [];

        try {
            return DB::transaction(function () use ($questions, $target, $disk, &$copiedImages): Collection {
                $copies = new Collection;

                foreach ($questions as $question) {
                    $copy = (new Question)->forceFill([
                        ...$question->only(['type', 'statement', 'payload', 'explanation', 'difficulty', 'theme', 'image_alt']),
                        'bank_id' => $target->id,
                        'image_path' => null,
                    ]);

                    if ($question->image_path !== null && $disk->exists($question->image_path)) {
                        $copy->image_path = Question::IMAGE_DIRECTORY.'/'.Str::ulid()->toBase32().'.'.pathinfo($question->image_path, PATHINFO_EXTENSION);
                        $disk->copy($question->image_path, $copy->image_path);
                        $copiedImages[] = $copy->image_path;
                    }

                    $copy->save();
                    $copies->push($copy);
                }

                return $copies;
            });
        } catch (Throwable $exception) {
            $disk->delete($copiedImages);

            throw $exception;
        }
    }
}
