<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserNote extends Model
{
    protected $fillable = ['user_id', 'day_number', 'lang', 'item_index', 'note_text'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
