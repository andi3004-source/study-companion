<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MandarinHskWord extends Model
{
    protected $fillable = [
        'hanzi', 'traditional', 'pinyin', 'pinyin_normalized', 'meaning_en', 'meaning_id',
        'pos', 'radical', 'frequency', 'hsk_level_old', 'hsk_level_new', 'classifiers',
    ];

    protected $casts = [
        'pos' => 'array',
        'classifiers' => 'array',
    ];
}
