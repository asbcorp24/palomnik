@if($object->pointsOfInterest->isNotEmpty())
<section class="mb-5">
    <div class="section-kicker mb-2">Рядом с объектом</div>
    <h2 class="h2 mb-4">Полезные места</h2>

    <div class="row g-3">
        @foreach($object->pointsOfInterest as $point)
            <div class="col-md-6">
                <div class="info-card h-100 p-4">
                    <div class="d-flex align-items-start gap-3">
                        <span class="info-icon flex-shrink-0" style="color:{{ $point->marker_color }};background:color-mix(in srgb, {{ $point->marker_color }} 12%, white)">
                            <i class="bi {{ $point->category_icon }}"></i>
                        </span>
                        <div class="min-w-0 flex-grow-1">
                            <div class="small fw-semibold mb-1" style="color:{{ $point->marker_color }}">{{ $point->category_label }}</div>
                            <h3 class="h6 mb-2">{{ $point->name }}</h3>
                            <div class="small text-secondary mb-2"><i class="bi bi-geo-alt me-1"></i>{{ $point->address }}</div>

                            @if($point->description)
                                <div class="small text-secondary mb-2">{{ $point->description }}</div>
                            @endif

                            @if($point->schedule_text)
                                <div class="small text-secondary mb-2"><i class="bi bi-clock me-1"></i>{{ $point->schedule_text }}</div>
                            @endif

                            <div class="d-flex flex-wrap gap-2 mt-3">
                                <a class="btn btn-sm btn-outline-pm" href="{{ route('map', ['focus_poi' => $point->id]) }}">
                                    <i class="bi bi-map me-1"></i>На карте
                                </a>
                                @if($point->website)
                                    <a class="btn btn-sm btn-light" href="{{ $point->website }}" target="_blank" rel="noopener">Сайт</a>
                                @endif
                                @if($point->phone)
                                    <a class="btn btn-sm btn-light" href="tel:{{ preg_replace('/[^+0-9]/', '', $point->phone) }}">Позвонить</a>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</section>
@endif
