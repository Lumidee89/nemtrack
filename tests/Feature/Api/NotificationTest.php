<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_list_and_mark_only_their_notifications_as_read(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $mine = DB::table('app_notifications')->insertGetId(['user_id' => $user->id, 'type' => 'activity', 'title' => 'Mine', 'message' => 'Private update', 'created_at' => now(), 'updated_at' => now()]);
        $theirs = DB::table('app_notifications')->insertGetId(['user_id' => $other->id, 'type' => 'activity', 'title' => 'Theirs', 'message' => 'Another update', 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($user)->getJson('/api/v1/notifications')->assertOk()->assertJsonPath('data.unread_count', 1)->assertJsonCount(1, 'data.items')->assertJsonPath('data.items.0.title', 'Mine');
        $this->patchJson('/api/v1/notifications/'.$theirs.'/read')->assertOk();
        $this->assertDatabaseHas('app_notifications', ['id' => $theirs, 'read_at' => null]);
        $this->patchJson('/api/v1/notifications/'.$mine.'/read')->assertOk();
        $this->assertDatabaseMissing('app_notifications', ['id' => $mine, 'read_at' => null]);
    }
}
