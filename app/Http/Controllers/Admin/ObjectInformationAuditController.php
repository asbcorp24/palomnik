<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PilgrimageObject;
use App\Services\ObjectEditorialCompletenessService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ObjectInformationAuditController extends Controller
{
    public function __construct(
        private readonly ObjectEditorialCompletenessService $completeness
    ) {
    }

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'issue' => ['nullable', 'in:stale,low_completeness,missing_schedule,missing_contacts,no_photo,no_description,missing_source,pending_update'],
            'status' => ['nullable', 'in:unverified,verified,needs_review,outdated,pending_update'],
        ]);

        $scoreSql = $this->completeness->sqlExpression();

        $query = $this->baseQuery()
            ->selectRaw($scoreSql.' as editorial_completeness_score')
            ->when($filters['q'] ?? null, function (Builder $query, string $term): void {
                $term = trim($term);
                $query->where(function (Builder $query) use ($term): void {
                    $query->where('name', 'like', "%{$term}%")
                        ->orWhere('address', 'like', "%{$term}%");
                });
            })
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('verification_status', $status));

        $this->applyIssueFilter($query, $filters['issue'] ?? null);

        if (($filters['issue'] ?? null) === 'low_completeness') {
            $query->orderBy('editorial_completeness_score');
        } else {
            $query
                ->orderByRaw("CASE verification_status WHEN 'pending_update' THEN 0 WHEN 'outdated' THEN 1 WHEN 'needs_review' THEN 2 WHEN 'unverified' THEN 3 ELSE 4 END")
                ->orderByRaw('CASE WHEN next_verification_at IS NULL THEN 0 ELSE 1 END')
                ->orderBy('next_verification_at');
        }

        $objects = $query
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        $objects->getCollection()->each(function (PilgrimageObject $object): void {
            $object->setAttribute(
                'editorial_completeness_score',
                $this->completeness->score($object)
            );
            $object->setAttribute(
                'editorial_completeness_missing',
                $this->completeness->missingLabels($object)
            );
            $object->setAttribute(
                'editorial_completeness_breakdown',
                $this->completeness->breakdown($object)
            );
        });

        $stats = [
            'stale' => $this->countForIssue('stale'),
            'low_completeness' => $this->countForIssue('low_completeness'),
            'missing_schedule' => $this->countForIssue('missing_schedule'),
            'missing_contacts' => $this->countForIssue('missing_contacts'),
            'no_photo' => $this->countForIssue('no_photo'),
            'no_description' => $this->countForIssue('no_description'),
            'missing_source' => $this->countForIssue('missing_source'),
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
            'information_source_url' => ['required', 'url', 'max:1000'],
            'next_verification_at' => ['nullable', 'date', 'after_or_equal:today'],
        ]);

        $object->update([
            'information_verified_at' => now(),
            'information_source_url' => $data['information_source_url'],
            'verified_by' => $request->user()->id,
            'next_verification_at' => $data['next_verification_at'] ?? now()->addDays(90),
            'verification_status' => PilgrimageObject::VERIFICATION_VERIFIED,
        ]);

        return back()->with('success', 'Сведения об объекте «'.$object->name.'» подтверждены.');
    }

    private function baseQuery(): Builder
    {
        return PilgrimageObject::query()
            ->with(['objectType', 'verifier', 'coverMedia'])
            ->withCount([
                'updateRequests as pending_update_requests_count' => fn (Builder $query) => $query->where('status', 'pending'),
                'media as image_media_count' => fn (Builder $query) => $query->where('type', 'image'),
                'sanctities',
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
        } elseif ($issue === 'low_completeness') {
            $query->whereRaw($this->completeness->sqlExpression().' < 30');
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
        } elseif ($issue === 'missing_source') {
            $query->where(function (Builder $query): void {
                $this->whereBlank($query, 'information_source_url');
                $query->orWhere('verification_status', '<>', PilgrimageObject::VERIFICATION_VERIFIED)
                    ->orWhereNull('verification_status');
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
