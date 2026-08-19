<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class PanicIncident extends Model { protected $guarded=[]; protected function casts(): array { return ['acknowledged_at'=>'datetime','resolved_at'=>'datetime']; } public function device(){ return $this->belongsTo(PanicDevice::class,'panic_device_id'); } }
