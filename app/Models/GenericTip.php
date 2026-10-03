<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GenericTip extends Model
{
    protected $fillable = ['item_type', 'tip_text'];
}
