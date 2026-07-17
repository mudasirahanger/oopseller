<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brands', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $t->foreignId('client_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->string('registry_status')->default('unknown');
            $t->timestamps();
            $t->unique(['client_id', 'name']);
        });
        Schema::create('products', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $t->foreignId('client_id')->constrained()->cascadeOnDelete();
            $t->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $t->string('asin', 20);
            $t->string('sku')->nullable();
            $t->string('name');
            $t->string('product_type')->nullable();
            $t->string('status')->default('active');
            $t->text('image_url')->nullable();
            $t->json('metadata')->nullable();
            $t->timestamps();
            $t->unique(['client_id', 'asin']);
            $t->index(['organization_id', 'status']);
        });
        Schema::create('listings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $t->foreignId('client_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_id')->constrained()->cascadeOnDelete();
            $t->string('marketplace_id', 20);
            $t->text('title')->nullable();
            $t->json('bullet_points')->nullable();
            $t->longText('description')->nullable();
            $t->json('backend_terms')->nullable();
            $t->json('attributes')->nullable();
            $t->unsignedTinyInteger('image_count')->default(0);
            $t->string('a_plus_status')->default('not_started');
            $t->string('status')->default('active');
            $t->timestamp('last_imported_at')->nullable();
            $t->timestamps();
            $t->unique(['product_id', 'marketplace_id']);
        });
        Schema::create('listing_versions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $t->foreignId('client_id')->constrained()->cascadeOnDelete();
            $t->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->unsignedInteger('version');
            $t->string('source')->default('manual');
            $t->json('content');
            $t->json('target_keywords')->nullable();
            $t->text('change_summary')->nullable();
            $t->timestamp('published_at')->nullable();
            $t->timestamps();
            $t->unique(['listing_id', 'version']);
        });
        Schema::create('listing_audits', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $t->foreignId('client_id')->constrained()->cascadeOnDelete();
            $t->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $t->unsignedTinyInteger('score');
            $t->json('breakdown');
            $t->json('recommendations');
            $t->timestamp('audited_at');
            $t->timestamps();
            $t->index(['organization_id', 'score']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('listing_audits');
        Schema::dropIfExists('listing_versions');
        Schema::dropIfExists('listings');
        Schema::dropIfExists('products');
        Schema::dropIfExists('brands');
    }
};
