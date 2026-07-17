<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('advertising_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('amazon_account_id')->nullable()->constrained()->nullOnDelete();
            $table->string('profile_id')->unique();
            $table->string('marketplace_id', 20);
            $table->string('name')->nullable();
            $table->char('currency', 3)->default('INR');
            $table->string('status')->default('active');
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'client_id', 'status']);
        });

        Schema::create('advertising_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('advertising_profile_id')->constrained()->cascadeOnDelete();
            $table->string('campaign_id');
            $table->string('name');
            $table->string('ad_type')->default('sponsored_products');
            $table->string('targeting_type')->nullable();
            $table->string('state')->default('enabled');
            $table->decimal('daily_budget', 14, 2)->default(0);
            $table->timestamps();
            $table->unique(['advertising_profile_id', 'campaign_id']);
        });

        Schema::create('advertising_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('advertising_campaign_id')->constrained()->cascadeOnDelete();
            $table->date('metric_date');
            $table->unsignedBigInteger('impressions')->default(0);
            $table->unsignedBigInteger('clicks')->default(0);
            $table->decimal('spend', 14, 2)->default(0);
            $table->decimal('sales', 14, 2)->default(0);
            $table->unsignedInteger('orders')->default(0);
            $table->decimal('acos', 8, 4)->default(0);
            $table->decimal('roas', 8, 4)->default(0);
            $table->timestamp('recorded_at');
            $table->unique(['advertising_campaign_id', 'metric_date']);
            $table->index(['organization_id', 'metric_date']);
        });

        Schema::create('optimization_experiments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('listing_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('status')->default('draft');
            $table->date('baseline_start')->nullable();
            $table->date('baseline_end')->nullable();
            $table->date('experiment_start')->nullable();
            $table->date('experiment_end')->nullable();
            $table->json('hypothesis')->nullable();
            $table->json('changes')->nullable();
            $table->json('baseline_metrics')->nullable();
            $table->json('result_metrics')->nullable();
            $table->text('conclusion')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('optimization_experiments');
        Schema::dropIfExists('advertising_metrics');
        Schema::dropIfExists('advertising_campaigns');
        Schema::dropIfExists('advertising_profiles');
    }
};
