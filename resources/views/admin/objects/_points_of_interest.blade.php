@include('admin.audio-guides.form', [
    'guideable' => $object,
    'updateRouteName' => 'admin.objects.audio-guide.update',
    'destroyRouteName' => 'admin.objects.audio-guide.destroy',
    'fieldPrefix' => 'object-edit',
])

<div class="card-soft p-4 mt-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <div>
            <h2 class="h5 mb-1">Точки интереса рядом</h2>
            <div class="small text-secondary">Стоянки, кафе, гостиницы и другие полезные места, привязанные к этому объекту.</div>
        </div>
        <div class="d-flex gap-2">
            <a class="btn btn-light" href="{{ route('admin.points-of-interest.index', ['object_id' => $object->id]) }}">
                Все точки ({{ $object->pointsOfInterest->count() }})
            </a>
            <a class="btn btn-outline-green" href="{{ route('admin.points-of-interest.create', ['object_id' => $object->id]) }}">
                <i class="bi bi-plus-lg me-1"></i>Добавить точку
            </a>
        </div>
    </div>

    <div class="row g-3">
        @forelse($object->pointsOfInterest as $point)
            <div class="col-md-6 col-xl-4">
                <div class="border rounded-4 p-3 bg-white h-100">
                    <div class="d-flex justify-content-between gap-3 mb-2">
                        <span class="badge rounded-pill" style="background:{{ $point->marker_color }};color:#fff">
                            <i class="bi {{ $point->category_icon }} me-1"></i>{{ $point->category_label }}
                        </span>
                        <span class="badge {{ $point->is_published ? 'badge-published' : 'badge-draft' }}">{{ $point->is_published ? 'На карте' : 'Скрыта' }}</span>
                    </div>
                    <div class="fw-semibold mb-2">{{ $point->name }}</div>
                    <div class="small text-secondary mb-3"><i class="bi bi-geo-alt me-1"></i>{{ $point->address }}</div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="small text-secondary">{{ number_format((float)$point->latitude, 5) }}, {{ number_format((float)$point->longitude, 5) }}</span>
                        <div class="text-nowrap">
                            <a class="btn btn-sm btn-light" href="{{ route('admin.points-of-interest.edit', $point) }}"><i class="bi bi-pencil"></i></a>
                            <form class="d-inline" method="POST" action="{{ route('admin.points-of-interest.destroy', $point) }}" onsubmit="return confirm('Удалить точку интереса?')">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-light text-danger" type="submit"><i class="bi bi-trash"></i></button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12">
                <div class="border rounded-4 p-4 text-center text-secondary">К этому объекту пока не добавлены точки интереса.</div>
            </div>
        @endforelse
    </div>
</div>