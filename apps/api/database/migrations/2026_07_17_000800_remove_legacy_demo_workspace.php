<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $organizationId = DB::table('organizations')->where('slug', 'oopseller-demo')->value('id');

        if ($organizationId) {
            DB::table('organizations')->where('id', $organizationId)->delete();
        }

        DB::table('users')->where('email', 'owner@oopseller.test')->delete();
    }

    public function down(): void
    {
        // Legacy demonstration data is intentionally not recreated.
    }
};
