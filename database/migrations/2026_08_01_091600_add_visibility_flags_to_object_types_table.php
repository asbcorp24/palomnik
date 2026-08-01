<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('object_types')) {
            return;
        }

        if (! Schema::hasColumn('object_types', 'is_active')) {
            Schema::table('object_types', function (Blueprint $table): void {
                $table->boolean('is_active')->default(true)->after('sort_order')->index();
            });
        }

        if (! Schema::hasColumn('object_types', 'is_public')) {
            Schema::table('object_types', function (Blueprint $table): void {
                $table->boolean('is_public')->default(true)->after('is_active')->index();
            });
        }

        DB::table('object_types')->update([
            'is_active' => true,
            'is_public' => true,
        ]);

        DB::table('object_types')
            ->where('slug', 'holy-spring')
            ->update([
                'name' => 'Архивный тип',
                'icon' => 'archive',
                'sort_order' => 1000,
                'is_active' => false,
                'is_public' => false,
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('object_types')) {
            return;
        }

        $columns = [];

        if (Schema::hasColumn('object_types', 'is_public')) {
            $columns[] = 'is_public';
        }

        if (Schema::hasColumn('object_types', 'is_active')) {
            $columns[] = 'is_active';
        }

        if ($columns !== []) {
            Schema::table('object_types', function (Blueprint $table) use ($columns): void {
                $table->dropColumn($columns);
            });
        }
    }
};
