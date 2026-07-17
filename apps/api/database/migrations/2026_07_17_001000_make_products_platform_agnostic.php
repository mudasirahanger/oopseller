<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Non-Amazon channel products have no ASIN; identity becomes
// (client_id, platform, external_id) with asin kept for Amazon rows.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $t): void {
            $t->dropUnique(['client_id', 'asin']);
        });
        Schema::table('products', function (Blueprint $t): void {
            $t->string('asin', 20)->nullable()->change();
        });
        Schema::table('products', function (Blueprint $t): void {
            $t->unique(['client_id', 'platform', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $t): void {
            $t->dropUnique(['client_id', 'platform', 'external_id']);
        });
        Schema::table('products', function (Blueprint $t): void {
            $t->string('asin', 20)->nullable(false)->change();
        });
        Schema::table('products', function (Blueprint $t): void {
            $t->unique(['client_id', 'asin']);
        });
    }
};
