<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pilgrimage_objects')) {
            return;
        }

        Schema::table('pilgrimage_objects', function (Blueprint $table): void {
            if (! Schema::hasColumn('pilgrimage_objects', 'information_verified_at')) {
                $table->timestamp('information_verified_at')->nullable()->index()->after('accessibility_info');
            }

            if (! Schema::hasColumn('pilgrimage_objects', 'information_source_url')) {
                $table->string('information_source_url', 1000)->nullable()->after('information_verified_at');
            }

            if (! Schema::hasColumn('pilgrimage_objects', 'verified_by')) {
                $table->foreignId('verified_by')->nullable()->after('information_source_url')
                    ->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('pilgrimage_objects', 'next_verification_at')) {
                $table->timestamp('next_verification_at')->nullable()->index()->after('verified_by');
            }

            if (! Schema::hasColumn('pilgrimage_objects', 'verification_status')) {
                $table->string('verification_status', 32)->default('unverified')->index()->after('next_verification_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('pilgrimage_objects')) {
            return;
        }

        Schema::table('pilgrimage_objects', function (Blueprint $table): void {
            if (Schema::hasColumn('pilgrimage_objects', 'verified_by')) {
                $table->dropForeign(['verified_by']);
            }

            $columns = array_values(array_filter([
                Schema::hasColumn('pilgrimage_objects', 'information_verified_at') ? 'information_verified_at' : null,
                Schema::hasColumn('pilgrimage_objects', 'information_source_url') ? 'information_source_url' : null,
                Schema::hasColumn('pilgrimage_objects', 'verified_by') ? 'verified_by' : null,
                Schema::hasColumn('pilgrimage_objects', 'next_verification_at') ? 'next_verification_at' : null,
                Schema::hasColumn('pilgrimage_objects', 'verification_status') ? 'verification_status' : null,
            ]));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
