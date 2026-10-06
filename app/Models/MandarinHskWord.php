<?php

namespace App\Models;

use App\Support\HskCorrections;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class MandarinHskWord extends Model
{
    protected $fillable = [
        'hanzi', 'traditional', 'pinyin', 'pinyin_normalized', 'meaning_en', 'meaning_id', 'data_status',
        'pos', 'radical', 'frequency', 'hsk_level_old', 'hsk_level_new', 'classifiers',
    ];

    protected $casts = [
        'pos' => 'array',
        'classifiers' => 'array',
    ];

    /**
     * Rows TranslateMandarinMeaningsJob may fill: meaning_id still empty, not
     * hand-corrected, and an English gloss worth translating. A gloss led by
     * "surname" or containing "(slang)" is the dataset picking the wrong
     * homograph (都 "surname Du") or an obscure sense, so translating it would
     * only put a wrong Indonesian meaning in front of the learner — those
     * stay NULL ("Arti belum tersedia") until corrected by hand.
     */
    public function scopeNeedsTranslation(Builder $query): void
    {
        $query->whereNull('meaning_id')
            ->where(fn (Builder $q) => $q->whereNull('data_status')->orWhere('data_status', '!=', HskCorrections::STATUS))
            ->where('meaning_en', 'not like', 'surname%')
            ->where('meaning_en', 'not like', '%(slang)%');
    }
}
