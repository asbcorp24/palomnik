<article class="card-pm">
    <a class="text-decoration-none" href="{{ route('objects.show', $object) }}">
        @if($object->coverMedia && $object->coverMedia->url)
            <img class="object-cover" src="{{ $object->coverMedia->url }}" alt="{{ $object->name }}" loading="lazy">
        @else
            <div class="object-placeholder"><i class="bi bi-buildings"></i></div>
        @endif
    </a>
    <div class="p-4">
        <div class="d-flex align-items-center justify-content-between gap-2 mb-3">
            <span class="badge rounded-pill object-type-badge">{{ optional($object->objectType)->name ?: 'Паломнический объект' }}</span>
            @if($object->vicariate)
                <span class="small text-secondary text-truncate">{{ $object->vicariate->name }}</span>
            @endif
        </div>
        <h3 class="object-title mb-2"><a class="text-decoration-none" href="{{ route('objects.show', $object) }}">{{ $object->name }}</a></h3>
        <div class="object-meta mb-3"><i class="bi bi-geo-alt me-1"></i>{{ $object->address }}</div>
        @if($object->short_description)
            <p class="text-secondary small mb-3">{{ \Illuminate\Support\Str::limit($object->short_description, 145) }}</p>
        @endif
        <a class="text-decoration-none fw-semibold" style="color:var(--pm-green)" href="{{ route('objects.show', $object) }}">Открыть карточку <i class="bi bi-arrow-right ms-1"></i></a>
    </div>
</article>
