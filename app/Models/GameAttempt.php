<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameAttempt extends Model
{
    const UPDATED_AT = null;

    protected $fillable = ['user_id', 'item_id', 'kategori', 'benar'];

    protected $casts = [
        'item_id' => 'integer',
        'benar' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
