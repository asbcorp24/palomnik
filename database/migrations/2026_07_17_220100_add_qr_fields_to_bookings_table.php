<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('ticket_token', 64)->nullable()->unique()->after('ticket_code');
            $table->timestamp('checked_in_at')->nullable()->after('ticket_token');
            $table->foreignId('checked_in_by')->nullable()->after('checked_in_at')->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('checked_in_participants')->default(0)->after('checked_in_by');
        });

        DB::table('bookings')->orderBy('id')->each(function ($booking) {
            DB::table('bookings')->where('id', $booking->id)->update([
                'ticket_token' => hash('sha256', $booking->id.'|'.Str::uuid().'|'.microtime(true)),
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['checked_in_by']);
            $table->dropUnique(['ticket_token']);
            $table->dropColumn([
                'ticket_token',
                'checked_in_at',
                'checked_in_by',
                'checked_in_participants',
            ]);
        });
    }
};
