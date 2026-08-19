<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_orders', function (Blueprint $table) {
            $table->string('paystack_access_code')->nullable()->after('status');
            $table->text('paystack_authorization_url')->nullable()->after('paystack_access_code');
        });
    }

    public function down(): void
    {
        Schema::table('subscription_orders', function (Blueprint $table) {
            $table->dropColumn(['paystack_access_code', 'paystack_authorization_url']);
        });
    }
};
