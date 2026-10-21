<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DatabaseSecurityTest extends TestCase
{
    public function test_every_table_has_row_level_security_enabled(): void
    {
        $unprotected = collect(DB::select(
            "SELECT c.relname FROM pg_class c
             JOIN pg_namespace n ON n.oid = c.relnamespace
             WHERE n.nspname = current_schema() AND c.relkind = 'r' AND NOT c.relrowsecurity"
        ))->pluck('relname')->all();

        $this->assertSame([], $unprotected, 'Tables without row-level security: '.implode(', ', $unprotected));
    }

    public function test_licence_guard_function_has_a_fixed_search_path(): void
    {
        $config = DB::selectOne(
            "SELECT p.proconfig FROM pg_proc p
             JOIN pg_namespace n ON n.oid = p.pronamespace
             WHERE n.nspname = current_schema() AND p.proname = 'qdealme_assert_flyer_source_approved'"
        )->proconfig;

        $this->assertStringContainsString('search_path=', (string) $config);
    }

    public function test_the_app_still_reads_and_writes_with_rls_enabled(): void
    {
        $flyer = $this->makeFlyer();

        $this->get($flyer->url())->assertOk();
        $this->assertDatabaseHas('flyers', ['id' => $flyer->id]);
    }
}
