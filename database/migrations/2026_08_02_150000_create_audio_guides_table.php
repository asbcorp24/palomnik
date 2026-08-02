<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audio_guides', function (Blueprint $table) {
            $table->id();
            $table->string('guideable_type');
            $table->unsignedBigInteger('guideable_id');
            $table->string('title')->nullable();
            $table->string('path');
            $table->longText('transcript')->nullable();
            $table->string('original_name')->nullable();
            $table->string('mime_type', 191)->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->timestamps();

            $table->unique(['guideable_type', 'guideable_id'], 'audio_guides_guideable_unique');
            $table->index(['guideable_type', 'guideable_id'], 'audio_guides_guideable_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audio_guides');
    }
};
