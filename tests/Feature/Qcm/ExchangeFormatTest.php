<?php

namespace Tests\Feature\Qcm;

use App\Qcm\Exchange\ExchangeException;
use App\Qcm\Exchange\ExchangeFile;
use App\Qcm\Exchange\QuestionCsv;
use App\Qcm\Exchange\QuestionData;
use Tests\Feature\Qcm\Concerns\BuildsExchangeFiles;
use Tests\TestCase;

/**
 * Étape 4 du plan QCM : lecture et écriture du fichier d'échange (PRD QCM §6.1).
 */
class ExchangeFormatTest extends TestCase
{
    use BuildsExchangeFiles;

    public function test_csv_with_semicolons_bom_and_variable_wrong_answers_is_read(): void
    {
        $csv = "\u{FEFF}enonce;bonne_reponse;explication;module;illustration;mauvaise_reponse_1;explication_1;mauvaise_reponse_2;explication_2\n"
            ."Quel nuage annonce un orage ?;Cumulonimbus;Nuage d'orage.;Météo;;Cirrus;Trop haut.;Stratus;\n"
            ."Vitesse de décrochage ?;Dépend de la charge;;;;Fixe;;;\n";

        $result = (new QuestionCsv)->parse($csv);

        $this->assertSame([], $result->errors);
        $this->assertCount(2, $result->questions);
        [$first, $second] = $result->questions;
        $this->assertSame(2, $first->line);
        $this->assertSame('Cumulonimbus', $first->correctAnswer);
        $this->assertSame([['text' => 'Cirrus', 'explanation' => 'Trop haut.'], ['text' => 'Stratus', 'explanation' => null]], $first->wrongAnswers);
        $this->assertSame('Météo', $first->module);
        $this->assertNull($second->module);
        $this->assertCount(1, $second->wrongAnswers);
    }

    public function test_csv_with_commas_and_spreadsheet_header_is_read(): void
    {
        $csv = "Énoncé,Bonne réponse,Mauvaise réponse 1,Commentaire\n\"Question, avec virgule ?\",Oui,Non,ignoré\n";

        $result = (new QuestionCsv)->parse($csv);

        $this->assertSame('Question, avec virgule ?', $result->questions[0]->statement);
        $this->assertSame(['Commentaire'], $result->unknownColumns);
    }

