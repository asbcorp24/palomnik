<section id="objectHierarchy" class="py-4 border-bottom bg-light-subtle">
    <div class="container">
        @if($hierarchyParent)
            <div class="info-card p-3 p-md-4 mb-4">
                <div class="d-flex flex-column flex-md-row align-items-md-center gap-3">
                    @if($hierarchyParent->coverMedia && $hierarchyParent->coverMedia->url)
                        <a class="flex-shrink-0" href="{{ route('objects.show', $hierarchyParent) }}">
                            <img
                                src="{{ $hierarchyParent->coverMedia->url }}"
                                alt="{{ $hierarchyParent->name }}"
                                loading="lazy"
                                style="width:96px;height:76px;object-fit:cover;border-radius:16px"
                            >
                        </a>
                    @else
                        <span class="info-icon flex-shrink-0" style="width:56px;height:56px">
                            <i class="bi bi-buildings"></i>
                        </span>
                    @endif

                    <div class="flex-grow-1">
                        <div class="section-kicker mb-1">Храм монастыря</div>
                        <div class="small text-secondary mb-1">Этот храм находится на территории монастыря</div>
                        <h2 class="h5 mb-1">
                            <a class="text-decoration-none text-dark" href="{{ route('objects.show', $hierarchyParent) }}">
                                {{ $hierarchyParent->name }}
                            </a>
                        </h2>

                        @if($hierarchyParent->address)
                            <div class="small text-secondary">
                                <i class="bi bi-geo-alt me-1"></i>{{ $hierarchyParent->address }}
                            </div>
                        @endif
                    </div>

                    <div class="d-flex flex-column flex-sm-row gap-2 flex-shrink-0">
                        <a class="btn btn-outline-pm" href="{{ route('objects.show', $hierarchyParent) }}">
                            <i class="bi bi-buildings me-1"></i>Открыть монастырь
                        </a>
                        <a class="btn btn-light" href="{{ route('map', ['focus_slug' => $hierarchyParent->slug]) }}">
                            <i class="bi bi-map me-1"></i>На карте
                        </a>
                    </div>
                </div>
            </div>
        @endif

        @if($hierarchyChildren->isNotEmpty())
            <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-3">
                <div>
                    <div class="section-kicker mb-1">На территории</div>
                    <h2 class="h3 mb-0">Храмы монастыря</h2>
                </div>
                <div class="small text-secondary">
                    {{ $hierarchyChildren->count() }} {{ trans_choice('храм|храма|храмов', $hierarchyChildren->count()) }}
                </div>
            </div>

            <div class="row g-3">
                @foreach($hierarchyChildren as $child)
                    <div class="col-md-6 col-xl-4">
                        <article class="info-card h-100 overflow-hidden p-0">
                            @if($child->coverMedia && $child->coverMedia->url)
                                <a href="{{ route('objects.show', $child) }}">
                                    <img
                                        class="w-100"
                                        src="{{ $child->coverMedia->url }}"
                                        alt="{{ $child->name }}"
                                        loading="lazy"
                                        style="height:165px;object-fit:cover"
                                    >
                                </a>
                            @else
                                <a class="object-placeholder d-flex align-items-center justify-content-center text-decoration-none" href="{{ route('objects.show', $child) }}" style="height:165px">
                                    <i class="bi {{ optional($child->objectType)->slug === 'chapel' ? 'bi-house-heart' : 'bi-bank2' }} fs-1"></i>
                                </a>
                            @endif

                            <div class="p-3 p-md-4">
                                <div class="small text-secondary mb-2">
                                    {{ optional($child->objectType)->name ?: 'Храм' }}
                                </div>
                                <h3 class="h6 mb-2">
                                    <a class="text-decoration-none text-dark" href="{{ route('objects.show', $child) }}">
                                        {{ $child->name }}
                                    </a>
                                </h3>

                                @if($child->address)
                                    <div class="small text-secondary mb-3">
                                        <i class="bi bi-geo-alt me-1"></i>{{ $child->address }}
                                    </div>
                                @endif

                                <div class="d-flex gap-2 mt-auto">
                                    <a class="btn btn-sm btn-outline-pm flex-grow-1" href="{{ route('objects.show', $child) }}">
                                        Открыть храм
                                    </a>
                                    <a class="btn btn-sm btn-light" href="{{ route('map', ['focus_slug' => $child->slug]) }}" title="Показать на карте">
                                        <i class="bi bi-map"></i>
                                    </a>
                                </div>
                            </div>
                        </article>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</section>
