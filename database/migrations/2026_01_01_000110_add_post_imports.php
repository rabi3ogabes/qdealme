<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Flyers imported from a store's own social media post keep the post link
 * and a fingerprint of their images, so the same post is never imported
 * twice. A "social media" source can record the store's official page.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('flyer_sources', function (Blueprint $table) {
            $table->string('page_url', 2048)->nullable()->after('feed_url');
        });

        Schema::table('flyers', function (Blueprint $table) {
            $table->string('source_post_url', 2048)->nullable()->after('whatsapp_order_url');
            $table->string('content_hash', 64)->nullable()->after('source_post_url')->index();
        });
    }

    public function down(): void
    {
        Schema::table('flyers', function (Blueprint $table) {
            $table->dropIndex(['content_hash']);
            $table->dropColumn(['source_post_url', 'content_hash']);
        });

        Schema::table('flyer_sources', function (Blueprint $table) {
            $table->dropColumn('page_url');
        });
    }
};
