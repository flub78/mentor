<?php

namespace App\Banques\Exchange;

use App\Banques\Models\Question;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Enregistre les questions lues dans un fichier d'échange, avec leurs
 * illustrations (PRD Banques UC-05).
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
            'type' => $row->type,
            'statement' => $row->statement,
            'payload' => $row->payload,
            'explanation' => $row->explanation,
            'theme' => $row->theme,
            'difficulty' => $row->difficulty,
            'image_path' => $imagePath,
            'image_alt' => $imagePath === null ? null : ($row->imageAlt ?? 'Illustration : '.mb_strimwidth(trim($row->statement), 0, self::ALT_LENGTH, '…')),
        ]);
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
