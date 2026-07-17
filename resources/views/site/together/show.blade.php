@extends('site.layouts.app')

@section('title', $item->title.' — Паломничество вместе')
@section('meta_description', \Illuminate\Support\Str::limit($item->description, 155))

@section('content')
<section class="page-hero">
    <div class="container">
        <nav aria-label="breadcrumb"><ol class="breadcrumb small mb-3"><li class="breadcrumb-item"><a href="{{ route('home') }}">Главная</a></li><li class="breadcrumb-item"><a href="{{ route('together.index') }}">Паломничество вместе</a></li><li class="breadcrumb-item active">{{ $item->title }}</li></ol></nav>
        <div class="row align-items-end g-4">
            <div class="col-lg-8">
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <span class="badge rounded-pill object-type-badge">{{ $transportModes[$item->transport_mode] ?? $item->transport_mode }}</span>
                    <span class="badge rounded-pill text-bg-light">{{ $joinModes[$item->join_mode] ?? $item->join_mode }}</span>
                    @if($item->status !== 'published')<span class="badge rounded-pill text-bg-warning">{{ ['pending' => 'На модерации', 'rejected' => 'Отклонено', 'cancelled' => 'Отменено', 'completed' => 'Завершено'][$item->status] ?? $item->status }}</span>@endif
                </div>
                <h1 class="section-title mb-3">{{ $item->title }}</h1>
                <p class="section-lead mb-0"><i class="bi bi-person-circle me-2"></i>Организатор: {{ optional($item->organizer)->name }}</p>
            </div>
            <div class="col-lg-4 text-lg-end">
                @if($canManage)
                    <div class="d-flex flex-wrap justify-content-lg-end gap-2">
                        <a class="btn btn-outline-pm" href="{{ route('together.edit', $item) }}"><i class="bi bi-pencil me-2"></i>Редактировать</a>
                        <form method="POST" action="{{ route('together.destroy', $item) }}" onsubmit="return confirm('Удалить предложение и всё обсуждение?')">@csrf @method('DELETE')<button class="btn btn-outline-danger" type="submit"><i class="bi bi-trash me-2"></i>Удалить</button></form>
                    </div>
                @endif
            </div>
        </div>
    </div>
</section>

