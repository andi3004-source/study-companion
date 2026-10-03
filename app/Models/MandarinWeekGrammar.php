<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MandarinWeekGrammar extends Model
{
    protected $table = 'mandarin_week_grammar';

    protected $fillable = ['week_letter', 'grammar_text', 'listening_text'];
}
