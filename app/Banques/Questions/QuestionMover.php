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
 * Une référence déjà portée dans la banque cible n'est pas reprise.
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
     * Message sur les références qui n'ont pas pu suivre les questions.
     */
    public static function droppedReferencesNotice(int $count): ?string
    {
        return match ($count) {
            0 => null,
            1 => '1 question a perdu sa référence, déjà portée par une question de la banque cible.',
            default => "{$count} questions ont perdu leur référence, déjà portée par une question de la banque cible.",
        };
    }

    /**
     * @param  iterable<Question>  $questions
     * @return array{moved: int, report: string, droppedReferences: int} questions déplacées (celles déjà dans la cible ne comptent pas), compte rendu des modules et références retirées
     */
    public function move(iterable $questions, Bank $target): array
    {
        return DB::transaction(function () use ($questions, $target): array {
            $moved = 0;
            $dropped = 0;
            $reports = [];

            foreach ($questions as $question) {
                if ($question->bank_id === $target->id) {
                    continue;
                }

                $reference = $this->freeReference($question->reference, $target, $dropped);
                $question->update(['bank_id' => $target->id, 'reference' => $reference]);
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

            return ['moved' => $moved, 'report' => implode(' ', $lines), 'droppedReferences' => $dropped];
        });
    }

    /**
     * @param  iterable<Question>  $questions
     *
     * @param-out int $droppedReferences  copies créées sans la référence de l'original, déjà portée dans la cible
     *
     * @return Collection<int, Question> copies créées
     */
    public function copy(iterable $questions, Bank $target, ?int &$droppedReferences = 0): Collection
    {
        $droppedReferences = 0;
        $disk = Storage::disk(Question::IMAGE_DISK);
        $copiedImages = [];

        try {
            return DB::transaction(function () use ($questions, $target, $disk, &$copiedImages, &$droppedReferences): Collection {
                $copies = new Collection;

                foreach ($questions as $question) {
                    $copy = (new Question)->forceFill([
                        ...$question->only(['type', 'statement', 'payload', 'explanation', 'difficulty', 'theme', 'image_alt']),
                        'bank_id' => $target->id,
                        'reference' => $this->freeReference($question->reference, $target, $droppedReferences),
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

    /**
     * La référence si elle est libre dans la banque cible, null sinon (et le compte des références retirées augmente).
     */
    private function freeReference(?string $reference, Bank $target, int &$dropped): ?string
    {
        if ($reference === null || Question::holdingReference($target->id, $reference) === null) {
            return $reference;
        }

        $dropped++;

        return null;
    }
}
