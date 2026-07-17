@extends('site.layouts.app')

@section('title', 'Редактирование: '.$object->name)

@section('content')
<section class="page-hero">
    <div class="container">
        <nav aria-label="breadcrumb"><ol class="breadcrumb small mb-3"><li class="breadcrumb-item"><a href="{{ route('service.dashboard') }}">Кабинет представителя</a></li><li class="breadcrumb-item"><a href="{{ route('service.objects.index') }}">Мои объекты</a></li><li class="breadcrumb-item active">{{ $object->name }}</li></ol></nav>
        <div class="row align-items-end g-4"><div class="col-lg-8"><div class="section-kicker mb-2">Карточка объекта</div><h1 class="section-title mb-3">{{ $object->name }}</h1><p class="section-lead mb-0">Изменения текста и контактов проходят проверку администратора перед публикацией.</p></div><div class="col-lg-4 text-lg-end"><a class="btn btn-outline-pm" href="{{ route('objects.show', $object) }}" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right me-2"></i>Открыть на сайте</a></div></div>
    </div>
</section>

<section class="section-space pt-5">
    <div class="container">
        <div class="row g-5">
            <div class="col-xl-8">
                <form class="filter-card p-4 p-lg-5" method="POST" action="{{ route('service.objects.update', $object) }}">
                    @csrf @method('PUT')
                    <div class="d-flex flex-wrap gap-2 mb-4"><span class="badge rounded-pill object-type-badge">{{ optional($object->objectType)->name }}</span>@if($object->vicariate)<span class="badge rounded-pill text-bg-light">{{ $object->vicariate->name }}</span>@endif @if($object->deanery)<span class="badge rounded-pill text-bg-light">{{ $object->deanery->name }}</span>@endif</div>

                    <div class="row g-4">
                        <div class="col-12"><label class="form-label" for="short_description">Краткое описание</label><textarea class="form-control" id="short_description" name="short_description" rows="3">{{ old('short_description', $object->short_description) }}</textarea></div>
                        <div class="col-12"><label class="form-label" for="description">Полное описание</label><textarea class="form-control" id="description" name="description" rows="8">{{ old('description', $object->description) }}</textarea></div>
                        <div class="col-12"><label class="form-label" for="history">История</label><textarea class="form-control" id="history" name="history" rows="8">{{ old('history', $object->history) }}</textarea></div>
                        <div class="col-12"><label class="form-label required" for="address">Адрес</label><input class="form-control" id="address" name="address" value="{{ old('address', $object->address) }}" required></div>
                        <div class="col-md-6"><label class="form-label required" for="latitude">Широта</label><input class="form-control" id="latitude" name="latitude" value="{{ old('latitude', $object->latitude) }}" required></div>
                        <div class="col-md-6"><label class="form-label required" for="longitude">Долгота</label><input class="form-control" id="longitude" name="longitude" value="{{ old('longitude', $object->longitude) }}" required></div>
                        <div class="col-md-4"><label class="form-label" for="phone">Телефон</label><input class="form-control" id="phone" name="phone" value="{{ old('phone', $object->phone) }}"></div>
                        <div class="col-md-4"><label class="form-label" for="email">Email</label><input class="form-control" id="email" type="email" name="email" value="{{ old('email', $object->email) }}"></div>
                        <div class="col-md-4"><label class="form-label" for="website">Сайт</label><input class="form-control" id="website" type="url" name="website" value="{{ old('website', $object->website) }}"></div>
                        <div class="col-12"><label class="form-label" for="schedule_text">Расписание богослужений</label><textarea class="form-control" id="schedule_text" name="schedule_text" rows="7">{{ old('schedule_text', $object->schedule_text) }}</textarea></div>
                        <div class="col-md-6"><label class="form-label" for="parking_info">Парковка</label><textarea class="form-control" id="parking_info" name="parking_info" rows="4">{{ old('parking_info', $object->parking_info) }}</textarea></div>
                        <div class="col-md-6"><label class="form-label" for="accessibility_info">Доступность</label><textarea class="form-control" id="accessibility_info" name="accessibility_info" rows="4">{{ old('accessibility_info', $object->accessibility_info) }}</textarea></div>
                        <div class="col-12"><label class="form-label">Святыни</label><div class="row g-2" style="max-height:300px;overflow:auto">@foreach($sanctities as $sanctity)<div class="col-md-6"><label class="form-check border rounded-3 p-3 h-100"><input class="form-check-input" type="checkbox" name="sanctity_ids[]" value="{{ $sanctity->id }}" @checked(in_array($sanctity->id, old('sanctity_ids', $selectedSanctities)))><span class="form-check-label ms-2">{{ $sanctity->name }}</span></label></div>@endforeach</div></div>
                    </div>

                    <button class="btn btn-pm-gold mt-5 px-4" type="submit"><i class="bi bi-send me-2"></i>Отправить изменения на проверку</button>
                </form>

                <section class="mt-5">
                    <div class="section-kicker mb-2">Фото, видео, аудио и документы</div><h2 class="h2 mb-4">Добавить материалы</h2>
                    <form class="filter-card" method="POST" enctype="multipart/form-data" action="{{ route('service.objects.media.store', $object) }}">@csrf<div class="row g-3"><div class="col-12"><label class="form-label required" for="files">Файлы</label><input class="form-control" id="files" name="files[]" type="file" multiple required><div class="form-text">До 10 файлов за раз, каждый до 50 МБ. Материалы появятся на сайте после модерации.</div></div><div class="col-12"><label class="form-label" for="media_description">Описание</label><textarea class="form-control" id="media_description" name="description" rows="3"></textarea></div><div class="col-12"><button class="btn btn-outline-pm" type="submit"><i class="bi bi-cloud-upload me-2"></i>Загрузить на проверку</button></div></div></form>
                </section>
            </div>

            <aside class="col-xl-4">
                <div class="position-sticky d-grid gap-4" style="top:105px">
                    <div class="info-card"><h2 class="h5 mb-3">Как проходит публикация</h2><ol class="small text-secondary ps-3 mb-0 d-grid gap-2"><li>Вы отправляете изменения.</li><li>Администратор сравнивает новую и текущую версии.</li><li>После одобрения данные сразу появляются на сайте и в API.</li></ol></div>
                    <div class="info-card"><h2 class="h5 mb-3">Последние заявки</h2><div class="d-grid gap-2">@forelse($requests as $item)<div class="border rounded-3 p-3"><div class="d-flex justify-content-between gap-2"><span class="small">{{ $item->created_at->format('d.m.Y H:i') }}</span><span class="badge rounded-pill {{ $item->status === 'approved' ? 'text-bg-success' : ($item->status === 'rejected' ? 'text-bg-danger' : 'text-bg-warning') }}">{{ $item->status }}</span></div>@if($item->review_note)<div class="small text-secondary mt-2">{{ $item->review_note }}</div>@endif</div>@empty<div class="small text-secondary">Заявок пока нет.</div>@endforelse</div></div>
                    <div class="info-card"><h2 class="h5 mb-3">Материалы на модерации</h2><div class="row g-2">@forelse($mediaSubmissions as $media)<div class="col-6"><div class="border rounded-3 p-2 h-100">@if($media->type === 'image' && $media->status !== 'rejected')<img src="{{ $media->url }}" alt="" style="width:100%;aspect-ratio:1;object-fit:cover;border-radius:8px">@else<div class="object-placeholder rounded-3" style="aspect-ratio:1;font-size:1.6rem"><i class="bi bi-file-earmark"></i></div>@endif<div class="small mt-2 text-truncate">{{ $media->title }}</div><span class="badge rounded-pill mt-1 {{ $media->status === 'approved' ? 'text-bg-success' : ($media->status === 'rejected' ? 'text-bg-danger' : 'text-bg-warning') }}">{{ $media->status }}</span></div></div>@empty<div class="col-12 small text-secondary">Материалов пока нет.</div>@endforelse</div></div>
                </div>
            </aside>
        </div>
    </div>
</section>
@endsection
