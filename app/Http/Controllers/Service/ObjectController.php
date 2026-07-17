<?php

namespace App\Http\Controllers\Service;

use App\Http\Controllers\Controller;
use App\Models\ObjectMediaSubmission;
use App\Models\PilgrimageObject;
use App\Models\Sanctity;
use App\Models\User;
use App\Notifications\PlatformNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ObjectController extends Controller
{
    public function index(Request $request): View
    {
        $assignments = $request->user()->objectRepresentatives()
            ->with(['pilgrimageObject.objectType', 'pilgrimageObject.vicariate', 'pilgrimageObject.deanery', 'pilgrimageObject.coverMedia'])
            ->orderByDesc('verified_at')
            ->paginate(20);

        return view('service.objects.index', compact('assignments'));
    }

    public function edit(Request $request, PilgrimageObject $object): View
    {
        $this->authorizeObject($request, $object);
        $object->load(['objectType', 'vicariate', 'deanery', 'sanctities', 'media']);

        return view('service.objects.edit', [
            'object' => $object,
            'sanctities' => Sanctity::query()->orderBy('name')->get(),
            'selectedSanctities' => $object->sanctities->pluck('id')->all(),
            'requests' => $object->updateRequests()->where('user_id', $request->user()->id)->latest()->limit(10)->get(),
            'mediaSubmissions' => $object->mediaSubmissions()->where('user_id', $request->user()->id)->latest()->limit(20)->get(),
        ]);
    }

    public function update(Request $request, PilgrimageObject $object): RedirectResponse
    {
        $this->authorizeObject($request, $object);

        $data = $request->validate([
            'short_description' => ['nullable', 'string', 'max:2000'],
            'description' => ['nullable', 'string', 'max:30000'],
            'history' => ['nullable', 'string', 'max:30000'],
            'address' => ['required', 'string', 'max:500'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'phone' => ['nullable', 'string', 'max:64'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
            'schedule_text' => ['nullable', 'string', 'max:30000'],
            'parking_info' => ['nullable', 'string', 'max:5000'],
            'accessibility_info' => ['nullable', 'string', 'max:5000'],
            'sanctity_ids' => ['nullable', 'array'],
            'sanctity_ids.*' => ['integer', 'exists:sanctities,id'],
        ]);

        $requestModel = $object->updateRequests()->create([
            'user_id' => $request->user()->id,
            'payload' => $data,
            'status' => 'pending',
        ]);

        $admins = User::query()->whereIn('role', [User::ROLE_ADMIN, User::ROLE_SUPER_ADMIN])->where('is_active', true)->get();
        Notification::send($admins, new PlatformNotification(
            'Изменения карточки храма',
            $request->user()->name.' отправил изменения объекта «'.$object->name.'».',
            route('admin.service-review.index'),
            'bi-building-check'
        ));

        return back()->with('success', 'Изменения отправлены администратору на проверку. До одобрения на сайте остаётся текущая версия карточки.');
    }

    public function storeMedia(Request $request, PilgrimageObject $object): RedirectResponse
    {
        $this->authorizeObject($request, $object);

        $data = $request->validate([
            'files' => ['required', 'array', 'min:1', 'max:10'],
            'files.*' => ['file', 'max:51200', 'mimes:jpg,jpeg,png,webp,gif,mp3,wav,m4a,mp4,mov,avi,pdf,doc,docx'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        foreach ($request->file('files', []) as $file) {
            $mime = (string) $file->getMimeType();
            $type = Str::startsWith($mime, 'image/') ? 'image'
                : (Str::startsWith($mime, 'video/') ? 'video'
                    : (Str::startsWith($mime, 'audio/') ? 'audio' : 'document'));

            ObjectMediaSubmission::query()->create([
                'pilgrimage_object_id' => $object->id,
                'user_id' => $request->user()->id,
                'type' => $type,
                'path' => $file->store('service-submissions/'.$object->id, 'public'),
                'title' => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
                'description' => $data['description'] ?? null,
                'status' => 'pending',
            ]);
        }

        $admins = User::query()->whereIn('role', [User::ROLE_ADMIN, User::ROLE_SUPER_ADMIN])->where('is_active', true)->get();
        Notification::send($admins, new PlatformNotification(
            'Новые материалы объекта',
            $request->user()->name.' загрузил материалы для объекта «'.$object->name.'».',
            route('admin.service-review.index'),
            'bi-images'
        ));

        return back()->with('success', 'Материалы загружены и отправлены на модерацию.');
    }

    private function authorizeObject(Request $request, PilgrimageObject $object): void
    {
        if ($request->user()->isAdmin()) {
            return;
        }

        abort_unless(
            $request->user()->objectRepresentatives()
                ->where('pilgrimage_object_id', $object->id)
                ->where('status', 'approved')
                ->exists(),
            403
        );
    }
}
