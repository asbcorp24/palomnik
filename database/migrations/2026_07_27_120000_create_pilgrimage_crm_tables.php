<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('crm_stage', 32)->default('new')->index();
            $table->string('priority', 16)->default('normal')->index();
            $table->string('source', 32)->default('site')->index();
            $table->unsignedInteger('contact_attempts')->default(0);
            $table->timestamp('last_contact_at')->nullable()->index();
            $table->timestamp('next_contact_at')->nullable()->index();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->text('internal_notes')->nullable();
            $table->text('cancellation_reason')->nullable();
        });

        Schema::create('booking_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->string('full_name');
            $table->string('phone', 64)->nullable();
            $table->string('email')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('decision_status', 24)->default('pending')->index();
            $table->string('attendance_status', 24)->default('pending')->index();
            $table->boolean('is_primary')->default(false)->index();
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('booking_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 32)->default('note')->index();
            $table->text('body');
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });

        $now = now();

        DB::table('bookings')->orderBy('id')->chunkById(100, function ($bookings) use ($now) {
            foreach ($bookings as $booking) {
                $count = max(1, (int) $booking->participants_count);
                $decision = in_array($booking->status, ['confirmed', 'completed'], true)
                    ? 'going'
                    : (in_array($booking->status, ['cancelled', 'refunded'], true) ? 'not_going' : 'pending');
                $checkedIn = min($count, (int) ($booking->checked_in_participants ?? 0));
                $rows = [];

                for ($index = 0; $index < $count; $index++) {
                    $rows[] = [
                        'booking_id' => $booking->id,
                        'full_name' => $index === 0
                            ? ($booking->contact_name ?: 'Основной участник')
                            : 'Участник '.($index + 1),
                        'phone' => $index === 0 ? $booking->phone : null,
                        'email' => $index === 0 ? $booking->email : null,
                        'birth_date' => null,
                        'decision_status' => $decision,
                        'attendance_status' => $index < $checkedIn ? 'attended' : 'pending',
                        'is_primary' => $index === 0,
                        'paid_amount' => 0,
                        'notes' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                DB::table('booking_participants')->insert($rows);
                DB::table('booking_activities')->insert([
                    'booking_id' => $booking->id,
                    'user_id' => null,
                    'type' => 'system',
                    'body' => 'Заявка импортирована в CRM.',
                    'metadata' => null,
                    'created_at' => $now,
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_activities');
        Schema::dropIfExists('booking_participants');

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['assigned_to']);
            $table->dropColumn([
                'assigned_to',
                'crm_stage',
                'priority',
                'source',
                'contact_attempts',
                'last_contact_at',
                'next_contact_at',
                'confirmed_at',
                'closed_at',
                'internal_notes',
                'cancellation_reason',
            ]);
        });
    }
};
