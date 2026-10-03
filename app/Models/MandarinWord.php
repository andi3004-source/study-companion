<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MandarinWord extends Model
{
    protected $fillable = [
        'hanzi', 'pinyin', 'pinyin_normalized', 'meaning_id', 'meaning_en',
        'batch_number', 'order_in_batch', 'hsk_level_old', 'hsk_level_new',
        'traditional', 'radical', 'frequency', 'classifiers',
    ];

    protected $casts = [
        'classifiers' => 'array',
    ];
}
