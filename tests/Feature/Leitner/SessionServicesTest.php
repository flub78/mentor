<?php

namespace Tests\Feature\Leitner;

use App\Banques\Models\Bank;
use App\Banques\Models\Question;
use App\Banques\Questions\InvalidResponse;
use App\Banques\Questions\Verdict;
use App\Leitner\Models\ProgressItem;
use App\Leitner\Models\Session;
use App\Leitner\Models\SessionEntry;
use App\Leitner\Models\Suite;
use App\Leitner\Sessions\AnswerRecorder;
use App\Leitner\Sessions\AnswerUndoer;
use App\Leitner\Sessions\EntrySkipper;
use App\Leitner\Sessions\NextSession;
use App\Leitner\Sessions\SessionException;
use App\Leitner\Sessions\SessionFinisher;
use App\Leitner\Sessions\SessionStarter;
use App\Leitner\Sessions\SessionStatus;
use App\Leitner\Suites\PresentationOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Étape 3 du plan Leitner : services des séances (PRD Leitner UC-08, §7).
 *
 * Les questions sont des vrai / faux dont la bonne réponse est « vrai » :
 * répondre true est juste, false est faux.
 */
class SessionServicesTest extends TestCase
{
    use RefreshDatabase;

    private Bank $bank;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bank = Bank::factory()->create();
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function suite(array $settings = []): Suite
    {
        return Suite::factory()->withBanks($this->bank)->create([
            'box_count' => 5,
            'min_box_one' => 2,
            'retry_errors' => false,
            'presentation_order' => PresentationOrder::BoiteUnDAbord,
            ...$settings,
        ]);
    }

    private function question(string $statement, ?int $difficulty = null): Question
    {
        return Question::factory()->for($this->bank)->vraiFaux(true)->difficulty($difficulty)->create(['statement' => $statement]);
    }

    private function start(Suite $suite): Session
    {
        $result = (new SessionStarter)->start($suite->progression->fresh(), seed: 1);
        $this->assertNotNull($result->session, implode(' ', $result->notices));

        return $result->session;
    }

    private function answer(Session $session, SessionEntry $entry, bool $correct): SessionEntry
    {
        return (new AnswerRecorder)->record($session->fresh(), $entry->fresh(), $correct, 1500);
    }

    /**
     * Fait une séance complète : `$answers` donne, par énoncé, si la réponse
     * est juste. Renvoie les énoncés posés.
     *
     * @param  array<string, bool>  $answers
     * @return list<string>
     */
    private function playSession(Suite $suite, array $answers): array
    {
        $session = $this->start($suite);
        $asked = [];

        while (($entry = $session->fresh()->nextEntry()) !== null) {
            $statement = $entry->question->statement;
            $this->assertArrayHasKey($statement, $answers, "Question inattendue : {$statement}");
            $asked[] = $statement;
            $this->answer($session, $entry, $answers[$statement]);
        }

        (new SessionFinisher)->finish($session->fresh());

        return $asked;
    }

    /**
     * Énoncés par boîte (« maîtrisée » pour les questions maîtrisées).
     *
     * @return array<string, list<string>>
     */
    private function boxes(Suite $suite): array
    {
        $boxes = [];

        foreach ($suite->progression->fresh()->items()->with('question')->get() as $item) {
            $key = $item->mastered_at !== null ? 'maîtrisée' : (string) $item->box;
            $boxes[$key][] = $item->question->statement;
        }

        ksort($boxes);

        return array_map(function (array $statements): array {
            sort($statements);

            return $statements;
        }, $boxes);
    }

