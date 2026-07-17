<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use App\Models\Booking;
use App\Models\Review;
use App\Models\UserMedia;
use App\Models\Visit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ModerationController extends Controller
{
    public function index(Request $request, string $resource): View
    {
        $config = $this->config($resource);
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:64'],
        ]);

        $query = $config['model']::query()->with($config['with']);
        $search = trim((string) ($filters['q'] ?? ''));

        if ($search !== '') {
            $query->where(function (Builder $query) use ($resource, $search) {
                if ($resource === 'bookings') {
                    $query->where('contact_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('ticket_code', 'like', "%{$search}%");
                } elseif ($resource === 'posts') {
                    $query->where('title', 'like', "%{$search}%")
                        ->orWhereHas('user', fn (Builder $query) => $query->where('name', 'like', "%{$search}%"));
                } elseif ($resource === 'media') {
                    $query->where('title', 'like', "%{$search}%")
                        ->orWhereHas('user', fn (Builder $query) => $query->where('name', 'like', "%{$search}%"));
                } else {
                    $query->whereHas('user', function (Builder $query) use ($search) {
                        $query->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    })->orWhereHas('pilgrimageObject', function (Builder $query) use ($search) {
                        $query->where('name', 'like', "%{$search}%");
                    });
                }
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $items = $query
            ->orderByDesc($config['order_by'])
            ->paginate(25)
            ->withQueryString();

        return view('admin.moderation.index', [
            'resource' => $resource,
            'config' => $config,
            'items' => $items,
            'filters' => $filters,
        ]);
    }

    public function update(Request $request, string $resource, int $id): RedirectResponse
    {
        $config = $this->config($resource);
        $item = $config['model']::query()->findOrFail($id);

        $rules = [
            'status' => ['required', Rule::in(array_keys($config['statuses']))],
            'notes' => ['nullable', 'string'],
        ];

        if ($resource === 'bookings') {
            $rules['payment_status'] = ['required', Rule::in(array_keys($config['payment_statuses']))];
        }

        $data = $request->validate($rules);
        $item->status = $data['status'];

        if ($resource === 'bookings') {
            $item->payment_status = $data['payment_status'];
            $item->notes = $data['notes'] ?? $item->notes;
        } elseif (in_array($resource, ['reviews', 'posts', 'media'], true)) {
            $item->moderated_by = auth()->id();
            $item->moderated_at = now();

            if ($resource === 'posts') {
                $item->published_at = $data['status'] === 'published'
                    ? ($item->published_at ?? now())
                    : null;
            }
        } elseif ($resource === 'visits') {
            $item->notes = $data['notes'] ?? $item->notes;
        }

        $item->save();

        return back()->with('success', 'Статус обновлён.');
    }

    public function destroy(string $resource, int $id): RedirectResponse
    {
        $config = $this->config($resource);
        $item = $config['model']::query()->findOrFail($id);

        if ($resource === 'media' && $item->path) {
            Storage::disk('public')->delete($item->path);
        }

        $item->delete();

        return back()->with('success', 'Запись удалена.');
    }

    private function config(string $resource): array
    {
        $resources = [
            'bookings' => [
                'model' => Booking::class,
                'title' => 'Бронирования и билеты',
                'single' => 'Бронирование',
                'icon' => 'bi-ticket-perforated',
                'with' => ['trip.pilgrimageRoute', 'user'],
                'order_by' => 'created_at',
                'statuses' => [
                    'pending' => 'Ожидает подтверждения',
                    'confirmed' => 'Подтверждено',
                    'cancelled' => 'Отменено',
                    'completed' => 'Завершено',
                    'refunded' => 'Возвращено',
                ],
                'payment_statuses' => [
                    'unpaid' => 'Не оплачено',
                    'pending' => 'Платёж обрабатывается',
                    'paid' => 'Оплачено',
                    'failed' => 'Ошибка оплаты',
                    'refunded' => 'Возвращено',
                ],
            ],
            'visits' => [
                'model' => Visit::class,
                'title' => 'Отметки о посещениях',
                'single' => 'Посещение',
                'icon' => 'bi-geo-fill',
                'with' => ['user', 'pilgrimageObject'],
                'order_by' => 'visited_at',
                'statuses' => [
                    'pending' => 'На проверке',
                    'verified' => 'Подтверждено',
                    'rejected' => 'Отклонено',
                ],
            ],
            'reviews' => [
                'model' => Review::class,
                'title' => 'Отзывы пользователей',
                'single' => 'Отзыв',
                'icon' => 'bi-chat-square-text',
                'with' => ['user', 'pilgrimageObject'],
                'order_by' => 'created_at',
                'statuses' => [
                    'pending' => 'На модерации',
                    'published' => 'Опубликован',
                    'rejected' => 'Отклонён',
                ],
            ],
            'posts' => [
                'model' => BlogPost::class,
                'title' => 'Блог и путевые заметки',
                'single' => 'Публикация',
                'icon' => 'bi-journal-richtext',
                'with' => ['user'],
                'order_by' => 'created_at',
                'statuses' => [
                    'draft' => 'Черновик',
                    'pending' => 'На модерации',
                    'published' => 'Опубликовано',
                    'rejected' => 'Отклонено',
                ],
            ],
            'media' => [
                'model' => UserMedia::class,
                'title' => 'Фото и видео пользователей',
                'single' => 'Медиаматериал',
                'icon' => 'bi-camera',
                'with' => ['user', 'pilgrimageObject', 'blogPost'],
                'order_by' => 'created_at',
                'statuses' => [
                    'pending' => 'На модерации',
                    'published' => 'Опубликовано',
                    'rejected' => 'Отклонено',
                ],
            ],
        ];

        abort_unless(isset($resources[$resource]), 404);

        return $resources[$resource];
    }
}
