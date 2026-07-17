<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('amazon_accounts', function (Blueprint $table) {
            $table->timestamp('token_last_refreshed_at')->nullable()->after('authorized_at');
            $table->text('last_sync_error')->nullable()->after('last_synced_at');
            $table->json('metadata')->nullable()->after('last_sync_error');
            $table->index(['organization_id', 'client_id', 'status']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('amazon_account_id')->nullable()->after('client_id')->constrained()->nullOnDelete();
            $table->string('source')->default('manual')->after('status');
            $table->timestamp('last_imported_at')->nullable()->after('source');
            $table->index(['amazon_account_id', 'asin']);
        });

        Schema::table('listings', function (Blueprint $table) {
            $table->foreignId('amazon_account_id')->nullable()->after('client_id')->constrained()->nullOnDelete();
            $table->string('seller_sku')->nullable()->after('marketplace_id');
            $table->json('amazon_issues')->nullable()->after('attributes');
            $table->json('offers')->nullable()->after('amazon_issues');
            $table->json('fulfillment_availability')->nullable()->after('offers');
            $table->json('relationships')->nullable()->after('fulfillment_availability');
            $table->json('product_types')->nullable()->after('relationships');
            $table->json('raw_payload')->nullable()->after('product_types');
            $table->text('last_sync_error')->nullable()->after('last_imported_at');
            $table->timestamp('last_published_at')->nullable()->after('last_sync_error');
            $table->index(['amazon_account_id', 'seller_sku']);
        });

        Schema::create('amazon_sync_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('amazon_account_id')->constrained()->cascadeOnDelete();
            $table->string('marketplace_id', 20)->nullable();
            $table->string('type');
            $table->string('status')->default('queued');
            $table->unsignedInteger('processed')->default(0);
            $table->unsignedInteger('failed')->default(0);
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['amazon_account_id', 'type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('amazon_sync_runs');

        Schema::table('listings', function (Blueprint $table) {
            $table->dropForeign(['amazon_account_id']);
            $table->dropIndex(['amazon_account_id', 'seller_sku']);
            $table->dropColumn([
                'amazon_account_id', 'seller_sku', 'amazon_issues', 'offers',
                'fulfillment_availability', 'relationships', 'product_types',
                'raw_payload', 'last_sync_error', 'last_published_at',
            ]);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['amazon_account_id']);
            $table->dropIndex(['amazon_account_id', 'asin']);
            $table->dropColumn(['amazon_account_id', 'source', 'last_imported_at']);
        });

        Schema::table('amazon_accounts', function (Blueprint $table) {
            $table->dropIndex(['organization_id', 'client_id', 'status']);
            $table->dropColumn(['token_last_refreshed_at', 'last_sync_error', 'metadata']);
        });
    }
};
