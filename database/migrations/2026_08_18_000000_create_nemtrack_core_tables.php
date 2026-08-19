<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('type')->default('school');
            $table->string('status')->default('active');
            $table->string('timezone')->default('Africa/Lagos');
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('organization_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->string('first_name')->nullable()->after('organization_id');
            $table->string('last_name')->nullable()->after('first_name');
            $table->string('phone')->nullable()->after('email');
            $table->string('role')->default('organization_admin');
            $table->string('status')->default('active');
        });

        Schema::create('modules', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->boolean('available')->default(true);
            $table->timestamps();
        });

        Schema::create('organization_modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('module_id')->constrained()->cascadeOnDelete();
            $table->boolean('enabled')->default(true);
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->unique(['organization_id', 'module_id']);
        });

        Schema::create('access_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type')->default('gate');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('visitors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('phone');
            $table->string('email')->nullable();
            $table->string('identification_type')->nullable();
            $table->string('identification_number')->nullable();
            $table->boolean('watchlisted')->default(false);
            $table->timestamps();
            $table->index(['organization_id', 'phone']);
        });

        Schema::create('visitor_invitations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('visitor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('host_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('invitation_code', 8)->unique();
            $table->string('qr_token', 64)->unique();
            $table->string('purpose');
            $table->dateTime('valid_from');
            $table->dateTime('valid_until');
            $table->string('entry_type')->default('single');
            $table->unsignedInteger('maximum_entries')->default(1);
            $table->unsignedInteger('entry_count')->default(0);
            $table->string('status')->default('active');
            $table->timestamps();
            $table->index(['organization_id', 'status', 'valid_until']);
        });

        Schema::create('visitor_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invitation_id')->constrained('visitor_invitations')->cascadeOnDelete();
            $table->foreignId('visitor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('access_point_id')->constrained()->cascadeOnDelete();
            $table->foreignId('checked_in_by')->constrained('users');
            $table->dateTime('check_in_at');
            $table->foreignId('checked_out_by')->nullable()->constrained('users');
            $table->dateTime('check_out_at')->nullable();
            $table->string('status')->default('inside');
            $table->timestamps();
        });

        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('student_number');
            $table->string('first_name');
            $table->string('last_name');
            $table->string('class');
            $table->string('status')->default('active');
            $table->timestamps();
            $table->unique(['organization_id', 'student_number']);
        });

        Schema::create('pickup_persons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('phone');
            $table->string('relationship');
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::create('pickup_authorizations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('guardian_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('pickup_person_id')->constrained('pickup_persons')->cascadeOnDelete();
            $table->string('authorization_code', 8)->unique();
            $table->string('qr_token', 64)->unique();
            $table->dateTime('valid_from');
            $table->dateTime('valid_until');
            $table->string('authorization_type')->default('one_time');
            $table->string('status')->default('active');
            $table->dateTime('used_at')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'status', 'valid_until']);
        });

        Schema::create('pickup_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('authorization_id')->constrained('pickup_authorizations')->cascadeOnDelete();
            $table->foreignId('verified_by')->constrained('users');
            $table->dateTime('verified_at');
            $table->dateTime('released_at')->nullable();
            $table->string('status')->default('verified');
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->string('entity_type');
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('pickup_transactions');
        Schema::dropIfExists('pickup_authorizations');
        Schema::dropIfExists('pickup_persons');
        Schema::dropIfExists('students');
        Schema::dropIfExists('visitor_entries');
        Schema::dropIfExists('visitor_invitations');
        Schema::dropIfExists('visitors');
        Schema::dropIfExists('access_points');
        Schema::dropIfExists('organization_modules');
        Schema::dropIfExists('modules');
        Schema::table('users', fn (Blueprint $table) => $table->dropConstrainedForeignId('organization_id'));
        Schema::dropIfExists('organizations');
    }
};
