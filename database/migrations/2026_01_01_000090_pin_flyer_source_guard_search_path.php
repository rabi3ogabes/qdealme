<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Pin the licence-guard trigger function to the app's schema so it always
 * checks the real flyer_sources table, whatever search_path the caller has
 * (Supabase's security advisor flags functions with a mutable search_path).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $schema = DB::selectOne('SELECT current_schema() AS name')->name;

        DB::statement(sprintf(
            'ALTER FUNCTION qdealme_assert_flyer_source_approved() SET search_path = %s',
            '"'.str_replace('"', '""', $schema).'"'
        ));
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER FUNCTION qdealme_assert_flyer_source_approved() RESET search_path');
    }
};
