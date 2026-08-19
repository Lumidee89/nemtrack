<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PickupAuthorization extends Model
{
    protected $guarded = [];
    protected function casts(): array { return ['valid_from' => 'datetime', 'valid_until' => 'datetime', 'used_at' => 'datetime']; }
    public function student() { return $this->belongsTo(Student::class); }
    public function pickupPerson() { return $this->belongsTo(PickupPerson::class); }
}
