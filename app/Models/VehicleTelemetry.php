<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class VehicleTelemetry extends Model { protected $table='vehicle_telemetry'; protected $guarded=[]; protected function casts(): array { return ['recorded_at'=>'datetime']; } }
