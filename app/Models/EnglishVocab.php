<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EnglishVocab extends Model
{
    protected $table = 'english_vocab';

    protected $fillable = [
        'external_id', 'word', 'pos', 'definition', 'hint', 'cefr_level',
        'categories', 'examples', 'translations', 'ipa', 'synonyms',
    ];

    protected $casts = [
        'categories' => 'array',
        'examples' => 'array',
        'translations' => 'array',
        'synonyms' => 'array',
    ];
}
