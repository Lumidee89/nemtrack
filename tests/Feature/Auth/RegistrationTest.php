<?php

namespace Tests\Feature\Auth;

use App\Models\Module;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        Module::create(['code' => 'VTS', 'name' => 'Vehicle Tracking System', 'available' => true]);
        $response = $this->post('/register', [
            'name' => 'Test User',
            'organization_name' => 'Test Organization',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'modules' => [['code' => 'VTS', 'cycle' => 'monthly']],
        ]);

        $this->assertAuthenticated();
        $order = \App\Models\SubscriptionOrder::firstOrFail();
        $response->assertRedirect(route('subscriptions.checkout', $order));
        $this->assertDatabaseHas('subscription_orders', ['amount_kobo' => 250000, 'status' => 'pending']);
        $this->assertDatabaseHas('organization_modules', ['enabled' => false]);

        $this->get('/dashboard')->assertRedirect(route('subscriptions.checkout', $order));
    }
}