<section class="section-space pt-5">
    <div class="container">
        @if($item->status === 'pending')
            <div class="alert alert-warning mb-4"><i class="bi bi-hourglass-split me-2"></i>Предложение ожидает проверки администратора и пока не видно в общем каталоге.</div>
        @elseif($item->status === 'rejected')
            <div class="alert alert-danger mb-4"><strong>Предложение отклонено.</strong>@if($item->moderation_note) {{ $item->moderation_note }}@endif</div>
        @endif

        <div class="row g-5">
            <div class="col-lg-8">
                <section class="mb-5">
                    <div class="section-kicker mb-2">План поездки</div>
                    <h2 class="h2 mb-4">О совместном паломничестве</h2>
                    <div class="text-secondary lh-lg">{!! nl2br(e($item->description)) !!}</div>
                </section>

                @if($item->pilgrimageRoute)
                    <section class="mb-5">
                        <div class="section-kicker mb-2">Готовый маршрут</div>
                        <div class="filter-card">
                            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
                                <div><h2 class="h4 mb-2">{{ $item->pilgrimageRoute->title }}</h2><div class="small text-secondary">Точек маршрута: {{ $item->pilgrimageRoute->objects->count() }}</div></div>
                                <a class="btn btn-sm btn-outline-pm" href="{{ route('routes.show', $item->pilgrimageRoute) }}">Открыть маршрут</a>
                            </div>
                            <div class="d-grid gap-2">
                                @foreach($item->pilgrimageRoute->objects as $index => $object)
                                    <a class="map-object-row text-decoration-none" href="{{ route('objects.show', $object) }}"><span class="badge rounded-pill object-type-badge me-2">{{ $index + 1 }}</span>{{ $object->name }}</a>
                                @endforeach
                            </div>
                        </div>
                    </section>
                @endif

                @if($canManage)
                    <section class="mb-5">
                        <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
                            <div><div class="section-kicker mb-2">Организатору</div><h2 class="h2 mb-0">Заявки участников</h2></div>
                            <span class="text-secondary small">Подтверждено: {{ $item->approvedParticipantsCount() }}@if($item->max_participants) из {{ $item->max_participants }}@endif</span>
                        </div>
                        <div class="d-grid gap-3">
                            @forelse($item->members->where('status', 'pending') as $member)
                                <article class="info-card">
                                    <div class="row align-items-center g-3">
                                        <div class="col-md">
                                            <div class="fw-semibold mb-1">{{ $member->user->name }}</div>
                                            <div class="small text-secondary mb-2">{{ $member->user->email }}@if($member->user->phone) · {{ $member->user->phone }}@endif</div>
                                            @if($member->message)<div class="small">{{ $member->message }}</div>@endif
                                        </div>
                                        <div class="col-md-auto">
                                            <div class="d-flex gap-2">
                                                <form method="POST" action="{{ route('together.members.update', [$item, $member]) }}">@csrf @method('PUT')<input type="hidden" name="status" value="approved"><button class="btn btn-sm btn-pm-green" type="submit">Принять</button></form>
                                                <form method="POST" action="{{ route('together.members.update', [$item, $member]) }}">@csrf @method('PUT')<input type="hidden" name="status" value="rejected"><button class="btn btn-sm btn-outline-danger" type="submit">Отклонить</button></form>
                                            </div>
                                        </div>
                                    </div>
                                </article>
                            @empty
                                <div class="filter-card text-secondary">Новых заявок сейчас нет.</div>
                            @endforelse
                        </div>
                    </section>
                @endif

                <section id="discussion">
                    <div class="section-kicker mb-2">Договориться о деталях</div>
                    <h2 class="h2 mb-4">Обсуждение группы</h2>
                    @if($canDiscuss)
                        <div class="filter-card mb-4" style="max-height:560px;overflow:auto">
                            <div class="d-grid gap-3">
                                @forelse($messages as $message)
                                    <div class="{{ $message->is_system ? 'small text-secondary text-center py-2' : 'info-card' }}">
                                        @if($message->is_system)
                                            <i class="bi bi-info-circle me-1"></i>{{ $message->body }}
                                        @else
                                            <div class="d-flex justify-content-between gap-3 mb-2"><strong>{{ optional($message->user)->name }}</strong><span class="small text-secondary">{{ $message->created_at->format('d.m.Y H:i') }}</span></div>
                                            <div class="lh-lg">{!! nl2br(e($message->body)) !!}</div>
                                        @endif
                                    </div>
                                @empty
                                    <div class="text-secondary text-center py-4">Обсуждение ещё не началось. Напишите первое сообщение.</div>
                                @endforelse
                            </div>
                        </div>
                        <form class="filter-card" method="POST" action="{{ route('together.messages.store', $item) }}">
                            @csrf
                            <label class="form-label" for="body">Сообщение участникам</label>
                            <textarea class="form-control" id="body" name="body" rows="4" maxlength="3000" required placeholder="Обсудите время сбора, билеты, питание, одежду и другие детали"></textarea>
                            <button class="btn btn-pm-gold mt-3" type="submit"><i class="bi bi-send me-2"></i>Отправить</button>
                        </form>
                    @elseif(auth()->check())
                        <div class="filter-card text-center py-5"><i class="bi bi-chat-dots display-5 text-secondary"></i><h3 class="h5 mt-3">Обсуждение доступно участникам</h3><p class="text-secondary mb-0">После подтверждения заявки вы сможете писать сообщения и видеть контакт организатора.</p></div>
                    @else
                        <div class="filter-card text-center py-5"><h3 class="h5 mb-3">Войдите, чтобы присоединиться к обсуждению</h3><a class="btn btn-pm-gold" href="{{ route('login') }}">Войти</a></div>
                    @endif
                </section>
            </div>

            <aside class="col-lg-4">
                <div class="position-sticky d-grid gap-3" style="top:105px">
                    <div class="info-card">
                        <h2 class="h5 mb-4">Основная информация</h2>
                        <div class="d-grid gap-3 small">
                            <div><div class="text-secondary mb-1">Дата и время</div><strong>{{ $item->starts_at->format('d.m.Y H:i') }}</strong>@if($item->ends_at)<div class="text-secondary mt-1">до {{ $item->ends_at->format('d.m.Y H:i') }}</div>@endif</div>
                            <div><div class="text-secondary mb-1">Место встречи</div><strong>{{ $item->meeting_place }}</strong></div>
                            <div><div class="text-secondary mb-1">Транспорт</div><strong>{{ $transportModes[$item->transport_mode] ?? $item->transport_mode }}</strong></div>
                            <div><div class="text-secondary mb-1">Участники</div><strong>{{ $item->approvedParticipantsCount() }}@if($item->max_participants) из {{ $item->max_participants }}@else, без ограничения@endif</strong></div>
                            @if($item->availablePlaces() !== null)<div><div class="text-secondary mb-1">Свободно мест</div><strong>{{ $item->availablePlaces() }}</strong></div>@endif
                        </div>
                    </div>

                    @auth
                        @if($item->organizer_id !== auth()->id() && $item->status === 'published')
                            @if(!$membership || in_array($membership->status, ['left', 'rejected']))
                                @if(!$item->isFull())
                                    <form class="info-card" method="POST" action="{{ route('together.join', $item) }}">
                                        @csrf
                                        <h2 class="h5 mb-3">Присоединиться</h2>
                                        <label class="form-label small" for="message">Сообщение организатору</label>
                                        <textarea class="form-control" id="message" name="message" rows="3" maxlength="1500" placeholder="Коротко представьтесь или задайте вопрос"></textarea>
                                        <button class="btn btn-pm-gold w-100 mt-3" type="submit">{{ $item->join_mode === 'auto' ? 'Присоединиться сразу' : 'Отправить заявку' }}</button>
                                    </form>
                                @else
                                    <div class="alert alert-secondary mb-0">Группа уже набрана.</div>
                                @endif
                            @elseif($membership->status === 'pending')
                                <div class="info-card text-center"><i class="bi bi-hourglass-split fs-2 text-warning"></i><h2 class="h6 mt-3">Заявка ожидает решения</h2><p class="small text-secondary mb-3">Организатор увидит её в этом разделе.</p><form method="POST" action="{{ route('together.leave', $item) }}">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger" type="submit">Отозвать заявку</button></form></div>
                            @elseif($membership->status === 'approved')
                                <div class="info-card"><div class="text-success fw-semibold mb-3"><i class="bi bi-check-circle me-2"></i>Вы участвуете</div>@if($item->contact_method && $item->contact_value)<div class="small text-secondary mb-1">Контакт организатора</div><div class="fw-semibold mb-3">{{ $contactMethods[$item->contact_method] ?? $item->contact_method }}: {{ $item->contact_value }}</div>@endif<a class="btn btn-outline-pm w-100 mb-2" href="#discussion">Перейти к обсуждению</a><form method="POST" action="{{ route('together.leave', $item) }}">@csrf @method('DELETE')<button class="btn btn-sm btn-link text-danger w-100" type="submit">Отказаться от участия</button></form></div>
                            @endif
                        @elseif($item->organizer_id === auth()->id())
                            <div class="info-card"><div class="text-success fw-semibold"><i class="bi bi-person-badge me-2"></i>Вы организатор</div><p class="small text-secondary mt-2 mb-0">Принимайте заявки и согласовывайте детали в обсуждении.</p></div>
                        @endif
                    @else
                        <div class="info-card text-center"><h2 class="h5 mb-3">Хотите поехать вместе?</h2><p class="small text-secondary mb-3">Зарегистрируйтесь, чтобы подать заявку и участвовать в обсуждении.</p><a class="btn btn-pm-gold w-100" href="{{ route('register') }}">Создать аккаунт</a></div>
                    @endauth

                    <a class="btn btn-outline-pm py-3" href="{{ route('together.index') }}"><i class="bi bi-arrow-left me-2"></i>Все предложения</a>
                </div>
            </aside>
        </div>
    </div>
</section>
@endsection
