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

    public function test_the_app_still_reads_and_writes_with_rls_enabled(): void
    {
        $flyer = $this->makeFlyer();

        $this->get($flyer->url())->assertOk();
        $this->assertDatabaseHas('flyers', ['id' => $flyer->id]);
    }
}
