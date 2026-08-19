<?php

namespace Tests\Feature\Api;

use App\Models\Module;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NemtrackApiTest extends TestCase
{
    use RefreshDatabase;

    private function userWithModule(string $code = 'VAS'): User
    {
        $organization = Organization::create(['name' => 'Test School', 'slug' => 'test-school']);
        $module = Module::create(['code' => $code, 'name' => $code, 'available' => true]);
        $organization->modules()->attach($module, ['enabled' => true]);
        return User::factory()->create(['organization_id' => $organization->id, 'role' => 'organization_admin']);
    }

    public function test_tenant_user_can_create_and_verify_visitor_invitation(): void
    {
        $user = $this->userWithModule();
        $response = $this->actingAs($user)->postJson('/api/v1/visitor-invitations', [
            'first_name' => 'Ada', 'last_name' => 'Mensah', 'phone' => '+234800000001',
            'purpose' => 'Parent meeting', 'valid_from' => now()->addMinute()->toIso8601String(),
            'valid_until' => now()->addHours(2)->toIso8601String(),
        ])->assertCreated()->assertJsonPath('success', true);

        $this->actingAs($user)->postJson('/api/v1/visitor-access/verify', [
            'token' => $response->json('data.invitation.invitation_code'),
        ])->assertOk()->assertJsonPath('message', 'Visitor pass is valid.');
        $this->assertDatabaseHas('audit_logs', ['action' => 'visitor.invitation.created', 'organization_id' => $user->organization_id]);
    }

    public function test_disabled_module_is_denied(): void
    {
        $organization = Organization::create(['name' => 'No VAS School', 'slug' => 'no-vas']);
        $user = User::factory()->create(['organization_id' => $organization->id]);
        $this->actingAs($user)->getJson('/api/v1/visitors')->assertForbidden()->assertJsonPath('code', 'MODULE_NOT_ENABLED');
    }
}
