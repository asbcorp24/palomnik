<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PilgrimageObject;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ObjectInformationAuditController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'issue' => ['nullable', 'in:stale,missing_schedule,missing_contacts,no_photo,no_description,pending_update'],
            'status' => ['nullable', 'in:unverified,verified,needs_review,outdated,pending_update'],
        ]);

        $query = $this->baseQuery()
            ->when($filters['q'] ?? null, function (Builder $query, string $term): void {
                $term = trim($term);
                $query->where(function (Builder $query) use ($term): void {
                    $query->where('name', 'like', "%{$term}%")
                        ->orWhere('address', 'like', "%{$term}%");
                });
            })
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('verification_status', $status));

        $this->applyIssueFilter($query, $filters['issue'] ?? null);

        $objects = $query
            ->orderByRaw("CASE verification_status WHEN 'pending_update' THEN 0 WHEN 'outdated' THEN 1 WHEN 'needs_review' THEN 2 WHEN 'unverified' THEN 3 ELSE 4 END")
            ->orderByRaw('CASE WHEN next_verification_at IS NULL THEN 0 ELSE 1 END')
            ->orderBy('next_verification_at')
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        $stats = [
            'stale' => $this->countForIssue('stale'),
            'missing_schedule' => $this->countForIssue('missing_schedule'),
            'missing_contacts' => $this->countForIssue('missing_contacts'),
            'no_photo' => $this->countForIssue('no_photo'),
            'no_description' => $this->countForIssue('no_description'),
            'pending_update' => $this->countForIssue('pending_update'),
        ];

        return view('admin.information-audit.index', [
            'objects' => $objects,
            'filters' => $filters,
            'stats' => $stats,
            'statusLabels' => PilgrimageObject::verificationStatusLabels(),
        ]);
    }

    public function verify(Request $request, PilgrimageObject $object): RedirectResponse
    {
        $data = $request->validate([
            'information_source_url' => ['nullable', 'url', 'max:1000'],
            'next_verification_at' => ['nullable', 'date', 'after_or_equal:today'],
        ]);

        $object->update([
            'information_verified_at' => now(),
            'information_source_url' => $data['information_source_url'] ?? $object->information_source_url,
            'verified_by' => $request->user()->id,
            'next_verification_at' => $data['next_verification_at'] ?? now()->addDays(90),
            'verification_status' => PilgrimageObject::VERIFICATION_VERIFIED,
        ]);

        return back()->with('success', 'Сведения об объекте «'.$object->name.'» подтверждены на 90 дней.');
    }

    private function baseQuery(): Builder
    {
        return PilgrimageObject::query()
            ->with(['objectType', 'verifier', 'coverMedia'])
            ->withCount([
                'updateRequests as pending_update_requests_count' => fn (Builder $query) => $query->where('status', 'pending'),
                'media as image_media_count' => fn (Builder $query) => $query->where('type', 'image'),
            ]);
    }

    private function countForIssue(string $issue): int
    {
        $query = PilgrimageObject::query();
        $this->applyIssueFilter($query, $issue);

        return $query->count();
    }

    private function applyIssueFilter(Builder $query, ?string $issue): void
    {
        if ($issue === 'stale') {
            $query->where(function (Builder $query): void {
                $query->whereNull('information_verified_at')
                    ->orWhere('information_verified_at', '<=', now()->subDays(90))
                    ->orWhere('next_verification_at', '<', now())
                    ->orWhereIn('verification_status', [
                        PilgrimageObject::VERIFICATION_UNVERIFIED,
                        PilgrimageObject::VERIFICATION_NEEDS_REVIEW,
                        PilgrimageObject::VERIFICATION_OUTDATED,
                    ]);
            });
        } elseif ($issue === 'missing_schedule') {
            $this->whereBlank($query, 'schedule_text');
        } elseif ($issue === 'missing_contacts') {
            $query->where(function (Builder $query): void {
                $this->whereBlank($query, 'phone');
                $this->whereBlank($query, 'email');
                $this->whereBlank($query, 'website');
            });
        } elseif ($issue === 'no_photo') {
            $query->whereDoesntHave('media', fn (Builder $query) => $query->where('type', 'image'));
        } elseif ($issue === 'no_description') {
            $query->where(function (Builder $query): void {
                $this->whereBlank($query, 'short_description');
                $this->whereBlank($query, 'description');
            });
        } elseif ($issue === 'pending_update') {
            $query->whereHas('updateRequests', fn (Builder $query) => $query->where('status', 'pending'));
        }
    }

    private function whereBlank(Builder $query, string $column): void
    {
        $query->where(function (Builder $query) use ($column): void {
            $query->whereNull($column)
                ->orWhereRaw('TRIM(COALESCE('.$column.", '')) = ''");
        });
    }
}