    public function test_eight_sessions_follow_the_rules_of_the_prd(): void
    {
        $suite = $this->suite();
        $this->question('q1', 1);
        $this->question('q2', 1);
        $this->question('q3', 2);
        $this->question('q4', 3);
        $this->question('q5', 3);

        // S1 (boîte 1) : q1, q2 introduites ; q1 juste → 2, q2 fausse → reste en 1.
        $this->assertEqualsCanonicalizing(['q1', 'q2'], $this->playSession($suite, ['q1' => true, 'q2' => false]));
        $this->assertSame(['1' => ['q2'], '2' => ['q1']], $this->boxes($suite));

        // S2 (boîtes 1, 2), boîte 1 d'abord : q3 introduite ; tout juste.
        $asked = $this->playSession($suite, ['q1' => true, 'q2' => true, 'q3' => true]);
        $this->assertEqualsCanonicalizing(['q2', 'q3'], array_slice($asked, 0, 2));
        $this->assertSame('q1', $asked[2]);
        $this->assertSame(['2' => ['q2', 'q3'], '3' => ['q1']], $this->boxes($suite));

        // S3 (boîte 1) : q4, q5 introduites ; q4 juste → 2, q5 fausse → 1.
        $this->assertEqualsCanonicalizing(['q4', 'q5'], $this->playSession($suite, ['q4' => true, 'q5' => false]));
        $this->assertSame(['1' => ['q5'], '2' => ['q2', 'q3', 'q4'], '3' => ['q1']], $this->boxes($suite));

        // S4 (boîtes 1, 2, 3) : banque vide ; q5 fausse (reste en 1), q3 fausse (recule en 1), q2, q4, q1 justes.
        $this->assertEqualsCanonicalizing(['q5', 'q2', 'q3', 'q4', 'q1'], $this->playSession($suite, ['q5' => false, 'q2' => true, 'q3' => false, 'q4' => true, 'q1' => true]));
        $this->assertSame(['1' => ['q3', 'q5'], '3' => ['q2', 'q4'], '4' => ['q1']], $this->boxes($suite));

        // S5 (boîte 1) : q5 et q3 justes → 2.
        $this->playSession($suite, ['q5' => true, 'q3' => true]);
        $this->assertSame(['2' => ['q3', 'q5'], '3' => ['q2', 'q4'], '4' => ['q1']], $this->boxes($suite));

        // S6 (boîtes 1, 2) : q5 fausse → recule en 1, q3 juste → 3.
        $this->assertEqualsCanonicalizing(['q5', 'q3'], $this->playSession($suite, ['q5' => false, 'q3' => true]));
        $this->assertSame(['1' => ['q5'], '3' => ['q2', 'q3', 'q4'], '4' => ['q1']], $this->boxes($suite));

        // S7 (boîte 1) : q5 juste → 2.
        $this->assertSame(['q5'], $this->playSession($suite, ['q5' => true]));

        // S8 (boîtes 1 à 4) : q4 fausse → recule en 2, les autres justes ; q1 atteint la boîte 5.
        $this->assertEqualsCanonicalizing(['q5', 'q2', 'q3', 'q4', 'q1'], $this->playSession($suite, ['q5' => true, 'q2' => true, 'q3' => true, 'q4' => false, 'q1' => true]));
        $this->assertSame(['2' => ['q4'], '3' => ['q5'], '4' => ['q2', 'q3'], '5' => ['q1']], $this->boxes($suite));
        $this->assertSame([1 => 0, 2 => 1, 3 => 1, 4 => 2, 5 => 1], $suite->progression->fresh()->counts()['boxes']);
        $this->assertSame(8, $suite->progression->fresh()->last_session_number);
    }

    public function test_a_question_is_mastered_from_the_last_box(): void
    {
        $suite = $this->suite(['box_count' => 3, 'min_box_one' => 1]);
        $question = $this->question('q1');
        $suite->progression->items()->create(['question_id' => $question->id, 'box' => 3, 'introduced_at' => now()]);
        $suite->progression->update(['last_session_number' => 3]);

        $this->playSession($suite, ['q1' => true]);

        $item = $suite->progression->items()->sole();
        $this->assertNotNull($item->mastered_at);
        $this->assertNull($item->box);
        $this->assertTrue(SessionEntry::query()->sole()->mastered);
    }

    public function test_start_announces_introductions_and_lack_of_new_questions(): void
    {
        $suite = $this->suite(['min_box_one' => 3]);
        $this->question('q1');

        $result = (new SessionStarter)->start($suite->progression);

        $this->assertSame(1, $result->introduced);
        $this->assertSame('1 nouvelle question.', $result->notices[0]);
        $this->assertStringStartsWith('Plus assez de questions à introduire', $result->notices[1]);
        $this->assertTrue($result->session->entries()->sole()->is_new);
    }

    public function test_start_with_nothing_to_ask_creates_no_session(): void
    {
        $suite = $this->suite();

        $result = (new SessionStarter)->start($suite->progression);

        $this->assertNull($result->session);
        $this->assertStringStartsWith('Rien à poser à cette séance', $result->notices[array_key_last($result->notices)]);
        $this->assertSame(0, Session::count());
    }

    public function test_start_resumes_the_session_in_progress(): void
    {
        $suite = $this->suite();
        $this->question('q1');
        $this->question('q2');
        $session = $this->start($suite);
        $this->answer($session, $session->nextEntry(), true);

        $result = (new SessionStarter)->start($suite->progression->fresh());

        $this->assertTrue($result->resumed);
        $this->assertTrue($result->session->is($session));
        $this->assertSame(['done' => 1, 'total' => 2], $result->session->position());
    }

