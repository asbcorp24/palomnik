<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('joint_pilgrimages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organizer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('pilgrimage_route_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description');
            $table->dateTime('starts_at')->index();
            $table->dateTime('ends_at')->nullable();
            $table->string('meeting_place');
            $table->unsignedSmallInteger('max_participants')->nullable();
            $table->string('transport_mode', 32)->default('public');
            $table->string('join_mode', 32)->default('approval');
            $table->string('contact_method', 32)->nullable();
            $table->string('contact_value')->nullable();
            $table->string('status', 32)->default('pending')->index();
            $table->foreignId('moderated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('moderated_at')->nullable();
            $table->text('moderation_note')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('joint_pilgrimage_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('joint_pilgrimage_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 32)->default('pending')->index();
            $table->text('message')->nullable();
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();
            $table->unique(['joint_pilgrimage_id', 'user_id'], 'joint_pilgrimage_member_unique');
        });

        Schema::create('joint_pilgrimage_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('joint_pilgrimage_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->boolean('is_system')->default(false);
            $table->timestamps();
            $table->index(['joint_pilgrimage_id', 'created_at'], 'joint_pilgrimage_messages_order');
        });
    }

    public function down()
    {
        Schema::dropIfExists('joint_pilgrimage_messages');
        Schema::dropIfExists('joint_pilgrimage_members');
        Schema::dropIfExists('joint_pilgrimages');
    }
};
