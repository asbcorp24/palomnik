<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\PilgrimageObject;
use App\Models\Review;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function store(Request $request, PilgrimageObject $object): RedirectResponse
    {
        $data = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'body' => ['required', 'string', 'min:10', 'max:5000'],
        ]);

        Review::query()->updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'pilgrimage_object_id' => $object->id,
            ],
            [
                'rating' => $data['rating'],
                'body' => $data['body'],
                'status' => 'pending',
                'moderated_by' => null,
                'moderated_at' => null,
            ]
        );

        return back()->with('success', 'Отзыв отправлен на модерацию.');
    }

    public function destroy(Request $request, Review $review): RedirectResponse
    {
        abort_unless($review->user_id === $request->user()->id, 403);
        $review->delete();

        return back()->with('success', 'Отзыв удалён.');
    }
}
