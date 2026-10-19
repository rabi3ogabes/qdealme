<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('retailers', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 96)->unique();
            $table->string('name_ar');
            $table->string('name_en');
            $table->string('logo_path')->nullable();
            $table->string('brand_color', 9)->nullable();
            $table->string('website_url')->nullable();
            $table->string('whatsapp_number', 32)->nullable();
            $table->string('type', 32)->index();
            $table->text('about_ar')->nullable();
            $table->text('about_en')->nullable();
            $table->text('search_index')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('retailer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('city_id')->constrained()->cascadeOnDelete();
            $table->string('name_ar');
            $table->string('name_en');
            $table->string('address_ar')->nullable();
            $table->string('address_en')->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->string('phone', 32)->nullable();
            $table->jsonb('opening_hours')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branches');
        Schema::dropIfExists('retailers');
    }
};
