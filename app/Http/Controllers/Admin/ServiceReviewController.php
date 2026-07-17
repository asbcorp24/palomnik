<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ObjectMediaSubmission;
use App\Models\ObjectUpdateRequest;
use App\Notifications\PlatformNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ServiceReviewController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', 'in:pending,approved,rejected'],
            'type' => ['nullable', 'in:updates,media'],
        ]);

        $status = $filters['status'] ?? 'pending';

        $updates = ObjectUpdateRequest::query()
            ->with(['pilgrimageObject', 'user'])
            ->when($filters['type'] === 'media', fn ($query) => $query->whereRaw('1 = 0'))
            ->where('status', $status)
            ->latest()
            ->paginate(15, ['*'], 'updates_page')
            ->withQueryString();

        $media = ObjectMediaSubmission::query()
            ->with(['pilgrimageObject', 'user'])
            ->when($filters['type'] === 'updates', fn ($query) => $query->whereRaw('1 = 0'))
            ->where('status', $status)
            ->latest()
            ->paginate(20, ['*'], 'media_page')
            ->withQueryString();

        return view('admin.service-review.index', compact('updates', 'media', 'filters', 'status'));
    }

    public function updateRequest(Request $request, ObjectUpdateRequest $updateRequest): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['approved', 'rejected'])],
            'review_note' => ['nullable', 'string', 'max:3000'],
        ]);

        abort_unless($updateRequest->status === 'pending', 422, 'Эта заявка уже рассмотрена.');

        DB::transaction(function () use ($request, $updateRequest, $data) {
            if ($data['status'] === 'approved') {
                $payload = $updateRequest->payload;
                $sanctityIds = $payload['sanctity_ids'] ?? null;
                unset($payload['sanctity_ids']);

                $updateRequest->pilgrimageObject->update($payload);
                if (is_array($sanctityIds)) {
                    $updateRequest->pilgrimageObject->sanctities()->sync($sanctityIds);
                }
            }

            $updateRequest->update([
                'status' => $data['status'],
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'review_note' => $data['review_note'] ?? null,
            ]);
        });

        $updateRequest->user->notify(new PlatformNotification(
            $data['status'] === 'approved' ? 'Изменения опубликованы' : 'Изменения отклонены',
            'Заявка по объекту «'.$updateRequest->pilgrimageObject->name.'» рассмотрена. '.($data['review_note'] ?? ''),
            route('service.objects.edit', $updateRequest->pilgrimageObject),
            $data['status'] === 'approved' ? 'bi-check-circle' : 'bi-x-circle'
        ));

        return back()->with('success', 'Заявка представителя рассмотрена.');
    }

    public function media(Request $request, ObjectMediaSubmission $submission): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['approved', 'rejected'])],
            'review_note' => ['nullable', 'string', 'max:3000'],
        ]);

        abort_unless($submission->status === 'pending', 422, 'Этот материал уже рассмотрен.');

        DB::transaction(function () use ($request, $submission, $data) {
            if ($data['status'] === 'approved') {
                $sortOrder = (int) $submission->pilgrimageObject->media()->max('sort_order');
                $hasCover = $submission->pilgrimageObject->media()->where('is_cover', true)->exists();

                $submission->pilgrimageObject->media()->create([
                    'type' => $submission->type,
                    'path' => $submission->path,
                    'title' => $submission->title,
                    'description' => $submission->description,
                    'sort_order' => $sortOrder + 1,
                    'is_cover' => ! $hasCover && $submission->type === 'image',
                ]);
            } else {
                Storage::disk('public')->delete($submission->path);
            }

            $submission->update([
                'status' => $data['status'],
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'review_note' => $data['review_note'] ?? null,
            ]);
        });

        $submission->user->notify(new PlatformNotification(
            $data['status'] === 'approved' ? 'Материал опубликован' : 'Материал отклонён',
            'Материал для объекта «'.$submission->pilgrimageObject->name.'» рассмотрен. '.($data['review_note'] ?? ''),
            route('service.objects.edit', $submission->pilgrimageObject),
            $data['status'] === 'approved' ? 'bi-image' : 'bi-x-circle'
        ));

        return back()->with('success', 'Материал рассмотрен.');
    }
}
