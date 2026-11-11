<?php

namespace App\Leitner\Models;

use App\Banques\Models\Bank;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laraskel\Users\Models\Concerns\TracksAuthor;

/**
 * Banque source d'une suite (PRD Leitner UC-05).
 *
 * @property int $id
 * @property int $suite_id
 * @property int $bank_id
 */
class SuiteSource extends Model
{
    use TracksAuthor;

    protected $table = 'leitner_suite_sources';

    protected $fillable = ['suite_id', 'bank_id'];

    /**
     * @return BelongsTo<Suite, $this>
     */
    public function suite(): BelongsTo
    {
        return $this->belongsTo(Suite::class);
    }

    /**
     * @return BelongsTo<Bank, $this>
     */
    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }
}
