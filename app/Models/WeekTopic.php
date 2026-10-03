<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WeekTopic extends Model
{
    protected $fillable = ['week_number', 'topic_order', 'topic_name'];
}
