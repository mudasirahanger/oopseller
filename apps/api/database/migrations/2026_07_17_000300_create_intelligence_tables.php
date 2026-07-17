<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('keyword_projects', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $t->foreignId('client_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_id')->constrained()->cascadeOnDelete();
            $t->string('marketplace_id', 20);
            $t->string('name');
            $t->string('language', 10)->default('en');
            $t->string('status')->default('active');
            $t->json('settings')->nullable();
            $t->timestamps();
            $t->index(['organization_id', 'status']);
        });
        Schema::create('keywords', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $t->foreignId('client_id')->constrained()->cascadeOnDelete();
            $t->foreignId('keyword_project_id')->constrained()->cascadeOnDelete();
            $t->string('phrase', 250);
            $t->string('type')->default('secondary');
            $t->string('priority')->default('medium');
            $t->unsignedInteger('search_volume')->nullable();
            $t->decimal('relevance_score', 5, 2)->nullable();
            $t->boolean('listing_coverage')->default(false);
            $t->string('status')->default('active');
            $t->timestamps();
            $t->unique(['keyword_project_id', 'phrase']);
        });
        Schema::create('keyword_rankings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $t->foreignId('client_id')->constrained()->cascadeOnDelete();
            $t->foreignId('keyword_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_id')->constrained()->cascadeOnDelete();
            $t->string('marketplace_id', 20);
            $t->unsignedInteger('organic_position')->nullable();
            $t->unsignedInteger('sponsored_position')->nullable();
            $t->unsignedInteger('page_number')->nullable();
            $t->unsignedInteger('result_count')->nullable();
            $t->string('provider');
            $t->decimal('confidence_score', 5, 2)->default(0);
            $t->timestamp('observed_at');
            $t->index(['keyword_id', 'observed_at']);
            $t->index(['organization_id', 'marketplace_id', 'observed_at']);
        });
        Schema::create('competitors', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $t->foreignId('client_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_id')->constrained()->cascadeOnDelete();
            $t->string('marketplace_id', 20);
            $t->string('asin', 20);
            $t->string('name')->nullable();
            $t->string('status')->default('active');
            $t->timestamp('last_snapshot_at')->nullable();
            $t->timestamps();
            $t->unique(['product_id', 'marketplace_id', 'asin']);
        });
        Schema::create('competitor_snapshots', function (Blueprint $t) {
            $t->id();
            $t->foreignId('competitor_id')->constrained()->cascadeOnDelete();
            $t->decimal('price', 12, 2)->nullable();
            $t->decimal('rating', 3, 2)->nullable();
            $t->unsignedInteger('review_count')->nullable();
            $t->unsignedInteger('category_rank')->nullable();
            $t->boolean('in_stock')->nullable();
            $t->string('featured_offer_seller')->nullable();
            $t->json('content_hashes')->nullable();
            $t->timestamp('observed_at');
            $t->index(['competitor_id', 'observed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competitor_snapshots');
        Schema::dropIfExists('competitors');
        Schema::dropIfExists('keyword_rankings');
        Schema::dropIfExists('keywords');
        Schema::dropIfExists('keyword_projects');
    }
};
