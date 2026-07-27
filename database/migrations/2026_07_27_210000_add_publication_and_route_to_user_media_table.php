<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_media', function (Blueprint $table) {
            $table->foreignId('pilgrimage_route_id')
                ->nullable()
                ->after('pilgrimage_object_id')
                ->constrained('pilgrimage_routes')
                ->nullOnDelete();
            $table->boolean('publication_requested')->default(false)->after('status')->index();
            $table->timestamp('published_at')->nullable()->after('moderated_at')->index();
            $table->text('moderation_notes')->nullable()->after('published_at');
        });

        DB::table('user_media')
            ->whereIn('status', ['pending', 'published', 'rejected'])
            ->update(['publication_requested' => true]);
    }

    public function down(): void
    {
        Schema::table('user_media', function (Blueprint $table) {
            $table->dropForeign(['pilgrimage_route_id']);
            $table->dropColumn([
                'pilgrimage_route_id',
                'publication_requested',
                'published_at',
                'moderation_notes',
            ]);
        });
    }
};
