<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingParticipant;
use App\Models\Trip;
use App\Models\User;
use App\Services\BookingCrmService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PilgrimageCrmController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $this->filters($request);
        $query = $this->bookingQuery($filters);
        $summaryQuery = clone $query;

        $bookings = $query
            ->with([
                'trip.pilgrimageRoute',
                'assignedTo',
                'participants',
            ])
            ->withCount([
                'participants as going_count' => fn (Builder $query) => $query->where('decision_status', 'going'),
                'participants as not_going_count' => fn (Builder $query) => $query->where('decision_status', 'not_going'),
                'participants as attended_count' => fn (Builder $query) => $query->where('attendance_status', 'attended'),
            ])
            ->orderByRaw("CASE priority WHEN 'urgent' THEN 1 WHEN 'high' THEN 2 WHEN 'normal' THEN 3 ELSE 4 END")
            ->orderByRaw('CASE WHEN next_contact_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('next_contact_at')
            ->orderByDesc('created_at')
            ->paginate((int) ($filters['per_page'] ?? 25))
            ->withQueryString();

        $summary = [
            'bookings' => (clone $summaryQuery)->count(),
            'people' => (int) (clone $summaryQuery)->sum('participants_count'),
            'pending' => (clone $summaryQuery)->where('status', 'pending')->count(),
            'confirmed_people' => (int) (clone $summaryQuery)
                ->whereIn('status', ['confirmed', 'completed'])
                ->sum('participants_count'),
            'paid' => (float) (clone $summaryQuery)->where('payment_status', 'paid')->sum('total_amount'),
            'debt' => (float) (clone $summaryQuery)
                ->whereNotIn('status', BookingCrmService::CLOSED_BOOKING_STATUSES)
                ->where('payment_status', '<>', 'paid')
                ->sum('total_amount'),
            'overdue' => (clone $summaryQuery)
                ->whereNotIn('crm_stage', ['ready', 'closed'])
                ->whereNotNull('next_contact_at')
                ->where('next_contact_at', '<=', now())
                ->count(),
        ];

        return view('admin.crm.index', [
            'bookings' => $bookings,
            'filters' => $filters,
            'summary' => $summary,
            'trips' => $this->tripOptions(),
            'managers' => $this->managerOptions(),
            'bookingStatuses' => $this->bookingStatuses(),
            'paymentStatuses' => $this->paymentStatuses(),
            'crmStages' => $this->crmStages(),
            'priorities' => $this->priorities(),
            'sources' => $this->sources(),
        ]);
    }

    public function create(): View
    {
        return view('admin.crm.create', [
            'trips' => $this->tripOptions(false),
            'managers' => $this->managerOptions(),
            'bookingStatuses' => $this->bookingStatuses(),
            'paymentStatuses' => $this->paymentStatuses(),
            'crmStages' => $this->crmStages(),
            'priorities' => $this->priorities(),
            'sources' => $this->sources(),
        ]);
    }

    public function store(Request $request, BookingCrmService $service): RedirectResponse
    {
        $data = $request->validate([
            'trip_id' => ['required', 'integer', 'exists:trips,id'],
            'contact_name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:64'],
            'participants_count' => ['required', 'integer', 'min:1', 'max:100'],
            'participant_names_text' => ['nullable', 'string', 'max:10000'],
            'total_amount' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', Rule::in(array_keys($this->bookingStatuses()))],
            'payment_status' => ['required', Rule::in(array_keys($this->paymentStatuses()))],
            'crm_stage' => ['required', Rule::in(array_keys($this->crmStages()))],
            'priority' => ['required', Rule::in(array_keys($this->priorities()))],
            'source' => ['required', Rule::in(array_keys($this->sources()))],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'next_contact_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'internal_notes' => ['nullable', 'string', 'max:10000'],
        ]);

        $trip = Trip::query()->with('pilgrimageRoute')->findOrFail($data['trip_id']);
        $unitPrice = $trip->price !== null
            ? (float) $trip->price
            : (float) ($trip->pilgrimageRoute->base_price ?? 0);
        $names = preg_split('/\r\n|\r|\n/', (string) ($data['participant_names_text'] ?? '')) ?: [];

        $booking = $service->createBooking($trip, array_merge($data, [
            'total_amount' => $data['total_amount'] ?? ($unitPrice * (int) $data['participants_count']),
            'participant_names' => $names,
        ]), null, $request->user());

        return redirect()
            ->route('admin.crm.show', $booking)
            ->with('success', 'Заявка создана и добавлена в CRM.');
    }

    public function show(Booking $booking): View
    {
        $booking->load([
            'trip.pilgrimageRoute',
            'user',
            'assignedTo',
            'checkedInBy',
            'participants',
            'activities.user',
        ]);

        return view('admin.crm.show', [
            'booking' => $booking,
            'managers' => $this->managerOptions(),
            'bookingStatuses' => $this->bookingStatuses(),
            'paymentStatuses' => $this->paymentStatuses(),
            'crmStages' => $this->crmStages(),
            'priorities' => $this->priorities(),
            'sources' => $this->sources(),
            'decisionStatuses' => $this->decisionStatuses(),
            'attendanceStatuses' => $this->attendanceStatuses(),
        ]);
    }

    public function update(
        Request $request,
        Booking $booking,
        BookingCrmService $service
    ): RedirectResponse {
        $data = $request->validate([
            'contact_name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:64'],
            'total_amount' => ['required', 'numeric', 'min:0'],
            'status' => ['required', Rule::in(array_keys($this->bookingStatuses()))],
            'payment_status' => ['required', Rule::in(array_keys($this->paymentStatuses()))],
            'payment_provider' => ['nullable', 'string', 'max:64'],
            'payment_reference' => ['nullable', 'string', 'max:255'],
            'crm_stage' => ['required', Rule::in(array_keys($this->crmStages()))],
            'priority' => ['required', Rule::in(array_keys($this->priorities()))],
            'source' => ['required', Rule::in(array_keys($this->sources()))],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'next_contact_at' => ['nullable', 'date'],
            'internal_notes' => ['nullable', 'string', 'max:10000'],
            'cancellation_reason' => ['nullable', 'string', 'max:5000'],
            'mark_contacted' => ['nullable', 'boolean'],
        ]);

        $service->updateBooking($booking, $data, $request->user());

        return back()->with('success', 'Заявка обновлена.');
    }

    public function addNote(
        Request $request,
        Booking $booking,
        BookingCrmService $service
    ): RedirectResponse {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:10000'],
            'type' => ['nullable', Rule::in(['note', 'call', 'email', 'message'])],
        ]);

        $service->addNote($booking, $data['body'], $request->user(), $data['type'] ?? 'note');

        return back()->with('success', 'Контакт или комментарий добавлен в историю.');
    }

    public function storeParticipant(
        Request $request,
        Booking $booking,
        BookingCrmService $service
    ): RedirectResponse {
        $data = $this->participantData($request);
        $service->addParticipant($booking, $data, $request->user());

        return back()->with('success', 'Участник добавлен.');
    }

    public function updateParticipant(
        Request $request,
        BookingParticipant $participant,
        BookingCrmService $service
    ): RedirectResponse {
        $data = $this->participantData($request);
        $service->updateParticipant($participant, $data, $request->user());

        return back()->with('success', 'Данные участника обновлены.');
    }

    public function destroyParticipant(
        Request $request,
        BookingParticipant $participant,
        BookingCrmService $service
    ): RedirectResponse {
        $service->deleteParticipant($participant, $request->user());

        return back()->with('success', 'Участник удалён из заявки.');
    }

    public function bulkUpdate(Request $request, BookingCrmService $service): RedirectResponse
    {
        $data = $request->validate([
            'booking_ids' => ['required', 'array', 'min:1', 'max:200'],
            'booking_ids.*' => ['integer', 'exists:bookings,id'],
            'status' => ['nullable', Rule::in(array_keys($this->bookingStatuses()))],
            'payment_status' => ['nullable', Rule::in(array_keys($this->paymentStatuses()))],
            'crm_stage' => ['nullable', Rule::in(array_keys($this->crmStages()))],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $changes = collect($data)->only(['status', 'payment_status', 'crm_stage', 'assigned_to'])
            ->reject(fn ($value) => $value === null || $value === '')
            ->all();

        if (! $changes) {
            return back()->with('error', 'Выберите действие для массового изменения.');
        }

        foreach (Booking::query()->whereIn('id', $data['booking_ids'])->get() as $booking) {
            $service->updateBooking($booking, $changes, $request->user());
        }

        return back()->with('success', 'Выбранные заявки обновлены.');
    }

    public function trip(Trip $trip): View
    {
        $trip->load([
            'pilgrimageRoute',
            'bookings' => fn ($query) => $query
                ->with(['participants', 'assignedTo'])
                ->orderBy('contact_name'),
        ]);

        $participants = $trip->bookings
            ->flatMap(fn (Booking $booking) => $booking->participants->map(function ($participant) use ($booking) {
                $participant->setRelation('booking', $booking);
                return $participant;
            }));

        return view('admin.crm.trip', [
            'trip' => $trip,
            'participants' => $participants,
            'summary' => $this->tripSummary($trip, $participants),
            'decisionStatuses' => $this->decisionStatuses(),
            'attendanceStatuses' => $this->attendanceStatuses(),
            'bookingStatuses' => $this->bookingStatuses(),
            'paymentStatuses' => $this->paymentStatuses(),
        ]);
    }

    public function printTrip(Trip $trip): View
    {
        $trip->load([
            'pilgrimageRoute',
            'bookings' => fn ($query) => $query
                ->whereNotIn('status', BookingCrmService::CLOSED_BOOKING_STATUSES)
                ->with('participants')
                ->orderBy('contact_name'),
        ]);

        $participants = $trip->bookings
            ->flatMap(fn (Booking $booking) => $booking->participants->map(function ($participant) use ($booking) {
                $participant->setRelation('booking', $booking);
                return $participant;
            }));

        return view('admin.crm.print-trip', [
            'trip' => $trip,
            'participants' => $participants,
            'summary' => $this->tripSummary($trip, $participants),
            'decisionStatuses' => $this->decisionStatuses(),
            'attendanceStatuses' => $this->attendanceStatuses(),
        ]);
    }

    public function reports(Request $request): View
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'trip_id' => ['nullable', 'integer', 'exists:trips,id'],
        ]);

        $from = isset($filters['from'])
            ? Carbon::parse($filters['from'])->startOfDay()
            : now()->startOfMonth();
        $to = isset($filters['to'])
            ? Carbon::parse($filters['to'])->endOfDay()
            : now()->endOfMonth();

        $bookings = Booking::query()
            ->with(['trip.pilgrimageRoute', 'participants'])
            ->whereBetween('created_at', [$from, $to])
            ->when($filters['trip_id'] ?? null, fn (Builder $query, $tripId) => $query->where('trip_id', $tripId))
            ->get();

        $participants = $bookings->flatMap->participants;
        $activeBookings = $bookings->whereNotIn('status', BookingCrmService::CLOSED_BOOKING_STATUSES);
        $summary = [
            'bookings' => $bookings->count(),
            'people' => $participants->count(),
            'going' => $participants->where('decision_status', 'going')->count(),
            'not_going' => $participants->where('decision_status', 'not_going')->count(),
            'attended' => $participants->where('attendance_status', 'attended')->count(),
            'no_show' => $participants->where('attendance_status', 'no_show')->count(),
            'amount' => (float) $bookings->sum('total_amount'),
            'paid' => (float) $bookings->where('payment_status', 'paid')->sum('total_amount'),
            'debt' => (float) $activeBookings->where('payment_status', '<>', 'paid')->sum('total_amount'),
        ];

        $byTrip = $bookings
            ->groupBy('trip_id')
            ->map(function (Collection $group) {
                $trip = $group->first()->trip;
                return [
                    'trip' => $trip,
                    'bookings' => $group->count(),
                    'people' => $group->sum('participants_count'),
                    'confirmed' => $group->whereIn('status', ['confirmed', 'completed'])->sum('participants_count'),
                    'paid' => (float) $group->where('payment_status', 'paid')->sum('total_amount'),
                    'amount' => (float) $group->sum('total_amount'),
                ];
            })
            ->sortByDesc('people')
            ->values();

        $bySource = $bookings->groupBy('source')->map->count()->sortDesc();
        $byStatus = $bookings->groupBy('status')->map->count()->sortDesc();
        $daily = $bookings
            ->groupBy(fn (Booking $booking) => $booking->created_at->format('Y-m-d'))
            ->map(fn (Collection $group) => [
                'date' => $group->first()->created_at->format('d.m.Y'),
                'bookings' => $group->count(),
                'people' => $group->sum('participants_count'),
                'amount' => (float) $group->sum('total_amount'),
            ])
            ->values();

        return view('admin.crm.reports', [
            'filters' => [
                'from' => $from->format('Y-m-d'),
                'to' => $to->format('Y-m-d'),
                'trip_id' => $filters['trip_id'] ?? null,
            ],
            'summary' => $summary,
            'byTrip' => $byTrip,
            'bySource' => $bySource,
            'byStatus' => $byStatus,
            'daily' => $daily,
            'trips' => $this->tripOptions(),
            'bookingStatuses' => $this->bookingStatuses(),
            'sources' => $this->sources(),
        ]);
    }

    public function exportBookings(Request $request): StreamedResponse
    {
        $filters = $this->filters($request);
        $bookings = $this->bookingQuery($filters)
            ->with(['trip.pilgrimageRoute', 'assignedTo', 'participants'])
            ->orderByDesc('created_at')
            ->get();

        return response()->streamDownload(function () use ($bookings) {
            $output = fopen('php://output', 'wb');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, [
                'Код', 'Дата заявки', 'Поездка', 'Дата поездки', 'Контакт', 'Телефон', 'Email',
                'Участников', 'Едут', 'Не едут', 'Прибыли', 'Статус', 'Этап CRM', 'Оплата',
                'Сумма', 'Источник', 'Менеджер', 'Следующий контакт',
            ], ';');

            foreach ($bookings as $booking) {
                fputcsv($output, [
                    $booking->ticket_code,
                    $booking->created_at->format('d.m.Y H:i'),
                    optional(optional($booking->trip)->pilgrimageRoute)->title ?: optional($booking->trip)->title,
                    optional(optional($booking->trip)->starts_at)->format('d.m.Y H:i'),
                    $booking->contact_name,
                    $booking->phone,
                    $booking->email,
                    $booking->participants_count,
                    $booking->participants->where('decision_status', 'going')->count(),
                    $booking->participants->where('decision_status', 'not_going')->count(),
                    $booking->participants->where('attendance_status', 'attended')->count(),
                    $this->bookingStatuses()[$booking->status] ?? $booking->status,
                    $this->crmStages()[$booking->crm_stage] ?? $booking->crm_stage,
                    $this->paymentStatuses()[$booking->payment_status] ?? $booking->payment_status,
                    number_format((float) $booking->total_amount, 2, ',', ''),
                    $this->sources()[$booking->source] ?? $booking->source,
                    optional($booking->assignedTo)->name,
                    optional($booking->next_contact_at)->format('d.m.Y H:i'),
                ], ';');
            }

            fclose($output);
        }, 'crm-bookings-'.now()->format('Y-m-d-His').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function exportTrip(Trip $trip): StreamedResponse
    {
        $trip->load(['pilgrimageRoute', 'bookings.participants']);

        return response()->streamDownload(function () use ($trip) {
            $output = fopen('php://output', 'wb');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, [
                '№', 'ФИО', 'Телефон', 'Email', 'Решение', 'Явка', 'Оплачено участником',
                'Код заявки', 'Контакт заявки', 'Статус заявки', 'Оплата заявки', 'Примечание',
            ], ';');

            $number = 1;
            foreach ($trip->bookings as $booking) {
                foreach ($booking->participants as $participant) {
                    fputcsv($output, [
                        $number++,
                        $participant->full_name,
                        $participant->phone ?: $booking->phone,
                        $participant->email ?: $booking->email,
                        $this->decisionStatuses()[$participant->decision_status] ?? $participant->decision_status,
                        $this->attendanceStatuses()[$participant->attendance_status] ?? $participant->attendance_status,
                        number_format((float) $participant->paid_amount, 2, ',', ''),
                        $booking->ticket_code,
                        $booking->contact_name,
                        $this->bookingStatuses()[$booking->status] ?? $booking->status,
                        $this->paymentStatuses()[$booking->payment_status] ?? $booking->payment_status,
                        $participant->notes,
                    ], ';');
                }
            }

            fclose($output);
        }, 'trip-'.$trip->id.'-manifest.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function filters(Request $request): array
    {
        return $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'trip_id' => ['nullable', 'integer', 'exists:trips,id'],
            'status' => ['nullable', Rule::in(array_keys($this->bookingStatuses()))],
            'payment_status' => ['nullable', Rule::in(array_keys($this->paymentStatuses()))],
            'crm_stage' => ['nullable', Rule::in(array_keys($this->crmStages()))],
            'priority' => ['nullable', Rule::in(array_keys($this->priorities()))],
            'source' => ['nullable', Rule::in(array_keys($this->sources()))],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'per_page' => ['nullable', Rule::in([25, 50, 100])],
        ]);
    }

    private function bookingQuery(array $filters): Builder
    {
        return Booking::query()
            ->when(trim((string) ($filters['q'] ?? '')) !== '', function (Builder $query) use ($filters) {
                $search = trim((string) $filters['q']);
                $query->where(function (Builder $query) use ($search) {
                    $query->where('contact_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('ticket_code', 'like', "%{$search}%")
                        ->orWhereHas('participants', fn (Builder $query) => $query->where('full_name', 'like', "%{$search}%"))
                        ->orWhereHas('trip.pilgrimageRoute', fn (Builder $query) => $query->where('title', 'like', "%{$search}%"));
                });
            })
            ->when($filters['trip_id'] ?? null, fn (Builder $query, $value) => $query->where('trip_id', $value))
            ->when($filters['status'] ?? null, fn (Builder $query, $value) => $query->where('status', $value))
            ->when($filters['payment_status'] ?? null, fn (Builder $query, $value) => $query->where('payment_status', $value))
            ->when($filters['crm_stage'] ?? null, fn (Builder $query, $value) => $query->where('crm_stage', $value))
            ->when($filters['priority'] ?? null, fn (Builder $query, $value) => $query->where('priority', $value))
            ->when($filters['source'] ?? null, fn (Builder $query, $value) => $query->where('source', $value))
            ->when($filters['assigned_to'] ?? null, fn (Builder $query, $value) => $query->where('assigned_to', $value))
            ->when($filters['from'] ?? null, fn (Builder $query, $value) => $query->where('created_at', '>=', Carbon::parse($value)->startOfDay()))
            ->when($filters['to'] ?? null, fn (Builder $query, $value) => $query->where('created_at', '<=', Carbon::parse($value)->endOfDay()));
    }

    private function participantData(Request $request): array
    {
        return $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:64'],
            'email' => ['nullable', 'email', 'max:255'],
            'birth_date' => ['nullable', 'date', 'before_or_equal:today'],
            'decision_status' => ['required', Rule::in(array_keys($this->decisionStatuses()))],
            'attendance_status' => ['required', Rule::in(array_keys($this->attendanceStatuses()))],
            'paid_amount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
    }

    private function tripSummary(Trip $trip, Collection $participants): array
    {
        return [
            'capacity' => $trip->capacity,
            'booked' => $trip->booked_count,
            'total' => $participants->count(),
            'going' => $participants->where('decision_status', 'going')->count(),
            'pending' => $participants->where('decision_status', 'pending')->count(),
            'not_going' => $participants->where('decision_status', 'not_going')->count(),
            'attended' => $participants->where('attendance_status', 'attended')->count(),
            'no_show' => $participants->where('attendance_status', 'no_show')->count(),
            'paid_bookings' => $trip->bookings->where('payment_status', 'paid')->count(),
            'paid_amount' => (float) $trip->bookings->where('payment_status', 'paid')->sum('total_amount'),
            'amount' => (float) $trip->bookings->sum('total_amount'),
        ];
    }

    private function tripOptions(bool $includePast = true): Collection
    {
        return Trip::query()
            ->with('pilgrimageRoute')
            ->when(! $includePast, fn (Builder $query) => $query->where('starts_at', '>=', now()->subDay()))
            ->orderByDesc('starts_at')
            ->get();
    }

    private function managerOptions(): Collection
    {
        return User::query()
            ->whereIn('role', [User::ROLE_ADMIN, User::ROLE_SUPER_ADMIN, User::ROLE_SERVICE_MANAGER])
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);
    }

    private function bookingStatuses(): array
    {
        return [
            'pending' => 'Ожидает решения',
            'confirmed' => 'Подтверждено',
            'cancelled' => 'Отменено',
            'completed' => 'Поездка завершена',
            'refunded' => 'Возврат оформлен',
        ];
    }

    private function paymentStatuses(): array
    {
        return [
            'unpaid' => 'Не оплачено',
            'pending' => 'Ожидается оплата',
            'paid' => 'Оплачено',
            'failed' => 'Ошибка оплаты',
            'refunded' => 'Возвращено',
        ];
    }

    private function crmStages(): array
    {
        return [
            'new' => 'Новая заявка',
            'contact_pending' => 'Нужно связаться',
            'contacted' => 'Связались',
            'decision_pending' => 'Ждём решение',
            'ready' => 'Готово к поездке',
            'closed' => 'Закрыта',
        ];
    }

    private function priorities(): array
    {
        return [
            'low' => 'Низкий',
            'normal' => 'Обычный',
            'high' => 'Высокий',
            'urgent' => 'Срочный',
        ];
    }

    private function sources(): array
    {
        return [
            'site' => 'Сайт',
            'phone' => 'Телефон',
            'email' => 'Email',
            'vk' => 'VK',
            'office' => 'Личный визит',
            'partner' => 'Партнёр',
            'other' => 'Другое',
        ];
    }

    private function decisionStatuses(): array
    {
        return [
            'pending' => 'Не определился',
            'going' => 'Едет',
            'not_going' => 'Не едет',
        ];
    }

    private function attendanceStatuses(): array
    {
        return [
            'pending' => 'Не отмечен',
            'attended' => 'Прибыл',
            'no_show' => 'Не явился',
        ];
    }
}