    public function test_line_longer_than_the_header_is_rejected(): void
    {
        $result = (new QuestionCsv)->parse('enonce;bonne_reponse;mauvaise_reponse_1
A ?;Oui;Non;de trop
');

        $this->assertSame([], $result->questions);
        $this->assertStringContainsString('plus de colonnes que l\'en-tête', $result->errors[0]['message']);
    }

    public function test_invalid_lines_are_reported_without_blocking_the_others(): void
    {
        $csv = "enonce;bonne_reponse;mauvaise_reponse_1;explication_1;mauvaise_reponse_2;explication_2\n"
            .";Oui;Non;;;\n"
            ."Sans bonne réponse ?;;Non;;;\n"
            ."Sans mauvaise réponse ?;Oui;;;;\n"
            ."Explication orpheline ?;Oui;Non;;;Pourquoi\n"
            .";;;;;\n"
            ."Correcte ?;Oui;Non;;;\n";

        $result = (new QuestionCsv)->parse($csv);

        $this->assertCount(1, $result->questions);
        $this->assertSame(7, $result->questions[0]->line);
        $this->assertSame([2, 3, 4, 5], array_column($result->errors, 'line'));
        $this->assertStringContainsString('énoncé est vide', $result->errors[0]['message']);
        $this->assertStringContainsString('bonne réponse est vide', $result->errors[1]['message']);
        $this->assertStringContainsString('Aucune mauvaise réponse', $result->errors[2]['message']);
        $this->assertStringContainsString("explication 2 n'a pas de mauvaise réponse 2", $result->errors[3]['message']);
    }

    public function test_missing_required_columns_reject_the_file_with_the_expected_columns(): void
    {
        $this->expectException(ExchangeException::class);
        $this->expectExceptionMessage('Colonnes obligatoires absentes de la première ligne : mauvaise_reponse_1');

        (new QuestionCsv)->parse("enonce;bonne_reponse\nA ?;B\n");
    }

    public function test_non_utf8_csv_is_rejected_with_advice(): void
    {
        $this->expectException(ExchangeException::class);
        $this->expectExceptionMessage('CSV UTF-8');

        (new QuestionCsv)->parse(mb_convert_encoding("enonce;bonne_reponse;mauvaise_reponse_1\nÉté ?;Oui;Non\n", 'ISO-8859-1', 'UTF-8'));
    }

    public function test_written_csv_reads_back_identically(): void
    {
        $question = new QuestionData(0, "Énoncé avec ; et \"guillemets\"\nsur deux lignes ?", 'Bonne', [
            ['text' => 'Fausse 1', 'explanation' => 'Parce que.'],
            ['text' => 'Fausse 2', 'explanation' => null],
        ], 'Explication', 'Météo', 'carte.png');

        $read = (new QuestionCsv)->parse((new QuestionCsv)->write([$question]))->questions[0];

        $this->assertSame($question->statement, $read->statement);
        $this->assertSame($question->wrongAnswers, $read->wrongAnswers);
        $this->assertSame(['Explication', 'Météo', 'carte.png'], [$read->explanation, $read->module, $read->image]);
    }

    public function test_zip_gives_questions_parameters_and_images(): void
    {
        $path = $this->zipFile([
            'questions.csv' => "enonce;bonne_reponse;mauvaise_reponse_1;illustration\nA ?;Oui;Non;carte.png\nB ?;Oui;Non;absente.png\n",
            'images/carte.png' => self::PNG,
            'banque.json' => json_encode(['format_version' => 1, 'nom' => 'Météo']),
        ]);

        $file = ExchangeFile::open($path, 'meteo.zip');

        $this->assertTrue($file->isArchive());
        $this->assertSame('banque', $file->kind()?->value);
        $this->assertSame('Météo', $file->parameters()['nom']);
        $this->assertSame(self::PNG, $file->image('carte.png')['contents']);
        $this->assertStringContainsString('absente de l\'archive', (string) $file->image('absente.png')['problem']);
    }

    public function test_zip_problems_are_explained(): void
    {
        $cases = [
            'aucun fichier CSV' => ['lisez-moi.txt' => 'x'],
            'plusieurs fichiers de paramètres' => ['questions.csv' => "enonce;bonne_reponse;mauvaise_reponse_1\n", 'banque.json' => '{}', 'examen.json' => '{}'],
            'JSON valide' => ['questions.csv' => "enonce;bonne_reponse;mauvaise_reponse_1\n", 'banque.json' => '{nom:'],
            'format_version' => ['questions.csv' => "enonce;bonne_reponse;mauvaise_reponse_1\n", 'banque.json' => '{"nom":"x"}'],
            'version plus récente' => ['questions.csv' => "enonce;bonne_reponse;mauvaise_reponse_1\n", 'banque.json' => '{"format_version":99}'],
        ];

        foreach ($cases as $expected => $entries) {
            try {
                ExchangeFile::open($this->zipFile($entries), 'f.zip');
                $this->fail("Archive acceptée à tort ({$expected}).");
            } catch (ExchangeException $exception) {
                $this->assertStringContainsString($expected, $exception->getMessage());
            }
        }
    }

    public function test_image_that_is_not_an_image_is_refused(): void
    {
        $file = ExchangeFile::open($this->zipFile([
            'questions.csv' => "enonce;bonne_reponse;mauvaise_reponse_1\n",
            'images/faux.png' => 'pas une image',
        ]), 'f.zip');

        $this->assertStringContainsString("n'est pas une image", (string) $file->image('faux.png')['problem']);
    }

    public function test_csv_alone_has_no_images(): void
    {
        $file = ExchangeFile::open($this->csvFile("enonce;bonne_reponse;mauvaise_reponse_1\nA ?;Oui;Non\n"), 'f.csv');

        $this->assertFalse($file->isArchive());
        $this->assertNull($file->kind());
        $this->assertStringContainsString('CSV seul', (string) $file->image('x.png')['problem']);
    }
}