    public function test_session_number_is_assigned_at_the_first_answer(): void
    {
        $suite = $this->suite();
        $this->question('q1');
        $this->question('q2');

        $abandoned = $this->start($suite);
        $this->assertNull((new SessionFinisher)->abandon($abandoned));
        $this->assertModelMissing($abandoned);
        $this->assertSame(0, $suite->progression->fresh()->last_session_number);

        $interrupted = $this->start($suite);
        $this->answer($interrupted, $interrupted->nextEntry(), true);
        $this->assertSame(1, $interrupted->fresh()->number);

        $summary = (new SessionFinisher)->abandon($interrupted->fresh());

        $this->assertSame(SessionStatus::Abandonnee, $interrupted->fresh()->status);
        $this->assertSame(1, $summary->asked);
        $this->assertSame(1, $suite->progression->fresh()->last_session_number);
        $this->assertSame(1, $suite->progression->fresh()->inBox(1)->count(), 'La question non posée reste en boîte 1.');
    }

    public function test_retry_errors_repeat_a_failed_question_without_moving_it_again(): void
    {
        $suite = $this->suite(['retry_errors' => true, 'min_box_one' => 1]);
        $question = $this->question('q1');
        $suite->progression->items()->create(['question_id' => $question->id, 'box' => 3, 'introduced_at' => now()]);
        $suite->progression->update(['last_session_number' => 3]);
        $session = $this->start($suite);

        $this->answer($session, $session->nextEntry(), false);
        $firstRetry = $session->fresh()->nextEntry();
        $this->assertTrue($firstRetry->is_retry);
        $this->answer($session, $firstRetry, false);
        $secondRetry = $session->fresh()->nextEntry();
        $this->answer($session, $secondRetry, true);

        $this->assertNull($session->fresh()->nextEntry());
        $this->assertSame(3, $session->entries()->count());
        $item = ProgressItem::query()->sole();
        $this->assertSame([2, 1], [$item->box, $item->consecutive_failures], 'Seule la première réponse compte : recul de 3 à 2.');

        $summary = (new SessionFinisher)->finish($session->fresh());
        $this->assertSame([1, 0, 1], [$summary->asked, $summary->correct, $summary->wrong]);
        $this->assertSame(['3 → 2' => 1], $summary->moves);
    }

    public function test_no_retry_when_disabled(): void
    {
        $suite = $this->suite(['retry_errors' => false]);
        $this->question('q1');
        $session = $this->start($suite);

        $this->answer($session, $session->nextEntry(), false);

        $this->assertNull($session->fresh()->nextEntry());
    }

    public function test_undo_the_last_answer_restores_the_box_and_removes_the_retry(): void
    {
        $suite = $this->suite(['retry_errors' => true, 'min_box_one' => 1]);
        $question = $this->question('q1');
        $suite->progression->items()->create(['question_id' => $question->id, 'box' => 2, 'consecutive_failures' => 0, 'introduced_at' => now()]);
        $suite->progression->update(['last_session_number' => 1]);
        $session = $this->start($suite);
        $entry = $session->nextEntry();
        $this->answer($session, $entry, false);

        $reopened = (new AnswerUndoer)->undo($session->fresh());

        $this->assertTrue($reopened->is($entry));
        $this->assertNull($reopened->fresh()->answered_at);
        $this->assertSame(1, $session->entries()->count(), 'La reprise ajoutée est retirée.');
        $item = ProgressItem::query()->sole();
        $this->assertSame([2, 0], [$item->box, $item->consecutive_failures]);

        $this->expectException(SessionException::class);
        $this->expectExceptionMessage('Seule la dernière réponse peut être annulée, une seule fois');
        (new AnswerUndoer)->undo($session->fresh());
    }

    public function test_undo_is_refused_after_skipping_the_next_question(): void
    {
        $suite = $this->suite();
        $this->question('q1');
        $this->question('q2');
        $session = $this->start($suite);
        $this->answer($session, $session->nextEntry(), true);
        (new EntrySkipper)->skip($session->fresh(), $session->fresh()->nextEntry());

        $this->expectException(SessionException::class);
        (new AnswerUndoer)->undo($session->fresh());
    }

    public function test_undo_of_a_mastering_answer(): void
    {
        $suite = $this->suite(['box_count' => 3, 'min_box_one' => 1]);
        $question = $this->question('q1');
        $suite->progression->items()->create(['question_id' => $question->id, 'box' => 3, 'introduced_at' => now()]);
        $suite->progression->update(['last_session_number' => 3]);
        $session = $this->start($suite);
        $this->answer($session, $session->nextEntry(), true);

        (new AnswerUndoer)->undo($session->fresh());

        $item = ProgressItem::query()->sole();
        $this->assertSame(3, $item->box);
        $this->assertNull($item->mastered_at);
    }

