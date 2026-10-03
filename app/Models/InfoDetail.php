<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InfoDetail extends Model
{
    protected $fillable = ['label_key', 'detail_text', 'day_number', 'order_in_day'];
}
