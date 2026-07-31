<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pilgrimage_objects', function (Blueprint $table) {
            $table->foreignId('parent_object_id')
                ->nullable()
                ->after('object_type_id')
                ->constrained('pilgrimage_objects')
                ->nullOnDelete();

            $table->index(['parent_object_id', 'is_published']);
        });
    }

    public function down(): void
    {
        Schema::table('pilgrimage_objects', function (Blueprint $table) {
            $table->dropIndex(['parent_object_id', 'is_published']);
            $table->dropConstrainedForeignId('parent_object_id');
        });
    }
};
