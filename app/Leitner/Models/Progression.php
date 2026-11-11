<?php

namespace App\Leitner\Models;

use App\Leitner\Sessions\SessionStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laraskel\Users\Models\Concerns\TracksAuthor;

/**
 * Progression d'un étudiant dans une suite (PRD Leitner §3) : contenu des
 * boîtes et numéro de la dernière séance. Les questions *en banque* ne sont
 * pas enregistrées : ce sont les questions des sources sans ligne de
 * progression.
 *
 * @property int $id
 * @property int $suite_id
 * @property int|null $user_id
 * @property int $last_session_number
 */
class Progression extends Model
{
    use TracksAuthor;

    protected $table = 'leitner_progressions';

    protected $fillable = ['suite_id', 'user_id', 'last_session_number'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_session_number' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Suite, $this>
     */
    public function suite(): BelongsTo
    {
        return $this->belongsTo(Suite::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<ProgressItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(ProgressItem::class);
    }

    /**
     * @return HasMany<Session, $this>
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(Session::class);
    }

    /**
     * Séance en cours, s'il y en a une.
     */
    public function currentSession(): ?Session
    {
        return $this->sessions()->where('status', SessionStatus::EnCours)->latest('id')->first();
    }

    /**
     * Questions des banques sources de la suite.
     *
     * @return Builder<Question>
     */
    public function suiteQuestions(): Builder
    {
        return Question::query()->whereIn('bank_id', SuiteSource::query()
            ->select('bank_id')
            ->where('suite_id', $this->suite_id));
    }

    /**
     * Questions des sources jamais introduites dans cette progression.
     *
     * @return Builder<Question>
     */
    public function inBank(): Builder
    {
        return $this->suiteQuestions()->whereNotIn('id', $this->items()->select('question_id'));
    }

    /**
     * Questions en circulation dans la boîte `$box` (hors suspendues).
     *
     * @return HasMany<ProgressItem, $this>
     */
    public function inBox(int $box): HasMany
    {
        return $this->items()->where('box', $box)->whereNull('mastered_at')->whereNull('suspended_at');
    }

    /**
     * @return HasMany<ProgressItem, $this>
     */
    public function mastered(): HasMany
    {
        return $this->items()->whereNotNull('mastered_at');
    }

    /**
     * @return HasMany<ProgressItem, $this>
     */
    public function suspended(): HasMany
    {
        return $this->items()->whereNull('mastered_at')->whereNotNull('suspended_at');
    }

    /**
     * Répartition des questions de la suite (PRD Leitner UC-11) ; la somme
     * `inBank + boîtes + mastered + suspended` vaut `total`.
     *
     * @return array{inBank: int, boxes: array<int, int>, mastered: int, suspended: int, total: int}
     */
    public function counts(): array
    {
        $boxCount = $this->suite->box_count;
        $perBox = $this->items()
            ->whereNull('mastered_at')
            ->whereNull('suspended_at')
            ->selectRaw('box, count(*) as aggregate')
            ->groupBy('box')
            ->pluck('aggregate', 'box');

        $boxes = [];
        for ($box = 1; $box <= $boxCount; $box++) {
            $boxes[$box] = (int) ($perBox[$box] ?? 0);
        }

        $inBank = $this->inBank()->count();
        $mastered = $this->mastered()->count();
        $suspended = $this->suspended()->count();

        return [
            'inBank' => $inBank,
            'boxes' => $boxes,
            'mastered' => $mastered,
            'suspended' => $suspended,
            'total' => $inBank + array_sum($boxes) + $mastered + $suspended,
        ];
    }

    /**
     * Introduit une question en banque dans la boîte 1.
     */
    public function introduce(Question $question): ProgressItem
    {
        return $this->items()->create([
            'question_id' => $question->id,
            'box' => 1,
            'introduced_at' => now(),
        ]);
    }
}
