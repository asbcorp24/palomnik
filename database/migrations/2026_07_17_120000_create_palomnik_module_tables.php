<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('pilgrimage_routes', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('category', 64)->default('one_day')->index();
            $table->string('difficulty', 32)->default('easy')->index();
            $table->unsignedSmallInteger('duration_days')->default(1);
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->text('short_description')->nullable();
            $table->longText('description')->nullable();
            $table->longText('program')->nullable();
            $table->decimal('base_price', 12, 2)->nullable();
            $table->boolean('is_group')->default(false)->index();
            $table->boolean('is_published')->default(false)->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->string('cover_path')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('pilgrimage_route_object', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pilgrimage_route_id')->constrained('pilgrimage_routes')->cascadeOnDelete();
            $table->foreignId('pilgrimage_object_id')->constrained('pilgrimage_objects')->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedInteger('stay_minutes')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->unique(['pilgrimage_route_id', 'pilgrimage_object_id'], 'route_object_unique');
        });

        Schema::create('trips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pilgrimage_route_id')->constrained('pilgrimage_routes')->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->dateTime('starts_at')->index();
            $table->dateTime('ends_at')->nullable();
            $table->string('meeting_point')->nullable();
            $table->unsignedInteger('capacity')->nullable();
            $table->unsignedInteger('booked_count')->default(0);
            $table->decimal('price', 12, 2)->nullable();
            $table->string('status', 32)->default('open')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained('trips')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('contact_name');
            $table->string('email')->nullable()->index();
            $table->string('phone', 64)->nullable()->index();
            $table->unsignedSmallInteger('participants_count')->default(1);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->string('status', 32)->default('pending')->index();
            $table->string('payment_status', 32)->default('unpaid')->index();
            $table->string('payment_provider', 64)->nullable();
            $table->string('payment_reference')->nullable()->index();
            $table->string('ticket_code')->nullable()->unique();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('achievements', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('category', 64)->default('visits')->index();
            $table->string('badge_level', 32)->default('special')->index();
            $table->unsignedInteger('points')->default(0);
            $table->string('condition_type', 64)->default('visits_count')->index();
            $table->unsignedInteger('condition_value')->nullable();
            $table->text('description')->nullable();
            $table->string('icon')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('user_achievements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('achievement_id')->constrained()->cascadeOnDelete();
            $table->timestamp('awarded_at')->nullable();
            $table->json('progress')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'achievement_id']);
        });

        Schema::create('visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pilgrimage_object_id')->constrained('pilgrimage_objects')->cascadeOnDelete();
            $table->timestamp('visited_at')->index();
            $table->string('verification_method', 32)->default('geolocation')->index();
            $table->string('status', 32)->default('pending')->index();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pilgrimage_object_id')->constrained('pilgrimage_objects')->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->text('body');
            $table->string('status', 32)->default('pending')->index();
            $table->foreignId('moderated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('moderated_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'pilgrimage_object_id']);
        });

        Schema::create('blog_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('excerpt')->nullable();
            $table->longText('body');
            $table->string('status', 32)->default('draft')->index();
            $table->foreignId('moderated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('moderated_at')->nullable();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('user_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pilgrimage_object_id')->nullable()->constrained('pilgrimage_objects')->nullOnDelete();
            $table->foreignId('blog_post_id')->nullable()->constrained('blog_posts')->cascadeOnDelete();
            $table->string('type', 32)->default('image')->index();
            $table->string('path');
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('status', 32)->default('pending')->index();
            $table->foreignId('moderated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('moderated_at')->nullable();
            $table->timestamps();
        });

        Schema::create('favorite_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->boolean('is_default')->default(false)->index();
            $table->timestamps();
        });

        Schema::create('favorite_list_object', function (Blueprint $table) {
            $table->id();
            $table->foreignId('favorite_list_id')->constrained('favorite_lists')->cascadeOnDelete();
            $table->foreignId('pilgrimage_object_id')->constrained('pilgrimage_objects')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['favorite_list_id', 'pilgrimage_object_id'], 'favorite_object_unique');
        });
    }

    public function down()
    {
        Schema::dropIfExists('favorite_list_object');
        Schema::dropIfExists('favorite_lists');
        Schema::dropIfExists('user_media');
        Schema::dropIfExists('blog_posts');
        Schema::dropIfExists('reviews');
        Schema::dropIfExists('visits');
        Schema::dropIfExists('user_achievements');
        Schema::dropIfExists('achievements');
        Schema::dropIfExists('bookings');
        Schema::dropIfExists('trips');
        Schema::dropIfExists('pilgrimage_route_object');
        Schema::dropIfExists('pilgrimage_routes');
    }
};
