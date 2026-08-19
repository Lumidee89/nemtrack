<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VisitorInvitation extends Model
{
    protected $guarded = [];
    protected function casts(): array { return ['valid_from' => 'datetime', 'valid_until' => 'datetime']; }
    public function visitor() { return $this->belongsTo(Visitor::class); }
    public function host() { return $this->belongsTo(User::class, 'host_user_id'); }
}
