<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('user_route_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('transport_mode', 32)->default('walk')->index();
            $table->unsignedInteger('estimated_minutes')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('user_route_plan_object', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_route_plan_id')->constrained('user_route_plans')->cascadeOnDelete();
            $table->foreignId('pilgrimage_object_id')->constrained('pilgrimage_objects')->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedInteger('stay_minutes')->default(30);
            $table->timestamps();
            $table->unique(['user_route_plan_id', 'pilgrimage_object_id'], 'user_plan_object_unique');
        });
    }

    public function down()
    {
        Schema::dropIfExists('user_route_plan_object');
        Schema::dropIfExists('user_route_plans');
    }
};
