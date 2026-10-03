<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserWordProgress extends Model
{
    protected $table = 'user_word_progress';

    protected $fillable = ['user_id', 'word_type', 'word_id', 'is_known'];

    protected $casts = ['is_known' => 'boolean'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
