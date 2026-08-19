<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Visitor extends Model
{
    protected $guarded = [];
    protected function casts(): array { return ['watchlisted' => 'boolean']; }
}
