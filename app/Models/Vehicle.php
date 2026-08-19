<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Vehicle extends Model { protected $guarded = []; protected function casts(): array { return ['last_seen_at'=>'datetime']; } public function telemetry(){ return $this->hasMany(VehicleTelemetry::class); } public function latestTelemetry(){ return $this->hasOne(VehicleTelemetry::class)->latestOfMany('recorded_at'); } }
