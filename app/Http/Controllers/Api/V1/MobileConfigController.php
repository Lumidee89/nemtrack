<?php
namespace App\Http\Controllers\Api\V1;
use App\Http\Controllers\Controller;
use App\Models\PlatformSetting;
class MobileConfigController extends Controller { public function __invoke(){return response()->json(['success'=>true,'message'=>'Mobile configuration retrieved.','data'=>['mapbox_access_token'=>PlatformSetting::valueFor('mapbox_public_access_token')]]);}}
