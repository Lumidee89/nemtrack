<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\PanicIncident;
use App\Models\Vehicle;
use App\Models\Organization;
use App\Models\User;
use App\Models\Visitor;
use App\Models\VisitorInvitation;
use App\Models\Student;
use App\Models\PickupPerson;
use App\Models\PickupAuthorization;
use App\Services\AuditService;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;

class PortalController extends Controller
{
    public function index(Request $request, string $section)
    {
        $user = $request->user();
        $isSuperAdmin = $user->role === 'super_admin';
        abort_unless(in_array($section, $this->allowedSections($user->role), true), 403);
        $requiredModule = ['visitors'=>'VAS','invitations'=>'VAS','entry-exit'=>'VAS','students'=>'PAS','pickup'=>'PAS','vts'=>'VTS','pbs'=>'PBS'][$section] ?? null;
        if ($requiredModule && ! $isSuperAdmin) abort_unless($user->organization?->hasModule($requiredModule), 403, 'This module requires an active subscription.');
        $org = $user->organization_id;
        $config = $this->config($section);
        abort_unless($config, 404);

        $query = match ($section) {
            'organizations' => $this->tenant(DB::table('organizations'), $org, $isSuperAdmin, 'organizations.id')->select('id', 'name', 'type', 'status', 'created_at'),
            'people' => $this->tenant(DB::table('users')->leftJoin('organizations', 'organizations.id', '=', 'users.organization_id'), $org, $isSuperAdmin, 'users.organization_id')->select('users.id', 'users.name', 'users.email', 'users.phone', 'users.role', 'users.status', 'organizations.name as organization'),
            'visitors' => $this->tenant(DB::table('visitors')->leftJoin('organizations', 'organizations.id', '=', 'visitors.organization_id'), $org, $isSuperAdmin, 'visitors.organization_id')->select('visitors.id', 'visitors.first_name', 'visitors.last_name', 'visitors.phone', 'visitors.email', 'visitors.watchlisted', 'organizations.name as organization'),
            'invitations' => $this->tenant(DB::table('visitor_invitations')->join('visitors', 'visitors.id', '=', 'visitor_invitations.visitor_id')->leftJoin('organizations', 'organizations.id', '=', 'visitor_invitations.organization_id'), $org, $isSuperAdmin, 'visitor_invitations.organization_id')->select('visitor_invitations.id', 'visitors.first_name', 'visitors.last_name', 'purpose', 'invitation_code', 'valid_until', 'visitor_invitations.status', 'organizations.name as organization'),
            'entry-exit' => $this->tenant(DB::table('visitor_entries')->join('visitors', 'visitors.id', '=', 'visitor_entries.visitor_id')->leftJoin('organizations', 'organizations.id', '=', 'visitor_entries.organization_id'), $org, $isSuperAdmin, 'visitor_entries.organization_id')->select('visitor_entries.id', 'visitors.first_name', 'visitors.last_name', 'check_in_at', 'check_out_at', 'visitor_entries.status', 'organizations.name as organization'),
            'students' => $this->tenant(DB::table('students')->leftJoin('organizations', 'organizations.id', '=', 'students.organization_id'), $org, $isSuperAdmin, 'students.organization_id')->select('students.id', 'student_number', 'first_name', 'last_name', 'class', 'students.status', 'organizations.name as organization'),
            'pickup' => $this->tenant(DB::table('pickup_authorizations')->join('students', 'students.id', '=', 'pickup_authorizations.student_id')->join('pickup_persons', 'pickup_persons.id', '=', 'pickup_authorizations.pickup_person_id')->leftJoin('organizations', 'organizations.id', '=', 'pickup_authorizations.organization_id'), $org, $isSuperAdmin, 'pickup_authorizations.organization_id')->select('pickup_authorizations.id', 'students.first_name as student_first_name', 'students.last_name as student_last_name', 'pickup_persons.first_name as pickup_first_name', 'pickup_persons.last_name as pickup_last_name', 'authorization_code', 'valid_until', 'pickup_authorizations.status', 'organizations.name as organization'),
            'reports', 'audit-logs' => $this->auditQuery($org, $isSuperAdmin, $section === 'audit-logs'),
            'vts' => $this->tenant(DB::table('vehicles')->leftJoin('organizations', 'organizations.id', '=', 'vehicles.organization_id')->leftJoin('vehicle_telemetry', function ($join) { $join->on('vehicle_telemetry.vehicle_id', '=', 'vehicles.id')->whereRaw('vehicle_telemetry.id = (select max(vt.id) from vehicle_telemetry vt where vt.vehicle_id = vehicles.id)'); }), $org, $isSuperAdmin, 'vehicles.organization_id')->select('vehicles.id', 'vehicles.name', 'registration_number', 'driver_name', 'vehicles.status', 'speed', 'battery_level', 'vehicles.last_seen_at', 'organizations.name as organization'),
            'pbs' => $this->tenant(DB::table('panic_incidents')->leftJoin('organizations', 'organizations.id', '=', 'panic_incidents.organization_id'), $org, $isSuperAdmin, 'panic_incidents.organization_id')->select('panic_incidents.id', 'uuid', 'source', 'location_name', 'severity', 'panic_incidents.status', 'panic_incidents.created_at', 'organizations.name as organization'),
        };

        return Inertia::render('Portal/Index', [
            'section' => $section, 'config' => $config, 'records' => $query->limit(100)->get(),
            'summary' => $this->summary($org, $isSuperAdmin), 'isSuperAdmin' => $isSuperAdmin,
            'options' => $this->options($section, $org, $isSuperAdmin),
            'canCreate' => $this->canCreate($user->role, $section),
        ]);
    }

