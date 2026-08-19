<?php

namespace Database\Seeders;

use App\Models\Module;
use App\Models\Organization;
use App\Models\PickupPerson;
use App\Models\Student;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleTelemetry;
use App\Models\PanicDevice;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(SuperAdminSeeder::class);

        $organization = Organization::create(['name' => 'Greenfield Academy', 'slug' => 'greenfield-academy', 'type' => 'school']);
        $modules = collect([
            ['code' => 'VAS', 'name' => 'Visitor Authorization System', 'available' => true],
            ['code' => 'PAS', 'name' => 'Pick-up Authorization System', 'available' => true],
            ['code' => 'VTS', 'name' => 'Vehicle Tracking System', 'available' => true],
            ['code' => 'PBS', 'name' => 'Panic Button System', 'available' => true],
        ])->map(fn ($module) => Module::create($module));
        $organization->modules()->attach($modules->pluck('id'), ['enabled' => true, 'starts_at' => now()]);
        User::create(['organization_id' => $organization->id, 'name' => 'Amara Okafor', 'first_name' => 'Amara', 'last_name' => 'Okafor', 'email' => 'admin@nemtrack.test', 'phone' => '+234 800 555 0142', 'password' => 'password', 'role' => 'organization_admin', 'email_verified_at' => now()]);
        $this->call(RoleDemoUserSeeder::class);
        Student::create(['organization_id' => $organization->id, 'student_number' => 'GF-2026-0142', 'first_name' => 'Maya', 'last_name' => 'Okafor', 'class' => 'Primary 4A']);
        PickupPerson::create(['organization_id' => $organization->id, 'first_name' => 'Chidi', 'last_name' => 'Okafor', 'phone' => '+234 800 555 0198', 'relationship' => 'Uncle']);
        $vehicle = Vehicle::create(['organization_id'=>$organization->id,'name'=>'School Bus 04','registration_number'=>'LAG-204-NM','type'=>'school_bus','device_uid'=>'NMT-GPS-0004','driver_name'=>'Samuel Adeyemi','status'=>'online','last_seen_at'=>now()]);
        VehicleTelemetry::create(['organization_id'=>$organization->id,'vehicle_id'=>$vehicle->id,'latitude'=>6.5243793,'longitude'=>3.3792057,'speed'=>34.5,'heading'=>92,'battery_level'=>87,'ignition'=>'on','recorded_at'=>now()]);
        PanicDevice::create(['organization_id'=>$organization->id,'name'=>'Main Gate Panic Button','device_uid'=>'NMT-PBS-0001','location_name'=>'Main Gate Security Post','assigned_to'=>'Security team','status'=>'online','last_seen_at'=>now()]);
    }
}
