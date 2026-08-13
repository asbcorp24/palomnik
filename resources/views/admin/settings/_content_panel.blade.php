@php
    $defaults = array_replace(\App\Models\SiteContentSetting::DEFAULTS, [
        'home_eyebrow' => 'Паломничество по Москве и Подмосковью',
        'home_categories_title' => 'Что вы ищете сегодня?',
        'home_categories_lead' => 'Фильтры помогают быстро перейти к нужному типу паломнического объекта.',
        'home_steps_title' => 'От интереса — к паломничеству',
        'home_steps_lead' => 'Выберите место или событие, забронируйте поездку и сохраните QR-билет в телефоне.',
        'routes_builder_title' => 'Составьте маршрут дня',
        'routes_builder_lead' => 'Укажите местоположение, свободное время, способ передвижения и интересующую тему. Система подберёт храмы и монастыри, проверит расписания и рассчитает путь.',
        'map_title' => 'Храмы и монастыри',
        'map_lead' => 'Карта загружает только объекты в видимой области. Приближайте карту, чтобы увидеть отдельные храмы и точки интереса.',
        'footer_offline_title' => 'Работа без сети',
        'footer_copyright_name' => 'Московский паломник',
    ]);
    $content = array_replace($defaults, \App\Models\SiteContentSetting::values());
    $contentGroups = [
        'Главная страница' => [
            ['home_eyebrow', 'Надпись над главным заголовком', false],
            ['home_title', 'Главный заголовок', false],
            ['home_lead', 'Подпись под главным заголовком', true],
            ['home_events_title', 'Заголовок блока событий', false],
            ['home_events_lead', 'Подпись блока событий', true],
            ['home_objects_title', 'Заголовок блока храмов', false],
            ['home_objects_lead', 'Подпись блока храмов', true],
            ['home_categories_title', 'Заголовок выбора направления', false],
            ['home_categories_lead', 'Подпись выбора направления', true],
            ['home_steps_title', 'Заголовок блока «Простой путь»', false],
            ['home_steps_lead', 'Подпись блока «Простой путь»', true],
        ],
        'Каталог храмов и монастырей' => [
            ['objects_title', 'Заголовок страницы', false],
            ['objects_lead', 'Описание страницы', true],
        ],
        'Маршруты' => [
            ['routes_title', 'Заголовок страницы', false],
            ['routes_lead', 'Описание страницы', true],
            ['routes_builder_title', 'Заголовок конструктора маршрута дня', false],
            ['routes_builder_lead', 'Описание конструктора маршрута дня', true],
        ],
        'Календарь' => [
            ['calendar_title', 'Заголовок страницы', false],
            ['calendar_lead', 'Описание страницы', true],
        ],
        'Сообщество' => [
            ['community_title', 'Заголовок страницы', false],
            ['community_lead', 'Описание страницы', true],
        ],
        'Карта' => [
            ['map_title', 'Заголовок панели карты', false],
            ['map_lead', 'Описание карты', true],
        ],
        'Нижняя часть сайта' => [
            ['footer_tagline', 'Подпись рядом с логотипом', false],
            ['footer_description', 'Описание проекта в подвале', true],
            ['footer_offline_title', 'Заголовок блока работы без сети', false],
            ['footer_text', 'Текст блока работы без сети', true],
            ['footer_copyright_name', 'Название в строке ©', false],
        ],
    ];
@endphp

<div class="card-soft p-4 mb-4" id="site-content-settings">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h2 class="h4 mb-1">Внешний вид и тексты сайта</h2>
            <p class="text-secondary mb-0">Бренд, логотип и основные статичные тексты публичных страниц. Изменения применяются сразу после сохранения.</p>
        </div>
        <span class="badge rounded-pill text-bg-light border px-3 py-2"><i class="bi bi-type me-1"></i>Контент сайта</span>
    </div>

    <form method="POST" action="{{ route('admin.settings.content.update') }}">
        @csrf
        @method('PUT')

        <div class="border rounded-4 p-3 p-lg-4 mb-4">
            <h3 class="h5 mb-3">Логотип и шапка</h3>
            <div class="row g-3">
                <div class="col-lg-5">
                    <label class="form-label" for="siteLogoPath">Логотип сайта</label>
                    <input class="form-control" id="siteLogoPath" name="logo_path" value="{{ old('logo_path', $content['logo_path'] ?? '') }}" maxlength="1500" placeholder="/storage/site/logo.png или https://...">
                    <div class="form-text">Укажите адрес изображения. Поддерживается полный URL или путь сайта. Пустое поле вернёт стандартный знак с крестом.</div>
                    @if(!empty($content['logo_path']))
                        @php
                            $logoPreview = preg_match('~^https?://~i', $content['logo_path']) ? $content['logo_path'] : asset(ltrim($content['logo_path'], '/'));
                        @endphp
                        <div class="mt-3 p-3 border rounded-3 bg-light d-inline-block">
                            <img src="{{ $logoPreview }}" alt="Текущий логотип" style="max-width:180px;max-height:90px;object-fit:contain">
                        </div>
                    @endif
                </div>
                <div class="col-lg-7">
                    <div class="mb-3">
                        <label class="form-label" for="brandName">Название проекта</label>
                        <input class="form-control" id="brandName" name="brand_name" value="{{ old('brand_name', $content['brand_name']) }}" maxlength="1500">
                    </div>
                    <div>
                        <label class="form-label" for="headerTagline">Подпись под названием в шапке</label>
                        <input class="form-control" id="headerTagline" name="header_tagline" value="{{ old('header_tagline', $content['header_tagline']) }}" maxlength="1500">
                    </div>
                </div>
            </div>
        </div>

        <div class="accordion mb-4" id="siteTextSettingsAccordion">
            @foreach($contentGroups as $groupTitle => $fields)
                <div class="accordion-item border rounded-4 mb-3 overflow-hidden">
                    <h3 class="accordion-header">
                        <button class="accordion-button {{ $loop->first ? '' : 'collapsed' }} fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#siteTextGroup{{ $loop->index }}" aria-expanded="{{ $loop->first ? 'true' : 'false' }}">
                            {{ $groupTitle }}
                        </button>
                    </h3>
                    <div id="siteTextGroup{{ $loop->index }}" class="accordion-collapse collapse {{ $loop->first ? 'show' : '' }}" data-bs-parent="#siteTextSettingsAccordion">
                        <div class="accordion-body">
                            <div class="row g-3">
                                @foreach($fields as [$key, $label, $multiline])
                                    <div class="{{ $multiline ? 'col-12' : 'col-lg-6' }}">
                                        <label class="form-label" for="siteContent_{{ $key }}">{{ $label }}</label>
                                        @if($multiline)
                                            <textarea class="form-control" id="siteContent_{{ $key }}" name="{{ $key }}" rows="3" maxlength="1500">{{ old($key, $content[$key] ?? '') }}</textarea>
                                        @else
                                            <input class="form-control" id="siteContent_{{ $key }}" name="{{ $key }}" value="{{ old($key, $content[$key] ?? '') }}" maxlength="1500">
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="d-flex flex-wrap align-items-center gap-3">
            <button class="btn btn-gold px-4" type="submit"><i class="bi bi-save me-2"></i>Сохранить внешний вид и тексты</button>
            <span class="small text-secondary">HTML в текстах не выполняется — значения выводятся безопасно как обычный текст.</span>
        </div>
    </form>
</div>
