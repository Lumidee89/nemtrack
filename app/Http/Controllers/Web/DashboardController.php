<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use App\Models\PlatformSetting;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        if ($request->user()->role === 'super_admin') {
            $stats = [
                'organizations' => DB::table('organizations')->count(),
                'administrators' => DB::table('users')->where('role', 'organization_admin')->count(),
                'users' => DB::table('users')->whereNot('role', 'super_admin')->count(),
                'activeIncidents' => DB::table('panic_incidents')->where('status', 'active')->count(),
                'visitors' => DB::table('visitors')->count(),
                'vehicles' => DB::table('vehicles')->count(),
            ];
            $activity = DB::table('audit_logs')->leftJoin('users', 'users.id', '=', 'audit_logs.user_id')->leftJoin('organizations', 'organizations.id', '=', 'audit_logs.organization_id')->latest('audit_logs.created_at')->limit(10)->get(['audit_logs.id', 'users.name as administrator', 'organizations.name as organization', 'action', 'audit_logs.created_at']);
            $organizations = DB::table('organizations')->leftJoin('users', function ($join) { $join->on('users.organization_id', '=', 'organizations.id')->where('users.role', '=', 'organization_admin'); })->select('organizations.id', 'organizations.name', 'organizations.type', 'organizations.status', DB::raw('count(users.id) as administrators'))->groupBy('organizations.id', 'organizations.name', 'organizations.type', 'organizations.status')->limit(8)->get();
            $mapboxConfigured = filled(PlatformSetting::valueFor('mapbox_public_access_token'));
            return Inertia::render('SuperAdmin/Dashboard', compact('stats', 'activity', 'organizations', 'mapboxConfigured'));
        }

        if ($request->user()->role !== 'organization_admin') {
            $destination = match ($request->user()->role) {
                'security_officer' => 'entry-exit',
                'staff', 'resident' => 'invitations',
                'parent', 'guardian' => 'pickup',
                default => 'pbs',
            };
            return redirect()->route('portal.index', ['section' => $destination]);
        }

        $org = $request->user()->organization_id;
        $stats = [
            'visitorsToday' => DB::table('visitor_entries')->where('organization_id', $org)->whereDate('check_in_at', today())->count(),
            'activePasses' => DB::table('visitor_invitations')->where('organization_id', $org)->where('status', 'active')->where('valid_until', '>', now())->count(),
            'pickupsToday' => DB::table('pickup_transactions')->where('organization_id', $org)->whereDate('verified_at', today())->count(),
            'students' => DB::table('students')->where('organization_id', $org)->count(),
        ];
        $activity = DB::table('audit_logs')->where('organization_id', $org)->latest()->limit(6)->get(['id', 'action', 'created_at']);
        return Inertia::render('Dashboard', compact('stats', 'activity'));
    }
}
