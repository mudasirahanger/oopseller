<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->softDeletes();
            $table->index(['organization_id', 'created_at']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->softDeletes();
            $table->index(['client_id', 'created_at']);
        });

        Schema::table('listings', function (Blueprint $table) {
            $table->softDeletes();
            $table->index(['client_id', 'marketplace_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->dropIndex(['client_id', 'marketplace_id', 'status']);
            $table->dropSoftDeletes();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['client_id', 'created_at']);
            $table->dropSoftDeletes();
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->dropIndex(['organization_id', 'created_at']);
            $table->dropSoftDeletes();
        });
    }
};
