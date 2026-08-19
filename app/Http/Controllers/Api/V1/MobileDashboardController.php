<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MobileDashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user()->load('organization');
        $org = $user->organization_id;
        $role = $user->role ?: 'organization_admin';
        $stats = match ($role) {
            'super_admin' => ['organizations' => DB::table('organizations')->count(), 'administrators' => DB::table('users')->where('role', 'organization_admin')->count(), 'incidents' => DB::table('panic_incidents')->where('status', 'active')->count()],
            'organization_admin' => ['visitors' => DB::table('visitors')->where('organization_id', $org)->count(), 'students' => DB::table('students')->where('organization_id', $org)->count(), 'vehicles' => DB::table('vehicles')->where('organization_id', $org)->count()],
            'security_officer' => ['active_passes' => DB::table('visitor_invitations')->where('organization_id', $org)->where('status', 'active')->where('valid_until', '>', now())->count(), 'active_pickups' => DB::table('pickup_authorizations')->where('organization_id', $org)->where('status', 'active')->where('valid_until', '>', now())->count(), 'incidents' => DB::table('panic_incidents')->where('organization_id', $org)->where('status', 'active')->count()],
            'staff', 'resident' => ['my_invitations' => DB::table('visitor_invitations')->where('organization_id', $org)->where('host_user_id', $user->id)->count(), 'active_passes' => DB::table('visitor_invitations')->where('organization_id', $org)->where('host_user_id', $user->id)->where('status', 'active')->count()],
            'parent', 'guardian' => ['students' => DB::table('students')->where('organization_id', $org)->count(), 'pickup_people' => DB::table('pickup_persons')->where('organization_id', $org)->where('status', 'active')->count(), 'active_authorizations' => DB::table('pickup_authorizations')->where('organization_id', $org)->where('guardian_user_id', $user->id)->where('status', 'active')->count()],
            default => [],
        };
        return response()->json(['success' => true, 'message' => 'Mobile dashboard retrieved.', 'data' => ['user' => $user, 'role' => $role, 'stats' => $stats, 'capabilities' => $this->capabilities($role)]]);
    }

    private function capabilities(string $role): array
    {
        return match ($role) {
            'super_admin' => ['platform.view', 'audit.view', 'incidents.view', 'fleet.view'],
            'organization_admin' => ['visitor.create', 'visitor.verify', 'pickup.create', 'pickup.verify', 'fleet.manage', 'panic.trigger'],
            'security_officer' => ['visitor.verify', 'pickup.verify', 'fleet.view', 'panic.trigger', 'panic.respond'],
            'staff', 'resident' => ['visitor.create', 'visitor.view', 'panic.trigger'],
            'parent', 'guardian' => ['student.view', 'pickup.create', 'pickup.view', 'panic.trigger'],
            default => [],
        };
    }
}
