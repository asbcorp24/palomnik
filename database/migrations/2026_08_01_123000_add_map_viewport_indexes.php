<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pilgrimage_objects')
            && ! $this->indexExists('pilgrimage_objects', 'pilgrimage_objects_map_bounds_idx')) {
            Schema::table('pilgrimage_objects', function (Blueprint $table): void {
                $table->index(
                    ['is_published', 'latitude', 'longitude'],
                    'pilgrimage_objects_map_bounds_idx'
                );
            });
        }

        if (Schema::hasTable('points_of_interest')
            && ! $this->indexExists('points_of_interest', 'points_of_interest_map_bounds_idx')) {
            Schema::table('points_of_interest', function (Blueprint $table): void {
                $table->index(
                    ['is_published', 'latitude', 'longitude'],
                    'points_of_interest_map_bounds_idx'
                );
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('pilgrimage_objects')
            && $this->indexExists('pilgrimage_objects', 'pilgrimage_objects_map_bounds_idx')) {
            Schema::table('pilgrimage_objects', function (Blueprint $table): void {
                $table->dropIndex('pilgrimage_objects_map_bounds_idx');
            });
        }

        if (Schema::hasTable('points_of_interest')
            && $this->indexExists('points_of_interest', 'points_of_interest_map_bounds_idx')) {
            Schema::table('points_of_interest', function (Blueprint $table): void {
                $table->dropIndex('points_of_interest_map_bounds_idx');
            });
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return false;
        }

        return DB::table('information_schema.statistics')
            ->where('table_schema', DB::connection()->getDatabaseName())
            ->where('table_name', $table)
            ->where('index_name', $index)
            ->exists();
    }
};
