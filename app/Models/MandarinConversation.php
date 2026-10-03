<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MandarinConversation extends Model
{
    protected $fillable = [
        'week_letter', 'order_in_week',
        'q_hanzi', 'q_pinyin', 'q_id',
        'a_hanzi', 'a_pinyin', 'a_id',
    ];
}
