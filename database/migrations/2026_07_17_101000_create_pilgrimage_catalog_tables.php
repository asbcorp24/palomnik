<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('object_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('marker_color', 16)->nullable();
            $table->string('icon')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('vicariates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('deaneries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vicariate_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('pilgrimage_objects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('object_type_id')->constrained()->onDelete('restrict');
            $table->foreignId('vicariate_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('deanery_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('short_description')->nullable();
            $table->longText('description')->nullable();
            $table->longText('history')->nullable();
            $table->string('address');
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->string('phone', 64)->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            $table->text('schedule_text')->nullable();
            $table->text('parking_info')->nullable();
            $table->text('accessibility_info')->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_published', 'object_type_id']);
            $table->index(['vicariate_id', 'deanery_id']);
            $table->index(['latitude', 'longitude']);
        });

        Schema::create('sanctities', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('type', 64)->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('object_sanctity', function (Blueprint $table) {
            $table->foreignId('pilgrimage_object_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sanctity_id')->constrained()->cascadeOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->primary(['pilgrimage_object_id', 'sanctity_id']);
        });

        Schema::create('object_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pilgrimage_object_id')->constrained()->cascadeOnDelete();
            $table->string('type', 24)->default('image');
            $table->string('path')->nullable();
            $table->string('external_url')->nullable();
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_cover')->default(false);
            $table->timestamps();

            $table->index(['pilgrimage_object_id', 'type', 'sort_order']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('object_media');
        Schema::dropIfExists('object_sanctity');
        Schema::dropIfExists('sanctities');
        Schema::dropIfExists('pilgrimage_objects');
        Schema::dropIfExists('deaneries');
        Schema::dropIfExists('vicariates');
        Schema::dropIfExists('object_types');
    }
};
