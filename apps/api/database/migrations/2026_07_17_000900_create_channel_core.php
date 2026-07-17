<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Generalizes the Amazon-only integration schema into a platform-agnostic
// channel core so additional marketplaces (Flipkart, Shopify, ...) can plug in
// beside Amazon without further schema rewrites.
return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('amazon_accounts', 'channel_accounts');
        Schema::table('channel_accounts', function (Blueprint $t): void {
            $t->renameColumn('seller_id', 'account_identifier');
        });
        Schema::table('channel_accounts', function (Blueprint $t): void {
            $t->string('platform', 30)->default('amazon')->after('client_id')->index();
            $t->text('credentials')->nullable()->after('refresh_token');
        });

        Schema::rename('amazon_account_marketplace', 'channel_account_marketplace');
        Schema::table('channel_account_marketplace', function (Blueprint $t): void {
            $t->renameColumn('amazon_account_id', 'channel_account_id');
        });

        Schema::rename('amazon_sync_runs', 'channel_sync_runs');
        Schema::table('channel_sync_runs', function (Blueprint $t): void {
            $t->renameColumn('amazon_account_id', 'channel_account_id');
        });
        Schema::table('channel_sync_runs', function (Blueprint $t): void {
            $t->string('platform', 30)->default('amazon')->after('client_id');
        });

        Schema::table('products', function (Blueprint $t): void {
            $t->renameColumn('amazon_account_id', 'channel_account_id');
        });
        Schema::table('products', function (Blueprint $t): void {
            $t->string('platform', 30)->default('amazon')->after('client_id');
            $t->string('external_id', 64)->nullable()->after('asin')->index();
        });
        DB::table('products')->update(['external_id' => DB::raw('asin')]);

        Schema::table('listings', function (Blueprint $t): void {
            $t->renameColumn('amazon_account_id', 'channel_account_id');
        });
        Schema::table('listings', function (Blueprint $t): void {
            $t->string('platform', 30)->default('amazon')->after('client_id');
        });

        Schema::table('advertising_profiles', function (Blueprint $t): void {
            $t->renameColumn('amazon_account_id', 'channel_account_id');
        });
    }

    public function down(): void
    {
        Schema::table('advertising_profiles', function (Blueprint $t): void {
            $t->renameColumn('channel_account_id', 'amazon_account_id');
        });

        Schema::table('listings', function (Blueprint $t): void {
            $t->dropColumn('platform');
        });
        Schema::table('listings', function (Blueprint $t): void {
            $t->renameColumn('channel_account_id', 'amazon_account_id');
        });

        Schema::table('products', function (Blueprint $t): void {
            $t->dropColumn(['platform', 'external_id']);
        });
        Schema::table('products', function (Blueprint $t): void {
            $t->renameColumn('channel_account_id', 'amazon_account_id');
        });

        Schema::table('channel_sync_runs', function (Blueprint $t): void {
            $t->dropColumn('platform');
        });
        Schema::table('channel_sync_runs', function (Blueprint $t): void {
            $t->renameColumn('channel_account_id', 'amazon_account_id');
        });
        Schema::rename('channel_sync_runs', 'amazon_sync_runs');

        Schema::table('channel_account_marketplace', function (Blueprint $t): void {
            $t->renameColumn('channel_account_id', 'amazon_account_id');
        });
        Schema::rename('channel_account_marketplace', 'amazon_account_marketplace');

        Schema::table('channel_accounts', function (Blueprint $t): void {
            $t->dropColumn(['platform', 'credentials']);
        });
        Schema::table('channel_accounts', function (Blueprint $t): void {
            $t->renameColumn('account_identifier', 'seller_id');
        });
        Schema::rename('channel_accounts', 'amazon_accounts');
    }
};
