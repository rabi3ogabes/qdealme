<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An approved source can have a private upload link, so the store's own team
 * can send its flyer straight to QDealMe. Social media sources remember the
 * page ID that Meta's API resolved for their official page.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('flyer_sources', function (Blueprint $table) {
            $table->string('upload_token', 64)->nullable()->unique()->after('page_url');
            $table->timestamp('last_uploaded_at')->nullable()->after('upload_token');
            $table->string('social_page_id', 64)->nullable()->after('last_uploaded_at');
        });
    }

    public function down(): void
    {
        Schema::table('flyer_sources', function (Blueprint $table) {
            $table->dropUnique(['upload_token']);
            $table->dropColumn(['upload_token', 'last_uploaded_at', 'social_page_id']);
        });
    }
};
