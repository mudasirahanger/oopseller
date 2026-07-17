<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->string('slug');
            $t->string('contact_name')->nullable();
            $t->string('contact_email')->nullable();
            $t->string('status')->default('onboarding');
            $t->text('notes')->nullable();
            $t->timestamps();
            $t->unique(['organization_id', 'slug']);
            $t->index(['organization_id', 'status']);
        });
        Schema::create('marketplaces', function (Blueprint $t) {
            $t->id();
            $t->string('amazon_marketplace_id')->unique();
            $t->char('country_code', 2);
            $t->string('name');
            $t->char('currency', 3);
            $t->string('domain');
            $t->string('region');
            $t->timestamps();
        });
        Schema::create('amazon_accounts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $t->foreignId('client_id')->constrained()->cascadeOnDelete();
            $t->string('seller_id')->nullable();
            $t->string('name');
            $t->string('region')->default('eu');
            $t->text('refresh_token')->nullable();
            $t->string('status')->default('pending');
            $t->timestamp('authorized_at')->nullable();
            $t->timestamp('last_synced_at')->nullable();
            $t->timestamps();
            $t->unique(['organization_id', 'seller_id']);
        });
        Schema::create('amazon_account_marketplace', function (Blueprint $t) {
            $t->foreignId('amazon_account_id')->constrained()->cascadeOnDelete();
            $t->foreignId('marketplace_id')->constrained()->cascadeOnDelete();
            $t->boolean('enabled')->default(true);
            $t->timestamps();
            $t->primary(['amazon_account_id', 'marketplace_id']);
        });
        Schema::create('personal_access_tokens', function (Blueprint $t) {
            $t->id();
            $t->morphs('tokenable');
            $t->string('name');
            $t->string('token', 64)->unique();
            $t->text('abilities')->nullable();
            $t->timestamp('last_used_at')->nullable();
            $t->timestamp('expires_at')->nullable()->index();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('amazon_account_marketplace');
        Schema::dropIfExists('amazon_accounts');
        Schema::dropIfExists('marketplaces');
        Schema::dropIfExists('clients');
    }
};
