<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConversationApiCall extends Model
{
    const UPDATED_AT = null;

    protected $table = 'conversation_api_calls';

    protected $fillable = ['session_id', 'tokens_in', 'tokens_out'];
}
