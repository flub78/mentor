<?php

namespace Tests\Feature\Banques;

use App\Banques\Models\Bank;
use App\Banques\Models\Question;
use App\Banques\Questions\Difficulty;
use App\Banques\Questions\InvalidQuestion;
use App\Banques\Questions\QuestionType;
use App\Banques\Questions\Verdict;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Étape 1 du plan Leitner : modèles des banques et des questions.
 *
 * Base SQLite en mémoire (phpunit.xml), propre à chaque exécution.
 */
class ModelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_factory_states_create_a_valid_question_of_each_type(): void
    {
        $questions = [
            Question::factory()->qcm()->create(),
            Question::factory()->vraiFaux()->create(),
            Question::factory()->autoEvaluation()->create(),
            Question::factory()->valeurNumerique()->create(),
        ];

        $this->assertSame(QuestionType::cases(), array_map(fn (Question $q): QuestionType => $q->fresh()->type, $questions));
        $this->assertSame(Verdict::Juste, $questions[2]->evaluate(true)->verdict);
        $this->assertSame(Verdict::Juste, $questions[3]->evaluate('1,227')->verdict);
        $this->assertSame('1,225 kg/m³ (± 0,005)', $questions[3]->expectedAnswer());
    }

    public function test_bank_has_questions(): void
    {
        $bank = Bank::factory()->create();
        $question = Question::factory()->for($bank)->create();

        $this->assertTrue($bank->questions->contains($question));
        $this->assertTrue($question->bank->is($bank));
    }

    public function test_theme_names_are_distinct_sorted_and_limited_to_the_bank(): void
    {
        $bank = Bank::factory()->create();
        Question::factory()->for($bank)->create(['theme' => 'Europe']);
        Question::factory()->for($bank)->create(['theme' => 'Asie']);
        Question::factory()->for($bank)->create(['theme' => 'Europe']);
        Question::factory()->for($bank)->create(['theme' => null]);
        Question::factory()->create(['theme' => 'Océanie']);

        $this->assertSame(['Asie', 'Europe'], $bank->themeNames());
    }

    public function test_blank_theme_is_stored_as_no_theme(): void
    {
        $question = Question::factory()->create(['theme' => '   ']);
        $this->assertNull($question->fresh()->theme);

        $question->update(['theme' => '  Europe  ']);
        $this->assertSame('Europe', $question->fresh()->theme);
    }

    public function test_difficulty_is_cast_to_its_enum(): void
    {
        $question = Question::factory()->difficulty(3)->create();

        $this->assertSame(Difficulty::Difficile, $question->fresh()->difficulty);
        $this->assertNull(Question::factory()->create()->fresh()->difficulty);
    }

    public function test_payload_is_normalized_on_save(): void
    {
        $question = Question::factory()->valeurNumerique()->create([
            'payload' => ['value' => '12,5', 'tolerance_kind' => 'aucune', 'tolerance' => '2', 'unit' => '  '],
        ]);

        $this->assertSame(['value' => 12.5, 'tolerance_kind' => 'aucune', 'tolerance' => null, 'unit' => null], $question->fresh()->payload);
    }

    public function test_invalid_payload_is_refused_with_a_french_message(): void
    {
        $this->expectException(InvalidQuestion::class);
        $this->expectExceptionMessage('Question « QCM » invalide : Une question QCM doit avoir au moins une mauvaise réponse.');

        Question::factory()->create(['payload' => ['correct' => 'Paris', 'wrong' => []]]);
    }

    public function test_type_cannot_be_changed_after_creation(): void
    {
        $question = Question::factory()->vraiFaux()->create();

        $this->expectException(InvalidQuestion::class);
        $this->expectExceptionMessage('Le type d\'une question ne peut pas être changé après sa création');

        $question->update(['type' => QuestionType::AutoEvaluation, 'payload' => ['answer' => 'Oui']]);
    }

    public function test_question_without_type_is_refused(): void
    {
        $this->expectException(InvalidQuestion::class);
        $this->expectExceptionMessage('Le type de la question est obligatoire.');

        Question::factory()->create(['type' => null]);
    }

    public function test_replacing_or_deleting_the_image_removes_the_old_file(): void
    {
        Storage::fake(Question::IMAGE_DISK);
        $first = UploadedFile::fake()->image('monument.png')->store(Question::IMAGE_DIRECTORY, Question::IMAGE_DISK);
        $second = UploadedFile::fake()->image('carte.png')->store(Question::IMAGE_DIRECTORY, Question::IMAGE_DISK);

        $question = Question::factory()->create(['image_path' => $first, 'image_alt' => 'Un monument']);
        $question->update(['image_path' => $second]);
        Storage::disk(Question::IMAGE_DISK)->assertMissing($first);
        Storage::disk(Question::IMAGE_DISK)->assertExists($second);

        $question->delete();
        Storage::disk(Question::IMAGE_DISK)->assertMissing($second);
    }

    public function test_image_description_is_cleared_with_the_image(): void
    {
        $question = Question::factory()->create(['image_path' => null, 'image_alt' => 'Orphelin']);

        $this->assertNull($question->fresh()->image_alt);
    }

    public function test_deleting_a_bank_deletes_its_questions_and_their_images(): void
    {
        Storage::fake(Question::IMAGE_DISK);
        $image = UploadedFile::fake()->image('carte.png')->store(Question::IMAGE_DIRECTORY, Question::IMAGE_DISK);
        $bank = Bank::factory()->create();
        $question = Question::factory()->for($bank)->create(['image_path' => $image, 'image_alt' => 'Carte']);
        $other = Question::factory()->create();

        $bank->delete();

        $this->assertModelMissing($question);
        $this->assertModelExists($other);
        Storage::disk(Question::IMAGE_DISK)->assertMissing($image);
    }
}
