<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_verified_organizer')->default(false)->after('is_active');
            $table->timestamp('verified_organizer_at')->nullable()->after('is_verified_organizer');
        });

        Schema::create('object_representatives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pilgrimage_object_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 32)->default('editor');
            $table->string('status', 32)->default('pending')->index();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->unique(['pilgrimage_object_id', 'user_id'], 'object_representative_unique');
        });

        Schema::create('object_update_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pilgrimage_object_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->json('payload');
            $table->string('status', 32)->default('pending')->index();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();
        });

        Schema::create('object_media_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pilgrimage_object_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 32);
            $table->string('path');
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->string('status', 32)->default('pending')->index();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();
        });

        Schema::create('community_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporter_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reported_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('joint_pilgrimage_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('joint_pilgrimage_message_id')->nullable()->constrained()->nullOnDelete();
            $table->string('category', 64);
            $table->text('description');
            $table->string('status', 32)->default('open')->index();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_note')->nullable();
            $table->timestamps();
        });

        Schema::create('user_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('blocker_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('blocked_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['blocker_id', 'blocked_id'], 'user_block_unique');
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('user_blocks');
        Schema::dropIfExists('community_reports');
        Schema::dropIfExists('object_media_submissions');
        Schema::dropIfExists('object_update_requests');
        Schema::dropIfExists('object_representatives');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['is_verified_organizer', 'verified_organizer_at']);
        });
    }
};
