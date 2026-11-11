<?php

namespace App\Banques\Exchange;

use App\Banques\Models\Question;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

/**
 * Écriture d'un fichier d'échange (PRD Banques §8) : CSV seul, ou archive ZIP
 * avec le CSV, les illustrations et le fichier de paramètres.
 */
final class ExchangeWriter
{
    /**
     * CSV des questions, sans illustration.
     *
     * @param  Collection<int, Question>  $questions
     */
    public function csv(Collection $questions): string
    {
        return (new QuestionCsv)->write($questions->map(fn (Question $question): QuestionRow => QuestionRow::fromQuestion($question))->all());
    }

    /**
     * Archive ZIP dans un fichier temporaire ; l'appelant le supprime après envoi.
     *
     * @param  Collection<int, Question>  $questions
     * @param  array<string, mixed>|null  $parameters  contenu du fichier de paramètres, sans la version du format
     * @return string chemin du fichier temporaire
     */
    public function zip(Collection $questions, ?ExchangeKind $kind = null, ?array $parameters = null): string
    {
        $path = tempnam(sys_get_temp_dir(), 'banque-export-');

        if ($path === false) {
            throw new RuntimeException('Impossible de créer le fichier temporaire de l\'export.');
        }

        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);

        $disk = Storage::disk(Question::IMAGE_DISK);
        $usedNames = [];
        $rows = [];

        foreach ($questions as $question) {
            $imageName = null;

            if ($question->image_path !== null && $disk->exists($question->image_path)) {
                $imageName = $this->uniqueName(basename($question->image_path), $usedNames);
                $usedNames[] = $imageName;
                $zip->addFromString(ExchangeFile::IMAGE_DIRECTORY.$imageName, (string) $disk->get($question->image_path));
            }

            $rows[] = QuestionRow::fromQuestion($question, $imageName);
        }

        $zip->addFromString(ExchangeFile::CSV_NAME, (new QuestionCsv)->write($rows));

        if ($kind !== null) {
            $zip->addFromString(
                $kind->parametersFile(),
                (string) json_encode(['format_version' => ExchangeFile::FORMAT_VERSION, ...($parameters ?? [])], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            );
        }

        $zip->close();

        return $path;
    }

    /**
     * @param  list<string>  $used
     */
    private function uniqueName(string $name, array $used): string
    {
        $candidate = $name;
        $counter = 2;

        while (in_array($candidate, $used, true)) {
            $candidate = pathinfo($name, PATHINFO_FILENAME).'-'.$counter++.'.'.pathinfo($name, PATHINFO_EXTENSION);
        }

        return $candidate;
    }
}
