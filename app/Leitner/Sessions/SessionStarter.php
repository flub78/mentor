<?php

namespace App\Leitner\Sessions;

use App\Leitner\Engine\Introducer;
use App\Leitner\Engine\SessionComposer;
use App\Leitner\Models\Progression;
use App\Leitner\Models\Session;
use App\Leitner\Suites\PresentationOrder;
use Illuminate\Support\Facades\DB;

/**
 * Lancement d'une séance (PRD Leitner UC-08, étapes 2 et 3) : reprise de la
 * séance en cours s'il y en a une, introduction des nouvelles questions
 * jusqu'au minimum de la boîte 1, puis composition.
 */
final class SessionStarter
{
    public function start(Progression $progression, ?PresentationOrder $order = null, ?int $seed = null): StartResult
    {
        $current = $progression->currentSession();

        if ($current !== null) {
            return new StartResult($current, true, 0, ['Une séance est en cours : elle reprend là où elle s\'est arrêtée.']);
        }

        return DB::transaction(function () use ($progression, $order, $seed): StartResult {
            $suite = $progression->suite;
            $order ??= $suite->presentation_order;
            $notices = [];

            $missing = max(0, $suite->min_box_one - $progression->inBox(1)->count());
            $introducedIds = [];

            if ($missing > 0) {
                $picked = Introducer::pick($progression->inBank()->get(), $missing, $seed);

                foreach ($picked as $question) {
                    $progression->introduce($question);
                    $introducedIds[] = $question->id;
                }

                if (count($introducedIds) > 0) {
                    $notices[] = count($introducedIds) === 1 ? '1 nouvelle question.' : count($introducedIds).' nouvelles questions.';
                }

                if (count($introducedIds) < $missing) {
                    $notices[] = 'Plus assez de questions à introduire : la boîte 1 compte moins de '
                        ."{$suite->min_box_one} questions. Ajoutez des questions ou une banque à la suite pour en avoir de nouvelles.";
                }
            }

            $items = SessionComposer::compose(
                $progression->items()->get(),
                $progression->last_session_number + 1,
                $suite->box_count,
                $order,
                $seed,
            );

            if ($items->isEmpty()) {
                $notices[] = 'Rien à poser à cette séance : toutes les questions sont maîtrisées, suspendues ou dans des boîtes qui ne sont pas posées aujourd\'hui. '
                    .'Réintroduisez des questions, ajoutez une source ou revenez à la prochaine séance.';

                return new StartResult(null, false, 0, $notices);
            }

            $session = $progression->sessions()->create([
                'kind' => Session::KIND_SESSION,
                'status' => SessionStatus::EnCours,
                'presentation_order' => $order,
                'started_at' => now(),
            ]);

            foreach ($items->values() as $index => $item) {
                $session->entries()->create([
                    'question_id' => $item->question_id,
                    'position' => $index + 1,
                    'is_new' => in_array($item->question_id, $introducedIds, true),
                    'box_before' => $item->box,
                ]);
            }

            return new StartResult($session, false, count($introducedIds), $notices);
        });
    }
}
