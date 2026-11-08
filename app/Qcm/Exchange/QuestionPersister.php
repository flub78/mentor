<?php

namespace App\Qcm\Exchange;

use App\Qcm\Models\Answer;
use App\Qcm\Models\Question;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Enregistre les questions lues dans un fichier d'échange, avec leurs
 * réponses et leurs illustrations.
 *
 * À utiliser dans une transaction ; en cas d'échec, `discardStoredImages()`
 * efface les illustrations déjà copiées, que l'annulation de la transaction
 * ne retire pas du disque.
 */
final class QuestionPersister
{
    /** @var list<string> */
    private array $storedImages = [];

    /**
     * @param  array{bank_id?: int|null, questionnaire_id?: int|null}  $owner
     */
    public function create(QuestionData $data, array $owner, ExchangeFile $file): Question
    {
        $question = Question::create([
            ...$owner,
            'statement' => $data->statement,
            'module' => $data->module,
            'explanation' => $data->explanation,
            'image_path' => $data->image === null ? null : $this->storeImage($file, $data->image),
        ]);

        Answer::create(['question_id' => $question->id, 'text' => $data->correctAnswer, 'is_correct' => true, 'position' => 0]);

        foreach ($data->wrongAnswers as $index => $wrong) {
            Answer::create([
                'question_id' => $question->id,
                'text' => $wrong['text'],
                'explanation' => $wrong['explanation'],
                'is_correct' => false,
                'position' => $index + 1,
            ]);
        }

        return $question;
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
