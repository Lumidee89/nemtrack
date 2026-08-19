<?php
namespace App\Http\Controllers\Web;
use App\Http\Controllers\Controller;
use App\Models\PlatformSetting;
use App\Services\AuditService;
use Illuminate\Http\Request;
class PlatformSettingsController extends Controller {
 public function updateMapbox(Request $request){abort_unless($request->user()->role==='super_admin',403);$token=$request->validate(['mapbox_access_token'=>['required','string','starts_with:pk.','max:500']])['mapbox_access_token'];$setting=PlatformSetting::updateOrCreate(['key'=>'mapbox_public_access_token'],['value'=>$token]);AuditService::record($request,'platform.mapbox.updated',$setting,['configured'=>true]);return back()->with('success','Mapbox access token updated for mobile and web maps.');}
}
