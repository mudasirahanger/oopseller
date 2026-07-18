<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $t->foreignId('client_id')->constrained()->cascadeOnDelete();
            $t->foreignId('channel_account_id')->nullable()->constrained('channel_accounts')->nullOnDelete();
            $t->string('platform', 30);
            $t->string('external_order_id', 100);
            $t->string('status', 30)->default('pending');
            $t->timestamp('order_date');
            $t->string('fulfillment_type', 40)->nullable();
            $t->string('marketplace_id', 20)->nullable();
            $t->json('items')->nullable();
            $t->unsignedInteger('units')->default(0);
            $t->decimal('subtotal', 14, 2)->default(0);
            $t->decimal('tax', 14, 2)->default(0);
            $t->decimal('shipping', 14, 2)->default(0);
            $t->decimal('total', 14, 2)->default(0);
            $t->string('currency', 3)->default('INR');
            $t->string('customer_city', 120)->nullable();
            $t->string('customer_state', 120)->nullable();
            $t->string('customer_pincode', 20)->nullable();
            $t->json('metadata')->nullable();
            $t->timestamps();

            $t->unique(['platform', 'external_order_id', 'client_id'], 'orders_platform_external_unique');
            $t->index(['organization_id', 'order_date']);
            $t->index(['client_id', 'order_date']);
            $t->index(['channel_account_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
