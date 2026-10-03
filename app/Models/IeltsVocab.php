<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IeltsVocab extends Model
{
    protected $table = 'ielts_vocab';

    protected $fillable = ['topic', 'word_en', 'meaning_id', 'example_sentence'];
}
