<?php

namespace App\Qcm\Exams;

use App\Banques\Exchange\QuestionCsv;
use App\Qcm\Models\Result;
use App\Qcm\Study\ModuleCatalog;

/**
 * Export CSV d'une liste de résultats (PRD QCM UC-09), à ouvrir dans un
 * tableur : séparateur « ; », UTF-8 avec BOM, comme le fichier d'échange.
 *
 * Une colonne par renseignement d'identité et par module rencontrés dans la
 * liste, dans leur ordre d'apparition ; les modules de même nom sont
 * regroupés sans tenir compte des majuscules. Les taux sont écrits avec une
 * virgule décimale, sans le signe %.
 */
final class ResultsCsv
{
    private const BOM = "\u{FEFF}";

    /**
     * @param  iterable<Result>  $results
     */
    public function csv(iterable $results): string
    {
        $results = is_array($results) ? $results : iterator_to_array($results, false);

        /** @var array<string, string> $identityColumns libellé => libellé */
        $identityColumns = ['Nom' => 'Nom', 'Prénom' => 'Prénom'];
        /** @var array<string, string> $moduleColumns clé de module => libellé */
        $moduleColumns = [];

        foreach ($results as $result) {
            foreach ($result->identity as $line) {
                $identityColumns[$line['label']] ??= $line['label'];
            }

            foreach ($result->module_rates as $module) {
                $moduleColumns[ModuleCatalog::key($module['label'] === ModuleCatalog::NO_MODULE_LABEL ? null : $module['label'])] ??= $module['label'];
            }
        }

        $handle = fopen('php://temp', 'r+');
        $write = fn (array $row) => fputcsv($handle, $row, QuestionCsv::SEPARATOR, '"', '', "\n");

        $write([
            ...array_values($identityColumns),
            'Date du passage', 'Examen', 'Questionnaire', 'Version',
            ...array_map(fn (string $label): string => 'Taux '.$label, array_values($moduleColumns)),
            'Taux global', 'Taux minimal', 'Réussite', 'Numéro de certificat',
        ]);

        foreach ($results as $result) {
            $identity = array_column($result->identity, 'value', 'label');
            $rates = [];

            foreach ($result->module_rates as $module) {
                $rates[ModuleCatalog::key($module['label'] === ModuleCatalog::NO_MODULE_LABEL ? null : $module['label'])] = self::rate((float) $module['rate']);
            }

            $write([
                ...array_map(fn (string $label): string => (string) ($identity[$label] ?? ''), array_values($identityColumns)),
                $result->taken_at->format('d/m/Y H:i'),
                $result->exam_title,
                $result->questionnaire_title,
                (string) $result->questionnaire_version,
                ...array_map(fn (string $key): string => $rates[$key] ?? '', array_keys($moduleColumns)),
                self::rate($result->global_rate),
                (string) $result->pass_rate,
                $result->passed ? 'Oui' : 'Non',
                $result->certificate_number,
            ]);
        }

        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        return self::BOM.$csv;
    }

    public function fileName(?string $examTitle = null): string
    {
        $slug = $examTitle === null ? '' : '-'.str($examTitle)->slug();

        return 'resultats'.$slug.'-'.now()->format('Ymd-Hi').'.csv';
    }

    private static function rate(float $rate): string
    {
        return str_replace('.', ',', (string) round($rate, 2));
    }
}
