<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('analytics_events')) {
            return;
        }

        Schema::create('analytics_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('session_id', 128)->nullable()->index();
            $table->string('event', 80)->index();
            $table->string('entity_type', 80)->nullable()->index();
            $table->unsignedBigInteger('entity_id')->nullable()->index();
            $table->string('search_query', 500)->nullable()->index();
            $table->json('properties')->nullable();
            $table->string('path', 1000)->nullable();
            $table->string('referrer', 1000)->nullable();
            $table->char('ip_hash', 64)->nullable()->index();
            $table->string('user_agent', 1000)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();

            $table->index(['event', 'created_at'], 'analytics_event_date_idx');
            $table->index(['entity_type', 'entity_id', 'created_at'], 'analytics_entity_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_events');
    }
};
