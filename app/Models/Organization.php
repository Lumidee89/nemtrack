<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Organization extends Model
{
    use HasFactory;
    protected $guarded = [];

    public function modules() { return $this->belongsToMany(Module::class, 'organization_modules')->withPivot(['enabled', 'starts_at', 'expires_at']); }
    public function users() { return $this->hasMany(User::class); }
    public function hasModule(string $code): bool { return $this->modules()->where('code', strtoupper($code))->wherePivot('enabled', true)->where('available', true)->where(fn($query)=>$query->whereNull('organization_modules.expires_at')->orWhere('organization_modules.expires_at','>',now()))->exists(); }
}
