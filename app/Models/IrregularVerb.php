<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IrregularVerb extends Model
{
    protected $fillable = [
        'base_form', 'past_form', 'past_participle',
        'meaning_id', 'example_sentence', 'batch_number', 'order_in_batch',
    ];
}
