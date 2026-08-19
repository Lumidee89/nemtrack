<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;

class RoleDemoUserSeeder extends Seeder
{
    public function run(): void
    {
        $organization = Organization::where('slug', 'greenfield-academy')->first() ?? Organization::firstOrFail();
        foreach ([
            ['Security Officer', 'security@nemtrack.test', 'security_officer'],
            ['Chinwe Staff', 'staff@nemtrack.test', 'staff'],
            ['Tunde Resident', 'resident@nemtrack.test', 'resident'],
            ['Ifeoma Parent', 'parent@nemtrack.test', 'parent'],
            ['David Guardian', 'guardian@nemtrack.test', 'guardian'],
        ] as [$name, $email, $role]) {
            User::updateOrCreate(['email' => $email], ['organization_id' => $organization->id, 'name' => $name, 'password' => 'password', 'role' => $role, 'status' => 'active', 'email_verified_at' => now()]);
        }
    }
}