    public function storeOrganization(Request $request)
    {
        abort_unless($request->user()->role === 'super_admin', 403);
        $data = $request->validate(['name' => 'required|string|max:160', 'type' => 'required|in:school,estate,company,hospital,church,hotel,government,event,industrial', 'admin_name' => 'required|string|max:160', 'admin_email' => 'required|email|unique:users,email']);
        $organization = DB::transaction(function () use ($data) {
            $organization = Organization::create(['name' => $data['name'], 'slug' => Str::slug($data['name']).'-'.Str::lower(Str::random(5)), 'type' => $data['type'], 'status' => 'active']);
            $organization->modules()->attach(DB::table('modules')->where('available', true)->pluck('id'), ['enabled' => true, 'starts_at' => now()]);
            User::create(['organization_id' => $organization->id, 'name' => $data['admin_name'], 'email' => $data['admin_email'], 'password' => Hash::make(Str::password(16)), 'role' => 'organization_admin', 'status' => 'active', 'email_verified_at' => now()]);
            return $organization;
        });
        AuditService::record($request, 'organization.created', $organization, $organization->toArray());
        return back()->with('success', 'Organization and administrator created. Send the administrator a password-reset link.');
    }

    public function storePerson(Request $request)
    {
        abort_unless(in_array($request->user()->role, ['super_admin', 'organization_admin'], true), 403);
        $data = $request->validate(['organization_id' => 'nullable|integer|exists:organizations,id', 'name' => 'required|string|max:160', 'email' => 'required|email|unique:users,email', 'phone' => 'nullable|string|max:30', 'role' => 'required|in:organization_admin,security_officer,staff,resident,parent,guardian', 'password' => 'required|string|min:8']);
        $organizationId = $this->authorizedOrganization($request, $data['organization_id'] ?? null);
        $user = User::create(array_merge($data, ['organization_id' => $organizationId, 'status' => 'active', 'email_verified_at' => now()]));
        AuditService::record($request, 'user.created', $user, ['name' => $user->name, 'role' => $user->role]);
        return back()->with('success', 'Person added successfully.');
    }

    public function storeVisitor(Request $request)
    {
        $this->requireSubscribedModule($request, 'VAS');
        $this->requireTenantManager($request);
        $data = $request->validate(['organization_id' => 'nullable|integer|exists:organizations,id', 'first_name' => 'required|string|max:80', 'last_name' => 'required|string|max:80', 'phone' => 'required|string|max:30', 'email' => 'nullable|email']);
        $visitor = Visitor::create(array_merge($data, ['organization_id' => $this->authorizedOrganization($request, $data['organization_id'] ?? null)]));
        AuditService::record($request, 'visitor.created', $visitor, $visitor->toArray());
        return back()->with('success', 'Visitor added successfully.');
    }

