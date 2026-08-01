<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('object_duplicate_candidates')) {
            return;
        }

        Schema::create('object_duplicate_candidates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('object_a_id')->constrained('pilgrimage_objects')->cascadeOnDelete();
            $table->foreignId('object_b_id')->constrained('pilgrimage_objects')->cascadeOnDelete();
            $table->decimal('score', 5, 2)->default(0)->index();
            $table->decimal('name_similarity', 5, 2)->nullable();
            $table->unsignedInteger('distance_meters')->nullable()->index();
            $table->json('reasons')->nullable();
            $table->string('status', 24)->default('pending')->index();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(['object_a_id', 'object_b_id'], 'duplicate_candidate_pair_unique');
            $table->index(['status', 'score'], 'duplicate_candidate_status_score_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('object_duplicate_candidates');
    }
};