    public function test_skipped_question_keeps_its_box(): void
    {
        $suite = $this->suite();
        $this->question('q1');
        $session = $this->start($suite);

        (new EntrySkipper)->skip($session, $session->nextEntry());

        $this->assertSame(1, ProgressItem::query()->sole()->box);
        $this->assertNull($session->fresh()->nextEntry());
        $this->assertNull((new SessionFinisher)->finish($session->fresh()), 'Séance sans réponse : supprimée.');
    }

    public function test_answers_are_refused_with_a_message(): void
    {
        $suite = $this->suite();
        $this->question('q1');
        $this->question('q2');
        $session = $this->start($suite);
        $entry = $session->nextEntry();
        $this->answer($session, $entry, true);

        $this->assertRefused(fn () => $this->answer($session, $entry, true), 'Cette question a déjà été traitée dans la séance');

        $other = Suite::factory()->withBanks(Bank::factory()->create())->create();
        Question::factory()->for($other->banks()->first())->vraiFaux()->create();
        $otherSession = (new SessionStarter)->start($other->progression)->session;
        $this->assertRefused(fn () => $this->answer($session, $otherSession->nextEntry(), true), 'Cette question n\'appartient pas à la séance en cours');

        (new SessionFinisher)->finish($session->fresh());
        $this->assertRefused(fn () => $this->answer($session, $session->entries()->whereNull('answered_at')->first(), true), 'La séance est terminée');
    }

    public function test_invalid_response_records_nothing(): void
    {
        $suite = $this->suite();
        $this->question('q1');
        $session = $this->start($suite);

        try {
            (new AnswerRecorder)->record($session, $session->nextEntry(), 'peut-être');
            $this->fail('Une réponse invalide doit être refusée.');
        } catch (InvalidResponse $exception) {
            $this->assertSame('Répondez « Vrai » ou « Faux ».', $exception->getMessage());
        }

        $this->assertNull($session->fresh()->number);
        $this->assertNull($session->nextEntry()->answered_at);
    }

    public function test_deleted_question_is_skipped_with_a_count(): void
    {
        $suite = $this->suite();
        $first = $this->question('q1');
        $this->question('q2');
        $session = $this->start($suite);

        $first->delete();

        $this->assertSame(1, (new EntrySkipper)->skipDeletedQuestions($session));
        $this->assertSame('q2', $session->nextEntry()->question->statement);
    }

    public function test_answer_records_verdict_and_duration(): void
    {
        $suite = $this->suite();
        $this->question('q1');
        $session = $this->start($suite);

        $entry = $this->answer($session, $session->nextEntry(), false)->fresh();

        $this->assertSame(Verdict::Fausse, $entry->verdict);
        $this->assertFalse($entry->response);
        $this->assertSame(1500, $entry->duration_ms);
        $this->assertSame([1, 1], [$entry->box_before, $entry->box_after]);
    }

    public function test_summary_and_next_session(): void
    {
        $suite = $this->suite(['min_box_one' => 3]);
        foreach (['q1', 'q2', 'q3', 'q4'] as $statement) {
            $this->question($statement);
        }
        $session = $this->start($suite);

        foreach ([true, false, true] as $correct) {
            $this->answer($session, $session->fresh()->nextEntry(), $correct);
        }
        $summary = (new SessionFinisher)->finish($session->fresh());

        $this->assertSame([3, 2, 1, 3, 0], [$summary->asked, $summary->correct, $summary->wrong, $summary->introduced, $summary->mastered]);
        $this->assertSame(67, $summary->successRate());
        $this->assertSame(['1 → 1' => 1, '1 → 2' => 2], $summary->moves);
        $this->assertCount(1, $summary->failed);
        $this->assertEquals(new NextSession(2, [1, 2], [1 => 1, 2 => 2], 1), $summary->next);
        $this->assertSame(4, $summary->next->total());
        $this->assertSame('Séance n° 2 — boîtes posées : 1 et 2 — 4 questions, dont 1 nouvelle.', $summary->next->describe());
    }

    private function assertRefused(callable $action, string $message): void
    {
        try {
            $action();
            $this->fail("Action acceptée, refus attendu : {$message}");
        } catch (SessionException $exception) {
            $this->assertStringStartsWith($message, $exception->getMessage());
        }
    }
}
