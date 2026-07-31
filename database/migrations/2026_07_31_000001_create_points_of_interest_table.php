<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('points_of_interest', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pilgrimage_object_id')
                ->constrained('pilgrimage_objects')
                ->cascadeOnDelete();
            $table->string('category', 32)->default('attraction');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('address', 500);
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->string('phone', 64)->nullable();
            $table->string('website')->nullable();
            $table->text('schedule_text')->nullable();
            $table->boolean('is_published')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['pilgrimage_object_id', 'is_published'], 'poi_object_published_index');
            $table->index(['category', 'is_published'], 'poi_category_published_index');
            $table->index(['latitude', 'longitude'], 'poi_coordinates_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('points_of_interest');
    }
};
