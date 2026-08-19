<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PickupAuthorization;
use App\Models\PickupPerson;
use App\Models\Student;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PickupController extends Controller
{
    public function students(Request $request) { return response()->json(['success' => true, 'message' => 'Students retrieved.', 'data' => Student::where('organization_id', $request->user()->organization_id)->get()]); }
    public function people(Request $request) { return response()->json(['success' => true, 'message' => 'Pickup people retrieved.', 'data' => PickupPerson::where('organization_id', $request->user()->organization_id)->get()]); }

    public function authorize(Request $request)
    {
        $data = $request->validate(['student_id' => ['required', 'integer'], 'pickup_person_id' => ['required', 'integer'], 'valid_until' => ['required', 'date', 'after:now'], 'authorization_type' => ['required', 'in:one_time,temporary,permanent,emergency']]);
        $org = $request->user()->organization_id;
        abort_unless(Student::where('organization_id', $org)->whereKey($data['student_id'])->exists() && PickupPerson::where('organization_id', $org)->whereKey($data['pickup_person_id'])->where('status', 'active')->exists(), 403, 'Tenant access denied.');
        $auth = PickupAuthorization::create($data + ['uuid' => (string) Str::uuid(), 'organization_id' => $org, 'guardian_user_id' => $request->user()->id, 'authorization_code' => strtoupper(Str::random(6)), 'qr_token' => hash('sha256', Str::random(64)), 'valid_from' => now(), 'status' => 'active']);
        AuditService::record($request, 'pickup.authorized', $auth, $auth->toArray());
        return response()->json(['success' => true, 'message' => 'Pickup authorized successfully.', 'data' => ['authorization' => $auth->load(['student', 'pickupPerson'])]], 201);
    }

    public function verify(Request $request)
    {
        $token = $request->validate(['token' => ['required', 'string']])['token'];
        $auth = PickupAuthorization::with(['student', 'pickupPerson'])->where('organization_id', $request->user()->organization_id)->where(fn ($q) => $q->where('qr_token', $token)->orWhere('authorization_code', strtoupper($token)))->first();
        $code = ! $auth ? 'PICKUP_AUTH_NOT_FOUND' : ($auth->used_at ? 'PICKUP_AUTH_USED' : ($auth->valid_until->isPast() ? 'PICKUP_AUTH_EXPIRED' : null));
        if ($code) return response()->json(['success' => false, 'message' => 'Pickup authorization is not valid.', 'code' => $code, 'errors' => null], 422);
        AuditService::record($request, 'pickup.verified', $auth);
        return response()->json(['success' => true, 'message' => 'Pickup authorization is valid.', 'data' => ['authorization' => $auth]]);
    }
}
