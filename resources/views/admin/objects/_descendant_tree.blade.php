@foreach($children as $child)
    <div class="{{ $level > 0 ? 'ms-4 mt-2' : ($loop->first ? '' : 'mt-2') }}">
        <a
            class="d-flex align-items-center gap-3 rounded-3 border bg-white p-3 text-decoration-none text-body shadow-sm"
            href="{{ route('admin.objects.edit', $child) }}"
            aria-label="Открыть карточку {{ $child->name }}"
        >
            <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-light flex-shrink-0" style="width:38px;height:38px">
                <i class="bi {{ $child->childObjects->isNotEmpty() ? 'bi-diagram-3' : 'bi-building' }} text-success"></i>
            </span>

            <span class="min-w-0 flex-grow-1">
                <span class="d-block fw-semibold text-truncate">{{ $child->name }}</span>
                <span class="d-flex flex-wrap align-items-center gap-2 small text-secondary">
                    @if($child->objectType)
                        <span>{{ $child->objectType->name }}</span>
                    @endif
                    <span class="badge {{ $child->is_published ? 'badge-published' : 'bg-secondary-subtle text-secondary-emphasis' }}">
                        {{ $child->is_published ? 'Опубликован' : 'Черновик' }}
                    </span>
                    @if($child->childObjects->isNotEmpty())
                        <span>{{ $child->childObjects->count() }} дочерн.</span>
                    @endif
                </span>
            </span>

            <i class="bi bi-chevron-right text-secondary flex-shrink-0"></i>
        </a>

        @if($child->childObjects->isNotEmpty())
            @include('admin.objects._descendant_tree', [
                'children' => $child->childObjects,
                'level' => $level + 1,
            ])
        @endif
    </div>
@endforeach
