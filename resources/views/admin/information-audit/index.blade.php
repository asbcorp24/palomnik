@extends('admin.layouts.app')

@section('title', 'Актуальность данных')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <h1 class="page-title">Актуальность и заполненность данных</h1>
        <div class="page-subtitle">Контроль расписаний, контактов, фотографий, описаний, источников и редакционного рейтинга карточек.</div>
    </div>
    <a class="btn btn-outline-green" href="{{ route('admin.objects.index') }}"><i class="bi bi-geo-alt me-1"></i>Все объекты</a>
</div>

<div class="row g-3 mb-4">
    @foreach([
        ['stale', 'bi-clock-history', 'Не проверены или просрочены'],
        ['low_completeness', 'bi-speedometer', 'Заполненность менее 30%'],
        ['missing_schedule', 'bi-calendar-x', 'Без расписания'],
        ['missing_contacts', 'bi-telephone-x', 'Без контактов'],
        ['no_photo', 'bi-image', 'Без фотографий'],
        ['no_description', 'bi-file-text', 'Без описания'],
        ['missing_source', 'bi-link-45deg', 'Без подтверждённого источника'],
        ['pending_update', 'bi-building-check', 'Изменения от храмов'],
    ] as $card)
        <div class="col-6 col-lg-4 col-xl-3">
            <a class="card-soft stat-card d-block text-decoration-none text-reset {{ ($filters['issue'] ?? '') === $card[0] ? 'border-warning' : '' }}" href="{{ route('admin.information-audit.index', ['issue' => $card[0]]) }}">
                <span class="stat-icon"><i class="bi {{ $card[1] }}"></i></span>
                <div class="stat-number">{{ $stats[$card[0]] }}</div>
                <div class="stat-label">{{ $card[2] }}</div>
            </a>
        </div>
    @endforeach
</div>

