<?php
namespace Tests\Feature\Api;

use App\Models\Module;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobilityAndPanicTest extends TestCase
{
    use RefreshDatabase;
    private function operator(): User { $org=Organization::create(['name'=>'Safety School','slug'=>'safety-school']); foreach(['VAS','PAS','VTS','PBS'] as $code){$m=Module::create(['code'=>$code,'name'=>$code,'available'=>true]);$org->modules()->attach($m,['enabled'=>true]);} return User::factory()->create(['organization_id'=>$org->id,'role'=>'organization_admin']); }
    public function test_vehicle_and_telemetry_flow(): void { $u=$this->operator();$vehicle=$this->actingAs($u)->postJson('/api/v1/vehicles',['name'=>'Bus 1','registration_number'=>'BUS-01','device_uid'=>'GPS-01'])->assertCreated()->json('data.vehicle');$this->postJson('/api/v1/vehicles/'.$vehicle['id'].'/telemetry',['latitude'=>6.52,'longitude'=>3.37,'speed'=>42,'battery_level'=>90])->assertCreated();$this->getJson('/api/v1/vehicles')->assertOk()->assertJsonPath('data.0.status','online'); }
    public function test_panic_incident_alerts_admin_security_and_super_admin(): void { $u=$this->operator();$security=User::factory()->create(['organization_id'=>$u->organization_id,'role'=>'security_officer']);$super=User::factory()->create(['organization_id'=>null,'role'=>'super_admin']);$incident=$this->actingAs($u)->postJson('/api/v1/panic/trigger',['location_name'=>'Main Gate','message'=>'Security assistance required'])->assertCreated()->json('data.incident');foreach([$u->id,$security->id,$super->id] as $recipient){$this->assertDatabaseHas('app_notifications',['user_id'=>$recipient,'type'=>'critical','read_at'=>null]);}$this->patchJson('/api/v1/panic-incidents/'.$incident['id'],['status'=>'acknowledged'])->assertOk()->assertJsonPath('data.incident.status','acknowledged');$this->assertDatabaseHas('audit_logs',['action'=>'panic.acknowledged']); }
    public function test_organization_admin_sections_render_and_platform_section_is_forbidden(): void { $u=$this->operator();foreach(['people','visitors','invitations','entry-exit','students','pickup','reports','audit-logs','vts','pbs'] as $section){$this->actingAs($u)->get('/portal/'.$section)->assertOk();}$this->actingAs($u)->get('/portal/organizations')->assertForbidden(); }
    public function test_super_admin_can_render_global_dashboard_and_every_portal_section(): void
    {
        $this->operator();
        $superAdmin = User::factory()->create(['organization_id' => null, 'role' => 'super_admin']);
        $this->actingAs($superAdmin)->get('/dashboard')->assertOk()->assertInertia(fn ($page) => $page->component('SuperAdmin/Dashboard')->has('stats.organizations'));
        foreach (['organizations','people','visitors','invitations','entry-exit','students','pickup','reports','audit-logs','vts','pbs'] as $section) {
            $this->actingAs($superAdmin)->get('/portal/'.$section)->assertOk();
        }
    }

    public function test_mobile_dashboard_supports_every_role_and_rejects_forbidden_actions(): void
    {
        $admin = $this->operator();
        foreach (['organization_admin', 'security_officer', 'staff', 'resident', 'parent', 'guardian'] as $role) {
            $user = $role === 'organization_admin' ? $admin : User::factory()->create(['organization_id' => $admin->organization_id, 'role' => $role]);
            $this->actingAs($user)->getJson('/api/v1/mobile/dashboard')->assertOk()->assertJsonPath('data.role', $role);
        }
        $super = User::factory()->create(['organization_id' => null, 'role' => 'super_admin']);
        $this->actingAs($super)->getJson('/api/v1/mobile/dashboard')->assertOk()->assertJsonPath('data.role', 'super_admin');

        $security = User::factory()->create(['organization_id' => $admin->organization_id, 'role' => 'security_officer']);
        $this->actingAs($security)->postJson('/api/v1/visitor-invitations', [])->assertForbidden()->assertJsonPath('code', 'ROLE_ACCESS_DENIED');
    }
}
