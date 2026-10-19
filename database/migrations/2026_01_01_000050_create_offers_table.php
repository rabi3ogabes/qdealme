<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('flyer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('flyer_page_id')->constrained('flyer_pages')->cascadeOnDelete();
            $table->foreignId('retailer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('slug', 160);
            $table->string('raw_title')->nullable();
            $table->string('title_ar');
            $table->string('title_en');
            $table->string('brand', 96)->nullable();
            $table->decimal('price', 12, 2);
            $table->decimal('old_price', 12, 2)->nullable();
            $table->string('currency', 3)->default('QAR');
            $table->decimal('size_value', 10, 3)->nullable();
            $table->string('size_unit', 12)->nullable();
            $table->decimal('unit_price', 12, 4)->nullable();
            $table->string('unit', 12)->nullable();
            $table->json('bbox')->nullable();
            $table->string('crop_path')->nullable();
            $table->text('search_index')->nullable();
            $table->string('verification', 16)->default('unverified')->index();
            $table->decimal('confidence', 4, 3)->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->index(['category_id', 'verification']);
            $table->index(['retailer_id', 'verification']);
            $table->index('price');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offers');
    }
};
