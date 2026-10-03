<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformLink extends Model
{
    protected $fillable = ['link_key', 'name', 'url'];
}
