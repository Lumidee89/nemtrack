<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::create('subscription_orders',function(Blueprint $table){$table->id();$table->foreignId('organization_id')->constrained()->cascadeOnDelete();$table->foreignId('user_id')->constrained()->cascadeOnDelete();$table->string('reference')->unique();$table->json('items');$table->unsignedBigInteger('amount_kobo');$table->string('currency',3)->default('NGN');$table->string('status')->default('pending');$table->string('paystack_transaction_id')->nullable();$table->timestamp('paid_at')->nullable();$table->timestamps();$table->index(['organization_id','status']);});
  Schema::create('module_subscriptions',function(Blueprint $table){$table->id();$table->foreignId('organization_id')->constrained()->cascadeOnDelete();$table->foreignId('module_id')->constrained()->cascadeOnDelete();$table->foreignId('subscription_order_id')->constrained()->cascadeOnDelete();$table->string('billing_cycle');$table->unsignedBigInteger('amount_kobo');$table->string('status')->default('pending');$table->timestamp('starts_at')->nullable();$table->timestamp('expires_at')->nullable();$table->timestamps();$table->index(['organization_id','module_id','status']);});
 }
 public function down(): void {Schema::dropIfExists('module_subscriptions');Schema::dropIfExists('subscription_orders');}
};
