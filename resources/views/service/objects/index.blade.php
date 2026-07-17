@extends('site.layouts.app')

@section('title', 'Мои объекты — Кабинет представителя')

@section('content')
<section class="page-hero">
    <div class="container">
        <nav aria-label="breadcrumb"><ol class="breadcrumb small mb-3"><li class="breadcrumb-item"><a href="{{ route('service.dashboard') }}">Кабинет представителя</a></li><li class="breadcrumb-item active">Мои объекты</li></ol></nav>
        <div class="section-kicker mb-2">Управление содержанием</div>
        <h1 class="section-title mb-3">Мои храмы и объекты</h1>
        <p class="section-lead mb-0">Редактирование доступно только по подтверждённым назначениям администратора.</p>
    </div>
</section>

<section class="section-space pt-5">
    <div class="container">
        <div class="row g-4">
            @forelse($assignments as $assignment)
                @php($object = $assignment->pilgrimageObject)
                <div class="col-md-6 col-xl-4">
                    <article class="card-pm">
                        @if($object && $object->coverMedia)<img class="object-cover" src="{{ $object->coverMedia->url }}" alt="{{ $object->name }}">@else<div class="object-placeholder"><i class="bi bi-buildings"></i></div>@endif
                        <div class="p-4">
                            <div class="d-flex justify-content-between gap-2 mb-3"><span class="badge rounded-pill {{ $assignment->status === 'approved' ? 'text-bg-success' : ($assignment->status === 'rejected' ? 'text-bg-danger' : 'text-bg-warning') }}">{{ ['approved' => 'Подтверждено', 'pending' => 'На проверке', 'rejected' => 'Отклонено'][$assignment->status] ?? $assignment->status }}</span><span class="small text-secondary">{{ $assignment->role === 'manager' ? 'Управляющий' : 'Редактор' }}</span></div>
                            <h2 class="object-title mb-2">{{ optional($object)->name ?: 'Объект удалён' }}</h2>
                            <div class="small text-secondary mb-4"><i class="bi bi-geo-alt me-1"></i>{{ optional($object)->address }}</div>
                            @if($object && $assignment->status === 'approved')<div class="d-flex gap-2"><a class="btn btn-outline-pm flex-grow-1" href="{{ route('service.objects.edit', $object) }}">Редактировать карточку</a><a class="btn btn-light" href="{{ route('objects.show', $object) }}" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right"></i></a></div>@elseif($assignment->note)<div class="small text-secondary">{{ $assignment->note }}</div>@endif
                        </div>
                    </article>
                </div>
            @empty
                <div class="col-12"><div class="filter-card text-center py-5"><div class="object-placeholder rounded-circle mx-auto mb-4" style="width:110px;aspect-ratio:1"><i class="bi bi-building-lock"></i></div><h2 class="h4 mb-3">Нет закреплённых объектов</h2><p class="text-secondary mb-0">Обратитесь к администратору платформы для подтверждения представительства.</p></div></div>
            @endforelse
        </div>
        @if($assignments->hasPages())<div class="mt-5">{{ $assignments->links() }}</div>@endif
    </div>
</section>
@endsection
