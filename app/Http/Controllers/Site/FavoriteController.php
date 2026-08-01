<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\FavoriteList;
use App\Models\PilgrimageObject;
use App\Services\AnalyticsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FavoriteController extends Controller
{
    public function storeList(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
        ]);

        $request->user()->favoriteLists()->create([
            'name' => $data['name'],
            'is_default' => false,
        ]);

        return back()->with('success', 'Список избранного создан.');
    }

    public function destroyList(Request $request, FavoriteList $favoriteList): RedirectResponse
    {
        abort_unless($favoriteList->user_id === $request->user()->id, 403);
        abort_if($favoriteList->is_default, 422, 'Основной список нельзя удалить.');

        $favoriteList->delete();

        return back()->with('success', 'Список удалён.');
    }

    public function addObject(
        Request $request,
        PilgrimageObject $object,
        AnalyticsService $analytics
    ): RedirectResponse {
        $data = $request->validate([
            'favorite_list_id' => ['nullable', 'integer'],
        ]);

        $list = isset($data['favorite_list_id'])
            ? $request->user()->favoriteLists()->findOrFail($data['favorite_list_id'])
            : $this->defaultList($request);

        $list->objects()->syncWithoutDetaching([$object->id]);
        $analytics->track($request, 'favorite_added', $object, [
            'favorite_list_id' => $list->id,
        ]);

        return back()->with('success', 'Объект добавлен в «'.$list->name.'».');
    }

    public function removeObject(
        Request $request,
        FavoriteList $favoriteList,
        PilgrimageObject $object,
        AnalyticsService $analytics
    ): RedirectResponse {
        abort_unless($favoriteList->user_id === $request->user()->id, 403);

        $favoriteList->objects()->detach($object->id);
        $analytics->track($request, 'favorite_removed', $object, [
            'favorite_list_id' => $favoriteList->id,
        ]);

        return back()->with('success', 'Объект удалён из списка.');
    }

    private function defaultList(Request $request): FavoriteList
    {
        return $request->user()->favoriteLists()->firstOrCreate(
            ['is_default' => true],
            ['name' => 'Избранное']
        );
    }
}
