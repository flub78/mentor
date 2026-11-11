<?php

namespace App\Banques\Exchange;

use App\Banques\Models\Question;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Enregistre les questions lues dans un fichier d'échange, avec leurs
 * illustrations (PRD Banques UC-05) : création, ou mise à jour d'une question
 * déjà présente, reconnue par sa référence.
 *
 * À utiliser dans une transaction ; en cas d'échec, `discardStoredImages()`
 * efface les illustrations déjà copiées, que l'annulation de la transaction
 * ne retire pas du disque.
 */
final class QuestionPersister
{
    /** Longueur de l'énoncé reprise dans la description d'une image qui n'en a pas. */
    private const ALT_LENGTH = 80;

    /** @var list<string> */
    private array $storedImages = [];

    public function create(QuestionRow $row, int $bankId, ExchangeFile $file): Question
    {
        $imagePath = $row->image === null ? null : $this->storeImage($file, $row->image);

        return Question::query()->create([
            'bank_id' => $bankId,
            'reference' => $row->reference,
            'type' => $row->type,
            'statement' => $row->statement,
            'payload' => $row->payload,
            'explanation' => $row->explanation,
            'theme' => $row->theme,
            'difficulty' => $row->difficulty,
            'image_path' => $imagePath,
            'image_alt' => $imagePath === null ? null : ($row->imageAlt ?? self::defaultAlt($row->statement)),
        ]);
    }

    private static function defaultAlt(string $statement): string
    {
        return 'Illustration : '.mb_strimwidth(trim($statement), 0, self::ALT_LENGTH, '…');
    }

    /**
     * Ce que la ligne changerait à la question : rien, une modification
     * mineure, ou la réponse attendue (modification de fond). Une cellule
     * `illustration` vide garde l'image actuelle.
     *
     * @return 'unchanged'|'changed'|'answer_changed'
     */
    public static function difference(Question $question, QuestionRow $row, ExchangeFile $file): string
    {
        if ($question->handler()->normalize($question->payload) !== $row->payload) {
            return 'answer_changed';
        }

        $changed = $question->statement !== $row->statement
            || $question->explanation !== $row->explanation
            || $question->theme !== $row->theme
            || $question->difficulty !== $row->difficulty;

        if (! $changed && $row->image !== null) {
            $contents = $file->image($row->image)['contents'];

            $changed = $contents !== null && (
                $question->image_path === null
                || Storage::disk(Question::IMAGE_DISK)->get($question->image_path) !== $contents
                || ($row->imageAlt !== null && $row->imageAlt !== $question->image_alt)
            );
        }

        return $changed ? 'changed' : 'unchanged';
    }

    /**
     * Met à jour une question déjà présente avec le contenu de la ligne
     * (PRD Banques UC-05) ; le type et la référence ne changent pas.
     */
    public function update(Question $question, QuestionRow $row, ExchangeFile $file): void
    {
        $question->fill([
            'statement' => $row->statement,
            'payload' => $row->payload,
            'explanation' => $row->explanation,
            'theme' => $row->theme,
            'difficulty' => $row->difficulty,
        ]);

        if ($row->image !== null) {
            $contents = $file->image($row->image)['contents'];
            $current = $question->image_path === null ? null : Storage::disk(Question::IMAGE_DISK)->get($question->image_path);

            if ($contents !== null && $contents !== $current) {
                $question->image_path = $this->storeImage($file, $row->image);
            }

            if ($question->image_path !== null) {
                $question->image_alt = $row->imageAlt ?? $question->image_alt ?? self::defaultAlt($row->statement);
            }
        }

        $question->save();
    }

    public function discardStoredImages(): void
    {
        Storage::disk(Question::IMAGE_DISK)->delete($this->storedImages);
        $this->storedImages = [];
    }

    private function storeImage(ExchangeFile $file, string $name): ?string
    {
        $contents = $file->image($name)['contents'];

        if ($contents === null) {
            return null;
        }

        $info = getimagesizefromstring($contents);
        $extension = $info === false ? 'img' : ltrim((string) image_type_to_extension($info[2]), '.');
        $path = Question::IMAGE_DIRECTORY.'/'.Str::ulid()->toBase32().'.'.$extension;

        Storage::disk(Question::IMAGE_DISK)->put($path, $contents);
        $this->storedImages[] = $path;

        return $path;
    }
}
