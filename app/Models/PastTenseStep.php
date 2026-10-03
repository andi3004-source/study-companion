<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PastTenseStep extends Model
{
    protected $fillable = ['slot_number', 'title', 'detail', 'batch_number_ref', 'platform_link_key'];
}
