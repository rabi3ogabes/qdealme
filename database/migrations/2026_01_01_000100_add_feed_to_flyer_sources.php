<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A "retailer feed" source can carry the official link where the store
 * publishes its current flyer, so staff can pull it instead of uploading.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('flyer_sources', function (Blueprint $table) {
            $table->string('feed_url', 2048)->nullable()->after('permission_contact');
            $table->timestamp('last_pulled_at')->nullable()->after('feed_url');
            $table->string('last_pulled_hash', 64)->nullable()->after('last_pulled_at');
            $table->text('last_pull_error')->nullable()->after('last_pulled_hash');
        });
    }

    public function down(): void
    {
        Schema::table('flyer_sources', function (Blueprint $table) {
            $table->dropColumn(['feed_url', 'last_pulled_at', 'last_pulled_hash', 'last_pull_error']);
        });
    }
};