<div class="card-soft p-3 mb-4">
    <form class="row g-3 align-items-end" method="GET" action="{{ route('admin.information-audit.index') }}">
        <div class="col-lg-4">
            <label class="form-label" for="q">Поиск</label>
            <input class="form-control" id="q" type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Название или адрес">
        </div>
        <div class="col-md-5 col-lg-4">
            <label class="form-label" for="issue">Проблема</label>
            <select class="form-select" id="issue" name="issue">
                <option value="">Все объекты</option>
                <option value="stale" @selected(($filters['issue'] ?? '') === 'stale')>Не проверены более 90 дней</option>
                <option value="low_completeness" @selected(($filters['issue'] ?? '') === 'low_completeness')>Заполненность менее 30%</option>
                <option value="missing_schedule" @selected(($filters['issue'] ?? '') === 'missing_schedule')>Нет расписания</option>
                <option value="missing_contacts" @selected(($filters['issue'] ?? '') === 'missing_contacts')>Нет контактов</option>
                <option value="no_photo" @selected(($filters['issue'] ?? '') === 'no_photo')>Нет фотографии</option>
                <option value="no_description" @selected(($filters['issue'] ?? '') === 'no_description')>Нет описания</option>
                <option value="missing_source" @selected(($filters['issue'] ?? '') === 'missing_source')>Нет подтверждённого источника</option>
                <option value="pending_update" @selected(($filters['issue'] ?? '') === 'pending_update')>Представитель прислал изменения</option>
            </select>
        </div>
        <div class="col-md-4 col-lg-2">
            <label class="form-label" for="status">Статус проверки</label>
            <select class="form-select" id="status" name="status">
                <option value="">Любой</option>
                @foreach($statusLabels as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3 col-lg-2 d-grid">
            <button class="btn btn-gold" type="submit"><i class="bi bi-funnel me-1"></i>Показать</button>
        </div>
        @if(array_filter($filters))
            <div class="col-12"><a class="small text-decoration-none" href="{{ route('admin.information-audit.index') }}"><i class="bi bi-x-circle me-1"></i>Сбросить фильтры</a></div>
        @endif
    </form>
</div>

<div class="card-soft p-0 overflow-hidden">
    @if($objects->isEmpty())
        <div class="p-5 text-center text-secondary">
            <i class="bi bi-check-circle display-5 d-block mb-3 text-success"></i>
            По выбранному фильтру проблем не найдено.
        </div>
    @else
        <div class="table-responsive">
            <table class="table mb-0 align-middle">
                <thead>
                <tr>
                    <th>Объект</th>
                    <th>Заполненность</th>
                    <th>Проверка</th>
                    <th>Недочёты</th>
                    <th class="text-end">Действия</th>
                </tr>
                </thead>
                <tbody>
                @foreach($objects as $object)
                    @php
                        $score = (int) $object->editorial_completeness_score;
                        $scoreClass = $score < 30 ? 'bg-danger' : ($score < 70 ? 'bg-warning' : 'bg-success');
                        $scoreTextClass = $score < 30 ? 'text-danger' : ($score < 70 ? 'text-warning' : 'text-success');
                        $isStale = !$object->information_verified_at
                            || $object->information_verified_at->lte(now()->subDays(90))
                            || ($object->next_verification_at && $object->next_verification_at->isPast())
                            || in_array($object->verification_status, ['unverified', 'needs_review', 'outdated'], true);
                        $missingContacts = blank($object->phone) && blank($object->email) && blank($object->website);
                        $missingDescription = blank($object->short_description) && blank($object->description);
                        $sourceConfirmed = filled($object->information_source_url) && $object->verification_status === 'verified';
                        $statusClass = match($object->verification_status) {
                            'verified' => 'text-bg-success',
                            'pending_update' => 'text-bg-primary',
                            'outdated' => 'text-bg-danger',
                            'needs_review' => 'text-bg-warning',
                            default => 'text-bg-secondary',
                        };
                    @endphp
                    <tr>
                        <td style="min-width:280px">
                            <div class="d-flex gap-3 align-items-center">
                                @if($object->coverMedia?->url)
                                    <img class="object-thumb" src="{{ $object->coverMedia->url }}" alt="">
                                @else
                                    <span class="object-thumb d-flex align-items-center justify-content-center"><i class="bi bi-image text-secondary"></i></span>
                                @endif
                                <div>
                                    <a class="fw-semibold text-decoration-none" href="{{ route('admin.objects.edit', $object) }}">{{ $object->name }}</a>
                                    <div class="small text-secondary mt-1">{{ $object->objectType?->name ?: 'Без типа' }} · {{ \Illuminate\Support\Str::limit($object->address, 75) }}</div>
                                </div>
                            </div>
                        </td>
                        <td style="min-width:245px">
                            <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
                                <strong class="{{ $scoreTextClass }}">{{ $score }}%</strong>
                                <span class="small text-secondary">из 100%</span>
                            </div>
                            <div class="progress" style="height:8px" role="progressbar" aria-label="Заполненность карточки" aria-valuenow="{{ $score }}" aria-valuemin="0" aria-valuemax="100">
                                <div class="progress-bar {{ $scoreClass }}" style="width:{{ $score }}%"></div>
                            </div>
                            <details class="small mt-2">
                                <summary class="text-secondary" style="cursor:pointer">Расчёт рейтинга</summary>
                                <div class="mt-2 d-grid gap-1">
                                    @foreach($object->editorial_completeness_breakdown as $criterion)
                                        <div class="d-flex justify-content-between gap-3 {{ $criterion['filled'] ? 'text-success' : 'text-secondary' }}">
                                            <span><i class="bi {{ $criterion['filled'] ? 'bi-check-circle-fill' : 'bi-circle' }} me-1"></i>{{ $criterion['label'] }}</span>
                                            <span>{{ rtrim(rtrim(number_format((float)$criterion['weight'], 1, ',', ' '), '0'), ',') }}%</span>
                                        </div>
                                    @endforeach
                                </div>
                            </details>
                        </td>
                        <td style="min-width:220px">
                            <span class="badge {{ $statusClass }}">{{ $statusLabels[$object->verification_status] ?? $object->verification_status }}</span>
                            <div class="small mt-2">
                                @if($object->information_verified_at)
                                    Проверено: <strong>{{ $object->information_verified_at->format('d.m.Y') }}</strong>
                                @else
                                    <span class="text-danger">Ещё не проверялось</span>
                                @endif
                            </div>
                            @if($object->next_verification_at)
                                <div class="small text-secondary">Следующая: {{ $object->next_verification_at->format('d.m.Y') }}</div>
                            @endif
                            @if($object->verifier)
                                <div class="small text-secondary">Ответственный: {{ $object->verifier->name }}</div>
                            @endif
                        </td>
                        <td style="min-width:330px">
                            <div class="d-flex flex-wrap gap-1">
                                @if($score < 30)<span class="badge text-bg-danger">Заполнено менее 30%</span>@endif
                                @if($isStale)<span class="badge text-bg-danger">Просрочено</span>@endif
                                @if(blank($object->schedule_text))<span class="badge text-bg-warning">Нет расписания</span>@endif
                                @if($missingContacts)<span class="badge text-bg-warning">Нет контактов</span>@endif
                                @if((int)$object->image_media_count === 0)<span class="badge text-bg-warning">Нет фото</span>@endif
                                @if($missingDescription)<span class="badge text-bg-warning">Нет описания</span>@endif
                                @if(!$sourceConfirmed)<span class="badge text-bg-warning">Нет подтверждённого источника</span>@endif
                                @if((int)$object->pending_update_requests_count > 0)<span class="badge text-bg-primary">Изменений: {{ $object->pending_update_requests_count }}</span>@endif
                                @if($score >= 70 && !$isStale && $sourceConfirmed && !(int)$object->pending_update_requests_count)
                                    <span class="badge text-bg-success">Карточка готова</span>
                                @endif
                            </div>
                            @if(!empty($object->editorial_completeness_missing))
                                <div class="small text-secondary mt-2">Не заполнено: {{ implode(', ', $object->editorial_completeness_missing) }}.</div>
                            @endif
                            @if($object->information_source_url)
                                <a class="small d-inline-block mt-2" href="{{ $object->information_source_url }}" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right me-1"></i>Источник информации</a>
                            @endif
                        </td>
                        <td class="text-end text-nowrap">
                            @if((int)$object->pending_update_requests_count > 0)
                                <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.service-review.index', ['type' => 'updates', 'status' => 'pending']) }}" title="Рассмотреть изменения"><i class="bi bi-building-check"></i></a>
                            @endif
                            <a class="btn btn-sm btn-light" href="{{ route('admin.objects.edit', $object) }}" title="Редактировать карточку"><i class="bi bi-pencil"></i></a>
                            <button class="btn btn-sm btn-outline-success" type="button" data-bs-toggle="collapse" data-bs-target="#verification-{{ $object->id }}" aria-expanded="false" title="Настроить и подтвердить"><i class="bi bi-patch-check"></i></button>
                        </td>
                    </tr>
                    <tr class="collapse bg-light" id="verification-{{ $object->id }}">
                        <td colspan="5">
                            <form class="row g-3 align-items-end p-2" method="POST" action="{{ route('admin.information-audit.verify', $object) }}">
                                @csrf
                                @method('PUT')
                                <div class="col-lg-7">
                                    <label class="form-label required" for="source-{{ $object->id }}">Источник информации</label>
                                    <input class="form-control" id="source-{{ $object->id }}" type="url" name="information_source_url" value="{{ $object->information_source_url }}" placeholder="https://официальный-сайт.ru/страница" required>
                                    <div class="form-text">Официальный сайт храма, епархии или другой проверенный источник.</div>
                                </div>
                                <div class="col-md-6 col-lg-3">
                                    <label class="form-label" for="next-{{ $object->id }}">Следующая проверка</label>
                                    <input class="form-control" id="next-{{ $object->id }}" type="date" name="next_verification_at" value="{{ optional($object->next_verification_at)->format('Y-m-d') ?: now()->addDays(90)->format('Y-m-d') }}" min="{{ now()->format('Y-m-d') }}">
                                </div>
                                <div class="col-md-6 col-lg-2 d-grid">
                                    <button class="btn btn-success" type="submit"><i class="bi bi-check2-circle me-1"></i>Подтвердить</button>
                                </div>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @if($objects->hasPages())<div class="p-3 border-top">{{ $objects->links() }}</div>@endif
    @endif
</div>
@endsection
