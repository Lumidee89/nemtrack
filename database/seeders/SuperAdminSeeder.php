<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => env('SUPER_ADMIN_EMAIL', 'superadmin@nemtrack.test')],
            [
                'organization_id' => null,
                'name' => env('SUPER_ADMIN_NAME', 'NEMTRACK Super Admin'),
                'first_name' => 'NEMTRACK',
                'last_name' => 'Administrator',
                'password' => env('SUPER_ADMIN_PASSWORD', 'password'),
                'role' => 'super_admin',
                'status' => 'active',
                'email_verified_at' => now(),
            ],
        );
    }
}
