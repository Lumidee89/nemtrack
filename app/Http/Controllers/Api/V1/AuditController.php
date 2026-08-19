<?php
namespace App\Http\Controllers\Api\V1;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class AuditController extends Controller {
 public function __invoke(Request $request){ abort_unless($request->user()->role==='super_admin',403); $items=DB::table('audit_logs')->leftJoin('users','users.id','=','audit_logs.user_id')->leftJoin('organizations','organizations.id','=','audit_logs.organization_id')->latest('audit_logs.created_at')->limit(100)->get(['audit_logs.id','users.name as administrator','organizations.name as organization','action','audit_logs.created_at']); return response()->json(['success'=>true,'message'=>'Audit activity retrieved.','data'=>$items]); }
}
