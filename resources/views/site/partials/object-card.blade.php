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
            @if($object->getAttribute('distance_km') !== null)
                @php
                    $distanceKm = (float) $object->getAttribute('distance_km');
                    $distanceLabel = $distanceKm < 1
                        ? max(1, (int) round($distanceKm * 1000)).' м'
                        : number_format($distanceKm, 1, ',', ' ').' км';
                @endphp
                <span class="badge rounded-pill text-bg-light"><i class="bi bi-signpost-2 me-1"></i>{{ $distanceLabel }}</span>
            @elseif($object->vicariate)
                <span class="small text-secondary text-truncate">{{ $object->vicariate->name }}</span>
            @endif
        </div>
        <h3 class="object-title mb-2"><a class="text-decoration-none" href="{{ route('objects.show', $object) }}">{{ $object->name }}</a></h3>
        <div class="object-meta mb-2"><i class="bi bi-geo-alt me-1"></i>{{ $object->address }}</div>
        @if($object->parentObject)
            <div class="small text-secondary mb-3"><i class="bi bi-diagram-2 me-1"></i>В составе: {{ $object->parentObject->name }}</div>
        @elseif(($object->published_child_objects_count ?? 0) > 0)
            <div class="small text-secondary mb-3"><i class="bi bi-diagram-3 me-1"></i>На территории: {{ $object->published_child_objects_count }} связанных объектов</div>
        @endif
        @if($object->short_description)
            <p class="text-secondary small mb-3">{{ \Illuminate\Support\Str::limit($object->short_description, 145) }}</p>
        @endif
        <a class="text-decoration-none fw-semibold" style="color:var(--pm-green)" href="{{ route('objects.show', $object) }}">Открыть карточку <i class="bi bi-arrow-right ms-1"></i></a>
    </div>
</article>
