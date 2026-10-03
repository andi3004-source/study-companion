<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserExamScore extends Model
{
    protected $fillable = ['user_id', 'day_number', 'exam_mode', 'correct', 'total', 'taken_at'];

    protected $casts = ['taken_at' => 'datetime'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
