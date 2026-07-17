@extends('site.profile.layout')

@section('title', 'Избранное — Московский паломник')
@section('profile_title', 'Избранное')
@section('profile_subtitle', 'Сохраняйте объекты в основной и персональные списки.')

@section('profile_content')
<div class="profile-card mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div><div class="section-kicker mb-1">Новый список</div><h2 class="h4 mb-0">Организуйте интересные места</h2></div>
        <form class="d-flex gap-2" method="POST" action="{{ route('favorites.lists.store') }}">
            @csrf
            <input class="form-control" name="name" placeholder="Например, Поездка с семьёй" required maxlength="100">
            <button class="btn btn-pm-gold text-nowrap" type="submit"><i class="bi bi-plus-lg me-1"></i>Создать</button>
        </form>
    </div>
</div>

<div class="d-grid gap-4">
    @foreach($lists as $list)
        <section class="favorite-list">
            <div class="d-flex justify-content-between align-items-center gap-3 mb-4">
                <div>
                    <h2 class="h4 mb-1">{{ $list->name }}</h2>
                    <div class="small text-secondary">{{ $list->objects->count() }} объектов</div>
                </div>
                @unless($list->is_default)
                    <form method="POST" action="{{ route('favorites.lists.destroy', $list) }}" onsubmit="return confirm('Удалить этот список?')">
                        @csrf
                        @method('DELETE')
                        <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash"></i></button>
                    </form>
                @endunless
            </div>

            <div class="row g-3">
                @forelse($list->objects as $object)
                    <div class="col-md-6">
                        <div class="favorite-mini-card h-100">
                            @if($object->coverMedia && $object->coverMedia->url)
                                <img src="{{ $object->coverMedia->url }}" alt="{{ $object->name }}">
                            @else
                                <span class="category-icon"><i class="bi bi-buildings"></i></span>
                            @endif
                            <div class="flex-grow-1 min-w-0">
                                <div class="small text-secondary">{{ optional($object->objectType)->name }}</div>
                                <a class="fw-semibold text-decoration-none d-block text-truncate" href="{{ route('objects.show', $object) }}">{{ $object->name }}</a>
                                <div class="small text-secondary text-truncate mt-1">{{ $object->address }}</div>
                                <form class="mt-2" method="POST" action="{{ route('favorites.objects.remove', [$list, $object]) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-link text-danger p-0" type="submit">Удалить из списка</button>
                                </form>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col-12"><div class="empty-state py-4">В этом списке пока нет объектов. <a href="{{ route('objects.index') }}">Открыть каталог</a></div></div>
                @endforelse
            </div>
        </section>
    @endforeach
</div>
@endsection
