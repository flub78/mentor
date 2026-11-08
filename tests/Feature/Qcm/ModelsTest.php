<?php

namespace Tests\Feature\Qcm;

use App\Qcm\Models\Bank;
use App\Qcm\Models\Question;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Étape 1 du plan QCM : modèles des banques, questions et réponses.
 *
 * Base SQLite en mémoire (phpunit.xml), propre à chaque exécution :
 * RefreshDatabase est sans effet sur une base partagée.
 */
class ModelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_question_has_one_correct_and_several_wrong_answers(): void
    {
        $question = Question::factory()->withAnswers(3)->create();

        $this->assertSame(4, $question->answers()->count());
        $this->assertTrue($question->correctAnswer->is_correct);
        $this->assertSame(0, $question->correctAnswer->position);
        $this->assertSame([1, 2, 3], $question->wrongAnswers->pluck('position')->all());
    }

    public function test_module_names_are_distinct_sorted_and_limited_to_the_bank(): void
    {
        $bank = Bank::factory()->create();
        Question::factory()->for($bank)->create(['module' => 'Réglementation']);
        Question::factory()->for($bank)->create(['module' => 'Météorologie']);
        Question::factory()->for($bank)->create(['module' => 'Météorologie']);
        Question::factory()->for($bank)->create(['module' => null]);
        Question::factory()->create(['module' => 'Navigation']);

        $this->assertSame(['Météorologie', 'Réglementation'], $bank->moduleNames());
    }

    public function test_blank_module_is_stored_as_no_module(): void
    {
        $question = Question::factory()->create(['module' => '   ']);
        $this->assertNull($question->fresh()->module);

        $question->update(['module' => '  Météo  ']);
        $this->assertSame('Météo', $question->fresh()->module);
    }

    public function test_replacing_or_deleting_the_image_removes_the_old_file(): void
    {
        Storage::fake(Question::IMAGE_DISK);
        $first = UploadedFile::fake()->image('carte.png')->store(Question::IMAGE_DIRECTORY, Question::IMAGE_DISK);
        $second = UploadedFile::fake()->image('schema.png')->store(Question::IMAGE_DIRECTORY, Question::IMAGE_DISK);

        $question = Question::factory()->create(['image_path' => $first]);
        $question->update(['image_path' => $second]);

        Storage::disk(Question::IMAGE_DISK)->assertMissing($first);
        Storage::disk(Question::IMAGE_DISK)->assertExists($second);

        $question->delete();
        Storage::disk(Question::IMAGE_DISK)->assertMissing($second);
    }

    public function test_deleting_a_bank_removes_its_questions_answers_and_images(): void
    {
        Storage::fake(Question::IMAGE_DISK);
        $image = UploadedFile::fake()->image('carte.png')->store(Question::IMAGE_DIRECTORY, Question::IMAGE_DISK);
        $bank = Bank::factory()->create();
        $question = Question::factory()->for($bank)->withAnswers()->create(['image_path' => $image]);
        $other = Question::factory()->withAnswers()->create();

        $bank->delete();

        $this->assertModelMissing($question);
        $this->assertDatabaseMissing('qcm_answers', ['question_id' => $question->id]);
        Storage::disk(Question::IMAGE_DISK)->assertMissing($image);
        $this->assertModelExists($other);
    }
}