    public function storeInvitation(Request $request)
    {
        $this->requireSubscribedModule($request, 'VAS');
        $this->requireTenantManager($request);
        $data = $request->validate(['visitor_id' => 'required|integer|exists:visitors,id', 'purpose' => 'required|string|max:160', 'valid_from' => 'required|date', 'valid_until' => 'required|date|after:valid_from', 'entry_type' => 'required|in:single,multiple,recurring', 'maximum_entries' => 'required|integer|min:1|max:100']);
        $visitor = Visitor::findOrFail($data['visitor_id']);
        abort_unless($request->user()->role === 'super_admin' || $visitor->organization_id === $request->user()->organization_id, 403);
        $hostId = $request->user()->organization_id === $visitor->organization_id ? $request->user()->id : User::where('organization_id', $visitor->organization_id)->where('role', 'organization_admin')->value('id');
        abort_unless($hostId, 422, 'The organization needs an administrator before creating invitations.');
        $invitation = VisitorInvitation::create($data + ['uuid' => (string) Str::uuid(), 'organization_id' => $visitor->organization_id, 'host_user_id' => $hostId, 'invitation_code' => strtoupper(Str::random(6)), 'qr_token' => hash('sha256', Str::random(64)), 'status' => 'active']);
        AuditService::record($request, 'visitor.invitation.created', $invitation, $invitation->toArray());
        return back()->with('success', 'Secure visitor invitation created.');
    }

    public function storeStudent(Request $request)
    {
        $this->requireSubscribedModule($request, 'PAS');
        $this->requireTenantManager($request);
        $data = $request->validate(['organization_id' => 'nullable|integer|exists:organizations,id', 'student_number' => 'required|string|max:50', 'first_name' => 'required|string|max:80', 'last_name' => 'required|string|max:80', 'class' => 'required|string|max:80']);
        $student = Student::create(array_merge($data, ['organization_id' => $this->authorizedOrganization($request, $data['organization_id'] ?? null), 'status' => 'active']));
        AuditService::record($request, 'student.created', $student, $student->toArray());
        return back()->with('success', 'Student record created.');
    }

    public function storePickup(Request $request)
    {
        $this->requireSubscribedModule($request, 'PAS');
        $this->requireTenantManager($request);
        $data = $request->validate(['student_id' => 'required|integer|exists:students,id', 'first_name' => 'required|string|max:80', 'last_name' => 'required|string|max:80', 'phone' => 'required|string|max:30', 'relationship' => 'required|string|max:80', 'authorization_type' => 'required|in:one_time,temporary,permanent,emergency', 'valid_until' => 'required|date|after:now']);
        $student = Student::findOrFail($data['student_id']);
        abort_unless($request->user()->role === 'super_admin' || $student->organization_id === $request->user()->organization_id, 403);
        $authorization = DB::transaction(function () use ($data, $student, $request) {
            $person = PickupPerson::create(['organization_id' => $student->organization_id, 'first_name' => $data['first_name'], 'last_name' => $data['last_name'], 'phone' => $data['phone'], 'relationship' => $data['relationship'], 'status' => 'active']);
            $guardianId = $request->user()->organization_id === $student->organization_id ? $request->user()->id : User::where('organization_id', $student->organization_id)->where('role', 'organization_admin')->value('id');
            abort_unless($guardianId, 422, 'The organization needs an administrator before creating pickup authorizations.');
            return PickupAuthorization::create(['uuid' => (string) Str::uuid(), 'organization_id' => $student->organization_id, 'student_id' => $student->id, 'guardian_user_id' => $guardianId, 'pickup_person_id' => $person->id, 'authorization_code' => strtoupper(Str::random(6)), 'qr_token' => hash('sha256', Str::random(64)), 'valid_from' => now(), 'valid_until' => $data['valid_until'], 'authorization_type' => $data['authorization_type'], 'status' => 'active']);
        });
        AuditService::record($request, 'pickup.authorized', $authorization, $authorization->toArray());
        return back()->with('success', 'Pickup person and authorization created.');
    }

