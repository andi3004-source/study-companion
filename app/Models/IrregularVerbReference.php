<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IrregularVerbReference extends Model
{
    protected $table = 'irregular_verbs_reference';

    protected $fillable = [
        'base_form', 'past_form', 'past_participle', 'ing_form', 'meaning_id', 'frequency_rank',
    ];
}
