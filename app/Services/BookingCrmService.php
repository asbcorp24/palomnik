<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingActivity;
use App\Models\BookingParticipant;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BookingCrmService
{
    public const CLOSED_BOOKING_STATUSES = ['cancelled', 'refunded'];

    public function createBooking(Trip $trip, array $data, ?User $customer, ?User $actor = null): Booking
    {
        return DB::transaction(function () use ($trip, $data, $customer, $actor) {
            $trip = Trip::query()->lockForUpdate()->findOrFail($trip->id);
            $participantsCount = max(1, (int) ($data['participants_count'] ?? 1));
            $status = (string) ($data['status'] ?? 'pending');

            if (! in_array($status, self::CLOSED_BOOKING_STATUSES, true)) {
                $this->assertCapacity($trip, $participantsCount);
            }

            $booking = Booking::query()->create([
                'trip_id' => $trip->id,
                'user_id' => $customer?->id,
                'contact_name' => $data['contact_name'],
                'email' => ! empty($data['email']) ? mb_strtolower($data['email']) : null,
                'phone' => $data['phone'] ?? null,
                'participants_count' => $participantsCount,
                'total_amount' => (float) ($data['total_amount'] ?? 0),
                'status' => $status,
                'payment_status' => $data['payment_status'] ?? 'unpaid',
                'payment_provider' => $data['payment_provider'] ?? null,
                'payment_reference' => $data['payment_reference'] ?? null,
                'ticket_code' => $data['ticket_code'] ?? $this->ticketCode(),
                'notes' => $data['notes'] ?? null,
                'assigned_to' => $data['assigned_to'] ?? null,
                'crm_stage' => $data['crm_stage'] ?? 'new',
                'priority' => $data['priority'] ?? 'normal',
                'source' => $data['source'] ?? 'site',
                'next_contact_at' => $data['next_contact_at'] ?? null,
                'internal_notes' => $data['internal_notes'] ?? null,
                'confirmed_at' => in_array($status, ['confirmed', 'completed'], true) ? now() : null,
                'closed_at' => in_array($status, self::CLOSED_BOOKING_STATUSES, true) ? now() : null,
                'cancellation_reason' => $data['cancellation_reason'] ?? null,
            ]);

            $this->createInitialParticipants($booking, $data['participant_names'] ?? []);
            $this->recalculateTripBookedCount($trip);
            $this->log($booking, $actor, 'created', 'Заявка создана.', [
                'source' => $booking->source,
                'participants_count' => $booking->participants_count,
            ]);

            return $booking->fresh(['participants', 'trip.pilgrimageRoute', 'assignedTo']);
        });
    }

    public function updateBooking(Booking $booking, array $data, User $actor): Booking
    {
        return DB::transaction(function () use ($booking, $data, $actor) {
            $booking = Booking::query()->lockForUpdate()->findOrFail($booking->id);
            $trip = Trip::query()->lockForUpdate()->findOrFail($booking->trip_id);
            $old = Arr::only($booking->getAttributes(), [
                'contact_name',
                'email',
                'phone',
                'total_amount',
                'status',
                'payment_status',
                'crm_stage',
                'priority',
                'source',
                'assigned_to',
                'next_contact_at',
                'internal_notes',
                'cancellation_reason',
            ]);

            $newStatus = (string) ($data['status'] ?? $booking->status);
            $wasClosed = in_array($booking->status, self::CLOSED_BOOKING_STATUSES, true);
            $willBeClosed = in_array($newStatus, self::CLOSED_BOOKING_STATUSES, true);

            if ($wasClosed && ! $willBeClosed) {
                $seatsToRestore = $booking->participants()
                    ->where('decision_status', '<>', 'not_going')
                    ->count();
                $this->assertCapacity($trip, $seatsToRestore, $booking);
            }

            $booking->fill(Arr::only($data, [
                'contact_name',
                'email',
                'phone',
                'total_amount',
                'status',
                'payment_status',
                'payment_provider',
                'payment_reference',
                'crm_stage',
                'priority',
                'source',
                'assigned_to',
                'next_contact_at',
                'internal_notes',
                'cancellation_reason',
            ]));

            if ($booking->email) {
                $booking->email = mb_strtolower($booking->email);
            }

            if (! empty($data['mark_contacted'])) {
                $booking->last_contact_at = now();
                $booking->contact_attempts = (int) $booking->contact_attempts + 1;
            }

            if (in_array($newStatus, ['confirmed', 'completed'], true) && ! $booking->confirmed_at) {
                $booking->confirmed_at = now();
            }

            if ($willBeClosed) {
                $booking->closed_at = $booking->closed_at ?? now();
            } elseif ($wasClosed) {
                $booking->closed_at = null;
            }

            $booking->save();

            if ($newStatus === 'confirmed') {
                $booking->participants()
                    ->where('decision_status', 'pending')
                    ->update(['decision_status' => 'going']);
            }

            if ($willBeClosed) {
                $booking->participants()->update(['decision_status' => 'not_going']);
            }

            $this->recalculateTripBookedCount($trip);

            $new = Arr::only($booking->fresh()->getAttributes(), array_keys($old));
            $changes = [];
            foreach ($new as $key => $value) {
                if ((string) ($old[$key] ?? '') !== (string) ($value ?? '')) {
                    $changes[$key] = ['from' => $old[$key] ?? null, 'to' => $value];
                }
            }

            $this->log(
                $booking,
                $actor,
                'updated',
                $changes ? 'Параметры заявки изменены.' : 'Заявка сохранена без изменения основных полей.',
                ['changes' => $changes]
            );

            return $booking->fresh(['participants', 'trip.pilgrimageRoute', 'assignedTo', 'activities.user']);
        });
    }

    public function addParticipant(Booking $booking, array $data, User $actor): BookingParticipant
    {
        return DB::transaction(function () use ($booking, $data, $actor) {
            $booking = Booking::query()->lockForUpdate()->findOrFail($booking->id);
            $trip = Trip::query()->lockForUpdate()->findOrFail($booking->trip_id);
            $decision = $data['decision_status'] ?? 'pending';

            if (! in_array($booking->status, self::CLOSED_BOOKING_STATUSES, true) && $decision !== 'not_going') {
                $this->assertCapacity($trip, 1);
            }

            $participant = $booking->participants()->create([
                'full_name' => $data['full_name'],
                'phone' => $data['phone'] ?? null,
                'email' => ! empty($data['email']) ? mb_strtolower($data['email']) : null,
                'birth_date' => $data['birth_date'] ?? null,
                'decision_status' => $decision,
                'attendance_status' => $data['attendance_status'] ?? 'pending',
                'is_primary' => false,
                'paid_amount' => (float) ($data['paid_amount'] ?? 0),
                'notes' => $data['notes'] ?? null,
            ]);

            $booking->participants_count = $booking->participants()->count();
            $booking->save();
            $this->recalculateTripBookedCount($trip);
            $this->log($booking, $actor, 'participant_added', 'Добавлен участник: '.$participant->full_name.'.', [
                'participant_id' => $participant->id,
            ]);

            return $participant;
        });
    }

    public function updateParticipant(BookingParticipant $participant, array $data, User $actor): BookingParticipant
    {
        return DB::transaction(function () use ($participant, $data, $actor) {
            $participant = BookingParticipant::query()->lockForUpdate()->findOrFail($participant->id);
            $booking = Booking::query()->lockForUpdate()->findOrFail($participant->booking_id);
            $trip = Trip::query()->lockForUpdate()->findOrFail($booking->trip_id);
            $oldDecision = $participant->decision_status;
            $newDecision = $data['decision_status'] ?? $oldDecision;

            if ($oldDecision === 'not_going'
                && $newDecision !== 'not_going'
                && ! in_array($booking->status, self::CLOSED_BOOKING_STATUSES, true)) {
                $this->assertCapacity($trip, 1);
            }

            $participant->fill(Arr::only($data, [
                'full_name',
                'phone',
                'email',
                'birth_date',
                'decision_status',
                'attendance_status',
                'paid_amount',
                'notes',
            ]));

            if ($participant->email) {
                $participant->email = mb_strtolower($participant->email);
            }

            if ($participant->attendance_status === 'attended') {
                $participant->decision_status = 'going';
            }

            $participant->save();
            $this->syncCheckInSummary($booking, $actor);
            $this->recalculateTripBookedCount($trip);
            $this->log($booking, $actor, 'participant_updated', 'Обновлён участник: '.$participant->full_name.'.', [
                'participant_id' => $participant->id,
                'decision_status' => $participant->decision_status,
                'attendance_status' => $participant->attendance_status,
            ]);

            return $participant->fresh();
        });
    }

    public function deleteParticipant(BookingParticipant $participant, User $actor): void
    {
        DB::transaction(function () use ($participant, $actor) {
            $participant = BookingParticipant::query()->lockForUpdate()->findOrFail($participant->id);
            $booking = Booking::query()->lockForUpdate()->findOrFail($participant->booking_id);
            $trip = Trip::query()->lockForUpdate()->findOrFail($booking->trip_id);

            if ($booking->participants()->count() <= 1) {
                throw ValidationException::withMessages([
                    'participant' => 'В заявке должен остаться хотя бы один участник.',
                ]);
            }

            $name = $participant->full_name;
            $wasPrimary = $participant->is_primary;
            $participant->delete();

            if ($wasPrimary) {
                $booking->participants()->orderBy('id')->first()?->update(['is_primary' => true]);
            }

            $booking->participants_count = $booking->participants()->count();
            $booking->save();
            $this->syncCheckInSummary($booking, $actor, false);
            $this->recalculateTripBookedCount($trip);
            $this->log($booking, $actor, 'participant_removed', 'Удалён участник: '.$name.'.');
        });
    }

    public function addNote(Booking $booking, string $body, User $actor, string $type = 'note'): BookingActivity
    {
        $booking->last_contact_at = now();
        $booking->contact_attempts = (int) $booking->contact_attempts + 1;
        $booking->save();

        return $this->log($booking, $actor, $type, $body);
    }

    public function markCheckedIn(Booking $booking, int $participants, User $actor): Booking
    {
        return DB::transaction(function () use ($booking, $participants, $actor) {
            $booking = Booking::query()->lockForUpdate()->findOrFail($booking->id);
            $participants = max(1, min($participants, $booking->participants_count));
            $participantRows = $booking->participants()->orderByDesc('is_primary')->orderBy('id')->get();

            foreach ($participantRows as $index => $participant) {
                $participant->update([
                    'decision_status' => $index < $participants ? 'going' : $participant->decision_status,
                    'attendance_status' => $index < $participants ? 'attended' : $participant->attendance_status,
                ]);
            }

            $booking->update([
                'checked_in_at' => now(),
                'checked_in_by' => $actor->id,
                'checked_in_participants' => $participants,
                'status' => $booking->status === 'pending' ? 'confirmed' : $booking->status,
                'confirmed_at' => $booking->confirmed_at ?? now(),
            ]);

            $this->log($booking, $actor, 'check_in', 'Выполнена регистрация на поездку. Участников: '.$participants.'.');

            return $booking->fresh(['participants', 'trip.pilgrimageRoute', 'checkedInBy']);
        });
    }

    public function recalculateTripBookedCount(Trip $trip): int
    {
        $count = BookingParticipant::query()
            ->whereHas('booking', function ($query) use ($trip) {
                $query->where('trip_id', $trip->id)
                    ->whereNotIn('status', self::CLOSED_BOOKING_STATUSES);
            })
            ->where('decision_status', '<>', 'not_going')
            ->count();

        $trip->forceFill(['booked_count' => $count])->save();

        return $count;
    }

    private function createInitialParticipants(Booking $booking, array $names): void
    {
        $names = collect($names)
            ->map(fn ($name) => trim((string) $name))
            ->filter()
            ->values();
        $decision = in_array($booking->status, ['confirmed', 'completed'], true)
            ? 'going'
            : (in_array($booking->status, self::CLOSED_BOOKING_STATUSES, true) ? 'not_going' : 'pending');

        for ($index = 0; $index < $booking->participants_count; $index++) {
            $booking->participants()->create([
                'full_name' => $names->get($index)
                    ?: ($index === 0 ? $booking->contact_name : 'Участник '.($index + 1)),
                'phone' => $index === 0 ? $booking->phone : null,
                'email' => $index === 0 ? $booking->email : null,
                'decision_status' => $decision,
                'attendance_status' => 'pending',
                'is_primary' => $index === 0,
                'paid_amount' => 0,
            ]);
        }
    }

    private function assertCapacity(Trip $trip, int $additionalSeats, ?Booking $excludeBooking = null): void
    {
        if ($trip->capacity === null || $additionalSeats <= 0) {
            return;
        }

        $current = BookingParticipant::query()
            ->whereHas('booking', function ($query) use ($trip, $excludeBooking) {
                $query->where('trip_id', $trip->id)
                    ->whereNotIn('status', self::CLOSED_BOOKING_STATUSES)
                    ->when($excludeBooking, fn ($query) => $query->where('id', '<>', $excludeBooking->id));
            })
            ->where('decision_status', '<>', 'not_going')
            ->count();

        if ($current + $additionalSeats > $trip->capacity) {
            throw ValidationException::withMessages([
                'participants_count' => 'Недостаточно свободных мест в выбранной поездке.',
            ]);
        }
    }

    private function syncCheckInSummary(Booking $booking, User $actor, bool $log = true): void
    {
        $attended = $booking->participants()->where('attendance_status', 'attended')->count();
        $booking->checked_in_participants = $attended;
        $booking->checked_in_at = $attended > 0 ? ($booking->checked_in_at ?? now()) : null;
        $booking->checked_in_by = $attended > 0 ? ($booking->checked_in_by ?? $actor->id) : null;
        $booking->save();

        if ($log) {
            $this->log($booking, $actor, 'attendance', 'Обновлена явка участников. Прибыли: '.$attended.'.');
        }
    }

    private function ticketCode(): string
    {
        do {
            $code = 'MP-'.now()->format('ymd').'-'.Str::upper(Str::random(7));
        } while (Booking::query()->where('ticket_code', $code)->exists());

        return $code;
    }

    private function log(
        Booking $booking,
        ?User $actor,
        string $type,
        string $body,
        array $metadata = []
    ): BookingActivity {
        return BookingActivity::query()->create([
            'booking_id' => $booking->id,
            'user_id' => $actor?->id,
            'type' => $type,
            'body' => $body,
            'metadata' => $metadata ?: null,
            'created_at' => now(),
        ]);
    }
}