    public function verifyAccess(Request $request)
    {
        $this->requireSubscribedModule($request, 'VAS');
        abort_unless(in_array($request->user()->role, ['super_admin', 'organization_admin', 'security_officer'], true), 403);
        $token = strtoupper($request->validate(['token' => 'required|string|max:128'])['token']);
        $invitation = VisitorInvitation::where(fn ($query) => $query->where('invitation_code', $token)->orWhere('qr_token', $request->input('token')))->first();
        abort_unless($invitation && ($request->user()->role === 'super_admin' || $invitation->organization_id === $request->user()->organization_id), 404, 'Pass not found.');
        $valid = $invitation->status === 'active' && $invitation->valid_until->isFuture() && $invitation->entry_count < $invitation->maximum_entries;
        return back()->with($valid ? 'success' : 'error', $valid ? 'Visitor pass is valid and access may be granted.' : 'Visitor pass is expired, revoked, or already used.');
    }

    public function storeVehicle(Request $request)
    {
        $this->requireSubscribedModule($request, 'VTS');
        abort_unless(in_array($request->user()->role, ['super_admin', 'organization_admin'], true), 403);
        $data = $request->validate(['name' => 'required|string|max:100', 'registration_number' => 'required|string|max:30', 'device_uid' => 'required|string|max:100|unique:vehicles', 'driver_name' => 'nullable|string|max:100']);
        $vehicle = Vehicle::create($data + ['organization_id' => $this->targetOrganization($request), 'type' => 'fleet']);
        AuditService::record($request, 'vehicle.created', $vehicle, $vehicle->toArray());
        return back()->with('success', 'Vehicle added to the fleet.');
    }

    public function triggerPanic(Request $request)
    {
        $this->requireSubscribedModule($request, 'PBS');
        $data = $request->validate(['location_name' => 'required|string|max:160', 'message' => 'nullable|string|max:500']);
        $incident = PanicIncident::create($data + ['uuid' => (string) Str::uuid(), 'organization_id' => $this->targetOrganization($request), 'triggered_by' => $request->user()->id, 'source' => 'web', 'severity' => 'critical', 'status' => 'active']);
        AuditService::record($request, 'panic.triggered', $incident, $incident->toArray());
        return back()->with('success', 'Emergency alert activated.');
    }

    public function updatePanic(Request $request, PanicIncident $incident)
    {
        $this->requireSubscribedModule($request, 'PBS');
        abort_unless($request->user()->role === 'super_admin' || $incident->organization_id === $request->user()->organization_id, 403);
        $status = $request->validate(['status' => 'required|in:acknowledged,resolved'])['status'];
        $incident->update(['status' => $status, $status.'_at' => now(), $status.'_by' => $request->user()->id]);
        AuditService::record($request, 'panic.'.$status, $incident, ['status' => $status]);
        return back();
    }

