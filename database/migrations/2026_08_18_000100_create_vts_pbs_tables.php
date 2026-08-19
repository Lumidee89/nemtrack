<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('registration_number');
            $table->string('type')->default('fleet');
            $table->string('device_uid')->unique();
            $table->string('driver_name')->nullable();
            $table->string('status')->default('offline');
            $table->dateTime('last_seen_at')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'registration_number']);
        });
        Schema::create('vehicle_telemetry', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->decimal('speed', 8, 2)->default(0);
            $table->decimal('heading', 6, 2)->nullable();
            $table->decimal('battery_level', 5, 2)->nullable();
            $table->string('ignition')->default('off');
            $table->dateTime('recorded_at');
            $table->timestamps();
            $table->index(['vehicle_id', 'recorded_at']);
        });
        Schema::create('panic_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('device_uid')->unique();
            $table->string('location_name');
            $table->string('assigned_to')->nullable();
            $table->string('status')->default('online');
            $table->dateTime('last_seen_at')->nullable();
            $table->timestamps();
        });
        Schema::create('panic_incidents', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('panic_device_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('triggered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source')->default('mobile');
            $table->string('severity')->default('critical');
            $table->string('location_name')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->text('message')->nullable();
            $table->string('status')->default('active');
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('acknowledged_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'status', 'created_at']);
        });
        DB::table('modules')->whereIn('code', ['VTS', 'PBS'])->update(['available' => true, 'updated_at' => now()]);
        foreach (DB::table('organizations')->pluck('id') as $organizationId) {
            foreach (DB::table('modules')->whereIn('code', ['VTS', 'PBS'])->pluck('id') as $moduleId) {
                DB::table('organization_modules')->insertOrIgnore(['organization_id' => $organizationId, 'module_id' => $moduleId, 'enabled' => true, 'starts_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('panic_incidents'); Schema::dropIfExists('panic_devices');
        Schema::dropIfExists('vehicle_telemetry'); Schema::dropIfExists('vehicles');
    }
};
