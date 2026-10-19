<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Supabase exposes every table in the "public" schema through its REST and
 * GraphQL APIs. Enabling row-level security with no policies closes that door
 * for the anon and authenticated API roles, while Laravel keeps full access
 * because it connects as the tables' owner.
 *
 * Tables created by later migrations must enable RLS too; the test suite
 * checks that every public table has it switched on.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach ($this->tables() as $table) {
            DB::statement(sprintf('ALTER TABLE %s ENABLE ROW LEVEL SECURITY', $this->quote($table)));
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach ($this->tables() as $table) {
            DB::statement(sprintf('ALTER TABLE %s DISABLE ROW LEVEL SECURITY', $this->quote($table)));
        }
    }

    /**
     * @return array<int, string>
     */
    private function tables(): array
    {
        return array_map(
            fn ($row) => $row->tablename,
            DB::select('SELECT tablename FROM pg_tables WHERE schemaname = current_schema()')
        );
    }

    private function quote(string $table): string
    {
        return '"'.str_replace('"', '""', $table).'"';
    }
};