    private function tenant(Builder $query, ?int $org, bool $global, string $column): Builder { return $global ? $query : $query->where($column, $org); }
    private function targetOrganization(Request $request): int { $id = $request->user()->organization_id ?? DB::table('organizations')->where('status', 'active')->value('id'); abort_unless($id, 422, 'Create an organization before adding operational data.'); return $id; }
    private function authorizedOrganization(Request $request, ?int $requested): int { if ($request->user()->role === 'super_admin') { abort_unless($requested, 422, 'Select an organization.'); return $requested; } abort_unless($request->user()->organization_id, 403); return $request->user()->organization_id; }
    private function requireTenantManager(Request $request): void { abort_unless(in_array($request->user()->role, ['super_admin', 'organization_admin'], true), 403); }
    private function requireSubscribedModule(Request $request, string $module): void { if ($request->user()->role !== 'super_admin') abort_unless($request->user()->organization?->hasModule($module), 403, 'This module requires an active subscription.'); }
    private function allowedSections(string $role): array
    {
        return match ($role) {
            'super_admin' => ['organizations','people','visitors','invitations','entry-exit','students','pickup','reports','audit-logs','vts','pbs'],
            'organization_admin' => ['people','visitors','invitations','entry-exit','students','pickup','reports','audit-logs','vts','pbs'],
            'security_officer' => ['entry-exit','pickup','vts','pbs'],
            'staff' => ['visitors','invitations','vts','pbs'],
            'resident' => ['visitors','invitations','pbs'],
            'parent', 'guardian' => ['students','pickup','pbs'],
            default => [],
        };
    }
    private function canCreate(string $role, string $section): bool
    {
        if (in_array($section, ['reports', 'audit-logs'], true)) return true;
        if ($role === 'super_admin') return true;
        if ($role === 'organization_admin') return $section !== 'organizations';
        if ($role === 'security_officer') return in_array($section, ['entry-exit', 'pbs'], true);
        return $section === 'pbs';
    }
    private function options(string $section, ?int $org, bool $global): array
    {
        $organizations = $global ? DB::table('organizations')->where('status', 'active')->get(['id', 'name']) : collect();
        $visitors = DB::table('visitors')->when(! $global, fn ($q) => $q->where('organization_id', $org))->get(['id', 'first_name', 'last_name', 'organization_id']);
        $students = DB::table('students')->when(! $global, fn ($q) => $q->where('organization_id', $org))->where('status', 'active')->get(['id', 'first_name', 'last_name', 'class', 'organization_id']);
        return ['organizations' => $organizations, 'visitors' => $section === 'invitations' ? $visitors : [], 'students' => $section === 'pickup' ? $students : []];
    }
    private function auditQuery(?int $org, bool $global, bool $detailed): Builder
    {
        $query = DB::table('audit_logs')->leftJoin('users', 'users.id', '=', 'audit_logs.user_id')->leftJoin('organizations', 'organizations.id', '=', 'audit_logs.organization_id');
        $this->tenant($query, $org, $global, 'audit_logs.organization_id');
        $columns = ['audit_logs.id', 'users.name as administrator', 'organizations.name as organization', 'action', 'entity_type', 'entity_id', 'audit_logs.created_at'];
        if ($detailed) $columns[] = 'ip_address';
        return $query->select($columns)->orderByDesc('audit_logs.created_at');
    }
    private function summary(?int $org, bool $global): array
    {
        $count = fn (string $table, ?callable $extra = null) => tap($this->tenant(DB::table($table), $org, $global, $table.'.organization_id'), fn ($q) => $extra && $extra($q))->count();
        return ['visitors' => $count('visitors'), 'students' => $count('students'), 'vehicles' => $count('vehicles'), 'activeIncidents' => $count('panic_incidents', fn ($q) => $q->where('status', 'active'))];
    }
    private function config(string $section): ?array { return collect([
        'organizations'=>['title'=>'Organizations','description'=>'Manage NEMTRACK tenants and their service status.','action'=>'Add organization'], 'people'=>['title'=>'People & access','description'=>'Organization members, roles, and security officers.','action'=>'Invite person'], 'visitors'=>['title'=>'Visitors','description'=>'Visitor directory and watchlist management.','action'=>'Add visitor'], 'invitations'=>['title'=>'Visitor invitations','description'=>'Create, monitor, and revoke secure visitor passes.','action'=>'Create invitation'], 'entry-exit'=>['title'=>'Entry & exit','description'=>'Live gate movements across all access points.','action'=>'Scan pass'], 'students'=>['title'=>'Students','description'=>'Student records available to the pickup authorization system.','action'=>'Add student'], 'pickup'=>['title'=>'Pickup authorization','description'=>'Verified pickup people, active approvals, and release history.','action'=>'Authorize pickup'], 'reports'=>['title'=>'Reports','description'=>'Operational activity across every active NEMTRACK module.','action'=>'Export report'], 'audit-logs'=>['title'=>'Audit logs','description'=>'Administrator actions and immutable accountability records.','action'=>'Export audit'], 'vts'=>['title'=>'Vehicle tracking','description'=>'Fleet location, device health, speed, and driver status.','action'=>'Add vehicle'], 'pbs'=>['title'=>'Panic & emergency','description'=>'Activate, acknowledge, and resolve emergency incidents.','action'=>'Activate panic'],
    ])->get($section); }
}
