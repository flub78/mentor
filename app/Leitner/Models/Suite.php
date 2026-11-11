<?php

namespace App\Leitner\Models;

use App\Leitner\Suites\PresentationOrder;
use Database\Factories\Leitner\SuiteFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Laraskel\Users\Models\Concerns\TracksAuthor;

/**
 * Suite de révision : banques sources et paramètres (PRD Leitner §3, UC-05).
 * Sans connexion, une suite a une seule progression, créée avec elle.
 *
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property int $box_count
 * @property int $min_box_one
 * @property PresentationOrder $presentation_order
 * @property bool $retry_errors
 * @property int|null $personal_bank_id
 */
#[UseFactory(SuiteFactory::class)]
class Suite extends Model
{
    /** @use HasFactory<SuiteFactory> */
    use HasFactory, TracksAuthor;

    /** Nombre de boîtes minimal et maximal (PRD Leitner UC-05). */
    public const MIN_BOXES = 3;

    public const MAX_BOXES = 10;

    protected $table = 'leitner_suites';

    protected $fillable = [
        'name', 'description', 'box_count', 'min_box_one',
        'presentation_order', 'retry_errors', 'personal_bank_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'box_count' => 'integer',
            'min_box_one' => 'integer',
            'presentation_order' => PresentationOrder::class,
            'retry_errors' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Suite $suite): void {
            $defaults = (array) config('leitner.defaults');
            $suite->box_count ??= (int) $defaults['box_count'];
            $suite->min_box_one ??= (int) $defaults['min_box_one'];
            $suite->presentation_order ??= PresentationOrder::from((string) $defaults['presentation_order']);
            $suite->retry_errors ??= (bool) $defaults['retry_errors'];
        });

        static::created(function (Suite $suite): void {
            $suite->progressions()->create();
        });
    }

    /**
     * @return HasMany<SuiteSource, $this>
     */
    public function sources(): HasMany
    {
        return $this->hasMany(SuiteSource::class);
    }

    /**
     * Banques sources.
     *
     * @return BelongsToMany<Bank, $this>
     */
    public function banks(): BelongsToMany
    {
        return $this->belongsToMany(Bank::class, 'leitner_suite_sources')->withTimestamps();
    }

    /**
     * Banque des questions créées par l'étudiant depuis la suite (UC-03).
     *
     * @return BelongsTo<Bank, $this>
     */
    public function personalBank(): BelongsTo
    {
        return $this->belongsTo(Bank::class, 'personal_bank_id');
    }

    /**
     * @return HasMany<Progression, $this>
     */
    public function progressions(): HasMany
    {
        return $this->hasMany(Progression::class);
    }

    /**
     * Progression unique tant qu'il n'y a pas de connexion (PRD Leitner §9.1).
     *
     * @return HasOne<Progression, $this>
     */
    public function progression(): HasOne
    {
        return $this->hasOne(Progression::class)->whereNull('user_id');
    }

    /**
     * Identifiants des banques sources.
     *
     * @return list<int>
     */
    public function bankIds(): array
    {
        return $this->sources()->pluck('bank_id')->map(fn (mixed $id): int => (int) $id)->values()->all();
    }
}
