<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('app_authentication_secret')->nullable();
            $table->text('app_authentication_recovery_codes')->nullable();
        });

        // Defence in depth for lawful sourcing: even a raw SQL insert cannot
        // attach a flyer to a source whose licence is not approved.
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
                CREATE OR REPLACE FUNCTION qdealme_assert_flyer_source_approved() RETURNS trigger AS $$
                BEGIN
                    IF NOT EXISTS (
                        SELECT 1 FROM flyer_sources
                        WHERE id = NEW.flyer_source_id AND license_status = 'approved'
                    ) THEN
                        RAISE EXCEPTION 'flyer_source % is not approved', NEW.flyer_source_id
                            USING ERRCODE = 'check_violation';
                    END IF;
                    RETURN NEW;
                END;
                $$ LANGUAGE plpgsql;

                CREATE TRIGGER flyers_source_must_be_approved
                    BEFORE INSERT OR UPDATE OF flyer_source_id ON flyers
                    FOR EACH ROW EXECUTE FUNCTION qdealme_assert_flyer_source_approved();
            SQL);
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('DROP TRIGGER IF EXISTS flyers_source_must_be_approved ON flyers; DROP FUNCTION IF EXISTS qdealme_assert_flyer_source_approved();');
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['app_authentication_secret', 'app_authentication_recovery_codes']);
        });
    }
};
