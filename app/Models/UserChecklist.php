<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserChecklist extends Model
{
    protected $table = 'user_checklist';

    protected $fillable = ['user_id', 'day_number', 'item_id', 'is_checked', 'checked_at'];

    protected $casts = [
        'is_checked' => 'boolean',
        'checked_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
