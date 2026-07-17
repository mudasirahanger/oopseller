<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agency_tasks', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $t->foreignId('client_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('listing_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->string('type');
            $t->string('title');
            $t->text('description')->nullable();
            $t->string('priority')->default('medium');
            $t->string('status')->default('todo');
            $t->timestamp('due_at')->nullable();
            $t->timestamp('completed_at')->nullable();
            $t->json('metadata')->nullable();
            $t->timestamps();
            $t->index(['organization_id', 'status', 'due_at']);
        });
        Schema::create('approvals', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $t->foreignId('client_id')->constrained()->cascadeOnDelete();
            $t->morphs('approvable');
            $t->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $t->string('status')->default('pending');
            $t->text('note')->nullable();
            $t->timestamp('requested_at');
            $t->timestamp('decided_at')->nullable();
            $t->timestamps();
        });
        Schema::create('alert_rules', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $t->foreignId('client_id')->nullable()->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->string('metric');
            $t->string('operator');
            $t->decimal('threshold', 16, 4);
            $t->json('scope')->nullable();
            $t->json('channels');
            $t->unsignedInteger('cooldown_minutes')->default(1440);
            $t->boolean('enabled')->default(true);
            $t->timestamp('last_triggered_at')->nullable();
            $t->timestamps();
        });
        Schema::create('client_reports', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $t->foreignId('client_id')->constrained()->cascadeOnDelete();
            $t->string('type');
            $t->date('period_start');
            $t->date('period_end');
            $t->string('status')->default('queued');
            $t->json('summary')->nullable();
            $t->json('metrics')->nullable();
            $t->string('file_path')->nullable();
            $t->timestamp('generated_at')->nullable();
            $t->timestamps();
            $t->unique(['client_id', 'type', 'period_start', 'period_end']);
        });
        Schema::create('metric_snapshots', function (Blueprint $t) {
            $t->id();
            $t->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $t->foreignId('client_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_id')->nullable()->constrained()->cascadeOnDelete();
            $t->string('marketplace_id', 20);
            $t->date('metric_date');
            $t->decimal('revenue', 14, 2)->default(0);
            $t->unsignedInteger('orders')->default(0);
            $t->unsignedInteger('units')->default(0);
            $t->unsignedInteger('sessions')->default(0);
            $t->decimal('conversion_rate', 8, 4)->default(0);
            $t->decimal('ad_spend', 14, 2)->default(0);
            $t->decimal('ad_sales', 14, 2)->default(0);
            $t->decimal('acos', 8, 4)->default(0);
            $t->decimal('tacos', 8, 4)->default(0);
            $t->decimal('organic_sales', 14, 2)->default(0);
            $t->decimal('refund_rate', 8, 4)->default(0);
            $t->timestamp('recorded_at');
            $t->unique(['client_id', 'product_id', 'marketplace_id', 'metric_date'], 'metric_snapshot_unique');
            $t->index(['organization_id', 'metric_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('metric_snapshots');
        Schema::dropIfExists('client_reports');
        Schema::dropIfExists('alert_rules');
        Schema::dropIfExists('approvals');
        Schema::dropIfExists('agency_tasks');
    }
};
