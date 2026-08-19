<?php
namespace App\Http\Controllers\Api\V1;
use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use App\Models\VehicleTelemetry;
use App\Services\AuditService;
use Illuminate\Http\Request;

class MobilityController extends Controller {
 public function index(Request $r){ return response()->json(['success'=>true,'message'=>'Fleet retrieved.','data'=>Vehicle::with('latestTelemetry')->where('organization_id',$r->user()->organization_id)->get()]); }
 public function store(Request $r){ $d=$r->validate(['name'=>'required|string|max:100','registration_number'=>'required|string|max:30','device_uid'=>'required|string|max:100|unique:vehicles','type'=>'nullable|string','driver_name'=>'nullable|string']); $v=Vehicle::create($d+['organization_id'=>$r->user()->organization_id]); AuditService::record($r,'vehicle.created',$v,$v->toArray()); return response()->json(['success'=>true,'message'=>'Vehicle added.','data'=>['vehicle'=>$v]],201); }
 public function telemetry(Request $r, Vehicle $vehicle){ abort_unless($vehicle->organization_id===$r->user()->organization_id,403); $d=$r->validate(['latitude'=>'required|numeric|between:-90,90','longitude'=>'required|numeric|between:-180,180','speed'=>'nullable|numeric|min:0','heading'=>'nullable|numeric|between:0,360','battery_level'=>'nullable|numeric|between:0,100','ignition'=>'nullable|in:on,off','recorded_at'=>'nullable|date']); $point=VehicleTelemetry::create($d+['organization_id'=>$vehicle->organization_id,'vehicle_id'=>$vehicle->id,'recorded_at'=>$d['recorded_at']??now()]); $vehicle->update(['status'=>'online','last_seen_at'=>now()]); return response()->json(['success'=>true,'message'=>'Telemetry recorded.','data'=>['telemetry'=>$point]],201); }
}
