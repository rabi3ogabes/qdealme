<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flyer_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('retailer_id')->constrained()->cascadeOnDelete();
            $table->string('type', 32);
            $table->string('license_status', 16)->default('pending')->index();
            $table->string('license_document_path')->nullable();
            $table->string('permission_contact')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('flyers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('retailer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('flyer_source_id')->constrained('flyer_sources')->restrictOnDelete();
            $table->string('slug', 160);
            $table->string('title_ar');
            $table->string('title_en');
            $table->date('valid_from');
            $table->date('valid_until');
            $table->string('status', 16)->default('draft')->index();
            $table->boolean('is_sponsored')->default(false);
            $table->date('sponsored_until')->nullable();
            $table->unsignedBigInteger('cover_page_id')->nullable();
            $table->unsignedSmallInteger('page_count')->default(0);
            $table->string('shop_online_url')->nullable();
            $table->string('whatsapp_order_url')->nullable();
            $table->string('upload_path')->nullable();
            $table->json('upload_files')->nullable();
            $table->text('processing_error')->nullable();
            $table->text('search_index')->nullable();
            $table->unsignedInteger('view_count')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'valid_until']);
            $table->index(['retailer_id', 'status']);
            $table->index('published_at');
        });

        Schema::create('flyer_city', function (Blueprint $table) {
            $table->foreignId('flyer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('city_id')->constrained()->cascadeOnDelete();
            $table->primary(['flyer_id', 'city_id']);
        });

        Schema::create('flyer_branch', function (Blueprint $table) {
            $table->foreignId('flyer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->primary(['flyer_id', 'branch_id']);
        });

        Schema::create('flyer_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('flyer_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('number');
            $table->string('original_path');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->json('derivatives')->nullable();
            $table->text('ocr_text')->nullable();
            $table->timestamps();

            $table->unique(['flyer_id', 'number']);
        });

        Schema::table('flyers', function (Blueprint $table) {
            $table->foreign('cover_page_id')->references('id')->on('flyer_pages')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('flyers', function (Blueprint $table) {
            $table->dropForeign(['cover_page_id']);
        });
        Schema::dropIfExists('flyer_pages');
        Schema::dropIfExists('flyer_branch');
        Schema::dropIfExists('flyer_city');
        Schema::dropIfExists('flyers');
        Schema::dropIfExists('flyer_sources');
    }
};
