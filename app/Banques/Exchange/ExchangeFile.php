<?php

namespace App\Banques\Exchange;

use ZipArchive;

/**
 * Fichier d'échange ouvert en lecture : CSV seul, ou archive ZIP avec le CSV,
 * les illustrations et un fichier de paramètres (PRD Banques §8.4).
 *
 * L'archive n'est jamais extraite sur le disque : chaque élément est lu par
 * son nom, après contrôle de sa taille.
 */
final class ExchangeFile
{
    /** Version du format écrite à l'export et la plus récente acceptée à l'import. */
    public const FORMAT_VERSION = 1;

    /** Nom du CSV dans une archive. */
    public const CSV_NAME = 'questions.csv';

    /** Répertoire des illustrations dans une archive. */
    public const IMAGE_DIRECTORY = 'images/';

    /** Taille maximale du CSV ou du fichier de paramètres lus dans une archive. */
    private const MAX_TEXT_BYTES = 20 * 1024 * 1024;

    private ?ZipArchive $zip = null;

    private CsvParseResult $csv;

    private ?ExchangeKind $kind = null;

    /** @var array<string, mixed> */
    private array $parameters = [];

    private function __construct(private readonly string $name) {}

    /**
     * @param  string  $path  chemin du fichier téléversé
     * @param  string  $name  nom d'origine du fichier, pour les titres par défaut et les messages
     */
    public static function open(string $path, string $name): self
    {
        $file = new self($name);
        $contents = @file_get_contents($path, length: 4);

        if ($contents === false) {
            throw ExchangeException::because("Le fichier « {$name} » ne peut pas être lu.");
        }

        if ($contents === "PK\x03\x04") {
            $file->openZip($path);
        } else {
            $file->csv = (new QuestionCsv)->parse((string) file_get_contents($path));
        }

        return $file;
    }

    public function name(): string
    {
        return $this->name;
    }

    /**
     * Nom du fichier sans extension, titre proposé par défaut.
     */
    public function baseName(): string
    {
        return pathinfo($this->name, PATHINFO_FILENAME);
    }

    public function isArchive(): bool
    {
        return $this->zip !== null;
    }

    public function csv(): CsvParseResult
    {
        return $this->csv;
    }

    /**
     * Nature donnée par le fichier de paramètres ; null pour un CSV seul ou
     * une archive sans paramètres.
     */
    public function kind(): ?ExchangeKind
    {
        return $this->kind;
    }

    /**
     * @return array<string, mixed>
     */
    public function parameters(): array
    {
        return $this->parameters;
    }

    /**
     * Contenu d'une illustration, ou motif pour lequel elle n'est pas utilisable.
     *
     * @return array{contents: string|null, problem: string|null}
     */
    public function image(string $name): array
    {
        if ($this->zip === null) {
            return ['contents' => null, 'problem' => 'un fichier CSV seul ne contient pas les illustrations (utilisez une archive ZIP)'];
        }

        $entry = self::IMAGE_DIRECTORY.$name;
        $stat = $this->zip->statName($entry);

        if ($stat === false) {
            return ['contents' => null, 'problem' => "absente de l'archive (attendue dans {$entry})"];
        }

        $maxKb = (int) config('banques.image_max_kb');

        if ($stat['size'] > $maxKb * 1024) {
            return ['contents' => null, 'problem' => "dépasse la taille maximale de {$maxKb} Ko"];
        }

        $contents = $this->zip->getFromName($entry);

        if ($contents === false || @getimagesizefromstring($contents) === false) {
            return ['contents' => null, 'problem' => "n'est pas une image lisible (JPEG, PNG, GIF, WebP…)"];
        }

        return ['contents' => $contents, 'problem' => null];
    }

    private function openZip(string $path): void
    {
        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            throw ExchangeException::because("L'archive « {$this->name} » est illisible ou endommagée.");
        }

        $this->zip = $zip;
        $csvName = $this->findCsv();
        $this->csv = (new QuestionCsv)->parse($this->readText($csvName));
        $this->readParameters();
    }

    private function findCsv(): string
    {
        $zip = $this->zip;
        assert($zip !== null);

        if ($zip->statName(self::CSV_NAME) !== false) {
            return self::CSV_NAME;
        }

        $candidates = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = (string) $zip->getNameIndex($i);

            if (! str_contains($entry, '/') && str_ends_with(strtolower($entry), '.csv')) {
                $candidates[] = $entry;
            }
        }

        if (count($candidates) !== 1) {
            throw ExchangeException::because(sprintf(
                "L'archive doit contenir à sa racine un fichier %s (%s).",
                self::CSV_NAME,
                $candidates === [] ? 'aucun fichier CSV trouvé' : 'plusieurs fichiers CSV trouvés : '.implode(', ', $candidates),
            ));
        }

        return $candidates[0];
    }

    private function readText(string $entry): string
    {
        $zip = $this->zip;
        assert($zip !== null);
        $stat = $zip->statName($entry);

        if ($stat === false || $stat['size'] > self::MAX_TEXT_BYTES) {
            throw ExchangeException::because("Le fichier {$entry} de l'archive est absent ou trop volumineux.");
        }

        $contents = $zip->getFromName($entry);

        if ($contents === false) {
            throw ExchangeException::because("Le fichier {$entry} de l'archive est illisible.");
        }

        return $contents;
    }

    private function readParameters(): void
    {
        $zip = $this->zip;
        assert($zip !== null);
        $found = array_values(array_filter(
            ExchangeKind::cases(),
            fn (ExchangeKind $kind): bool => $zip->statName($kind->parametersFile()) !== false,
        ));

        if (count($found) > 1) {
            throw ExchangeException::because('L\'archive contient plusieurs fichiers de paramètres ('
                .implode(', ', array_map(fn (ExchangeKind $kind): string => $kind->parametersFile(), $found))
                .') : elle ne doit en contenir qu\'un.');
        }

        if ($found === []) {
            return;
        }

        $kind = $found[0];
        $parameters = json_decode($this->readText($kind->parametersFile()), true);

        if (! is_array($parameters)) {
            throw ExchangeException::because("Le fichier {$kind->parametersFile()} n'est pas un JSON valide : ".json_last_error_msg().'.');
        }

        $version = $parameters['format_version'] ?? null;

        if (! is_int($version) || $version < 1) {
            throw ExchangeException::because("Le fichier {$kind->parametersFile()} doit indiquer \"format_version\" (nombre entier, actuellement ".self::FORMAT_VERSION.').');
        }

        if ($version > self::FORMAT_VERSION) {
            throw ExchangeException::because("Le fichier a été produit par une version plus récente du format ({$version}) ; cette application lit jusqu'à la version ".self::FORMAT_VERSION.'. Mettez Mentor à jour.');
        }

        $this->kind = $kind;
        /** @var array<string, mixed> $parameters */
        $this->parameters = $parameters;
    }

    public function __destruct()
    {
        $this->zip?->close();
    }
}
