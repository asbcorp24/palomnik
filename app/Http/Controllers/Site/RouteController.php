<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\PilgrimageRoute;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RouteController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:64'],
            'difficulty' => ['nullable', 'string', 'max:32'],
        ]);

        $routes = PilgrimageRoute::query()
            ->published()
            ->withCount(['objects', 'trips'])
            ->when($filters['q'] ?? null, function (Builder $query, string $term) {
                $term = trim($term);
                $query->where(function (Builder $query) use ($term) {
                    $query->where('title', 'like', "%{$term}%")
                        ->orWhere('short_description', 'like', "%{$term}%")
                        ->orWhere('description', 'like', "%{$term}%");
                });
            })
            ->when($filters['category'] ?? null, fn (Builder $query, string $category) => $query->where('category', $category))
            ->when($filters['difficulty'] ?? null, fn (Builder $query, string $difficulty) => $query->where('difficulty', $difficulty))
            ->orderByDesc('published_at')
            ->paginate(12)
            ->withQueryString();

        return view('site.routes.index', [
            'routes' => $routes,
            'filters' => $filters,
            'categories' => $this->categories(),
            'difficulties' => $this->difficulties(),
        ]);
    }

    public function show(PilgrimageRoute $pilgrimageRoute): View
    {
        $isScheduledForFuture = $pilgrimageRoute->published_at && $pilgrimageRoute->published_at->isFuture();
        abort_if(! $pilgrimageRoute->is_published || $isScheduledForFuture, 404);

        $pilgrimageRoute->load([
            'objects.objectType',
            'objects.coverMedia',
            'trips' => function ($query) {
                $query->whereIn('status', ['planned', 'open'])
                    ->where('starts_at', '>=', now())
                    ->orderBy('starts_at');
            },
        ]);

        return view('site.routes.show', [
            'pilgrimageRoute' => $pilgrimageRoute,
            'categories' => $this->categories(),
            'difficulties' => $this->difficulties(),
        ]);
    }

    private function categories(): array
    {
        return [
            'one_day' => 'Однодневный',
            'multi_day' => 'Многодневный',
            'thematic' => 'Тематический',
            'family' => 'Семейный',
            'youth' => 'Молодёжный',
            'individual' => 'Индивидуальный',
        ];
    }

    private function difficulties(): array
    {
        return [
            'easy' => 'Лёгкий',
            'medium' => 'Средний',
            'hard' => 'Сложный',
        ];
    }
}
