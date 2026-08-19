<?php
namespace App\Http\Controllers\Api\V1;
use App\Http\Controllers\Controller;
use App\Models\PanicIncident;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PanicController extends Controller {
 public function index(Request $r){ return response()->json(['success'=>true,'message'=>'Panic incidents retrieved.','data'=>PanicIncident::with('device')->where('organization_id',$r->user()->organization_id)->latest()->paginate(20)]); }
 public function trigger(Request $r){ $d=$r->validate(['panic_device_id'=>'nullable|integer','source'=>'nullable|in:mobile,hardware,web','location_name'=>'nullable|string|max:160','latitude'=>'nullable|numeric|between:-90,90','longitude'=>'nullable|numeric|between:-180,180','message'=>'nullable|string|max:500']); $i=PanicIncident::create($d+['uuid'=>(string)Str::uuid(),'organization_id'=>$r->user()->organization_id,'triggered_by'=>$r->user()->id,'severity'=>'critical','status'=>'active']); AuditService::record($r,'panic.triggered',$i,$i->toArray()); return response()->json(['success'=>true,'message'=>'Emergency alert activated.','data'=>['incident'=>$i]],201); }
 public function update(Request $r, PanicIncident $incident){ abort_unless($incident->organization_id===$r->user()->organization_id,403); $status=$r->validate(['status'=>'required|in:acknowledged,resolved'])['status']; $values=['status'=>$status,$status.'_at'=>now(),$status.'_by'=>$r->user()->id]; $incident->update($values); AuditService::record($r,'panic.'.$status,$incident,$values); return response()->json(['success'=>true,'message'=>'Incident '.$status.'.','data'=>['incident'=>$incident]]); }
}
