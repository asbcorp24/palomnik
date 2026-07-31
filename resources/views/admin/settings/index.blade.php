@extends('admin.layouts.app')

@section('title', 'Настройки сайта и SEO')

@push('styles')
<style>
    .settings-tabs .nav-link { color:var(--pilgrim-green);border-radius:12px;font-weight:600; }
    .settings-tabs .nav-link.active { color:#fff;background:var(--pilgrim-green); }
    .theme-card { border:1px solid var(--pilgrim-border);border-radius:18px;background:var(--pilgrim-paper);overflow:hidden; }
    .theme-card.active-theme { border-color:var(--pilgrim-gold);box-shadow:0 0 0 3px rgba(176,138,62,.12); }
    .theme-swatches { display:grid;grid-template-columns:repeat(10,1fr);height:38px; }
    .theme-swatches span { min-width:0; }
    .color-field { display:grid;grid-template-columns:52px 1fr;gap:10px;align-items:center; }
    .color-field input[type=color] { width:52px;height:44px;padding:3px;border:1px solid var(--pilgrim-border);border-radius:10px;background:#fff; }
    .preview-shell { border:1px solid var(--pilgrim-border);border-radius:20px;overflow:hidden;background:var(--preview-cream);color:var(--preview-ink); }
    .preview-header { padding:16px;background:var(--preview-paper);border-bottom:1px solid var(--preview-border); }
    .preview-brand { color:var(--preview-green);font-weight:700; }
    .preview-body { padding:22px; }
    .preview-card { padding:18px;border:1px solid var(--preview-border);border-radius:16px;background:var(--preview-paper); }
    .preview-primary { border:0;border-radius:10px;padding:9px 16px;background:var(--preview-gold);color:#fff; }
    .preview-secondary { border:0;border-radius:10px;padding:9px 16px;background:var(--preview-green);color:#fff; }
    .seo-help { color:var(--pilgrim-muted);font-size:.82rem; }
    .field-counter { color:var(--pilgrim-muted);font-size:.75rem;float:right; }
</style>
@endpush

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <h1 class="page-title">Настройки сайта и SEO</h1>
        <p class="page-subtitle mb-0">Цветовые схемы, метаданные, индексация, социальные сети и структурированные данные.</p>
    </div>
    <a class="btn btn-outline-green" href="{{ route('home') }}" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right me-2"></i>Открыть сайт</a>
</div>

<ul class="nav nav-pills settings-tabs gap-2 mb-4" role="tablist">
    <li class="nav-item"><button class="nav-link active" data-bs-toggle="pill" data-bs-target="#appearanceTab" type="button"><i class="bi bi-palette me-2"></i>Цветовая гамма</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#seoTab" type="button"><i class="bi bi-search me-2"></i>SEO</button></li>
</ul>

<div class="tab-content">
    <div class="tab-pane fade show active" id="appearanceTab">
        <div class="row g-4">
            <div class="col-xl-7">
                <div class="card-soft p-4 mb-4">
                    <div class="d-flex justify-content-between align-items-start gap-3 mb-4">
                        <div><h2 class="h4 mb-1">Сохранённые схемы</h2><div class="text-secondary small">Активная схема применяется ко всем публичным страницам сайта.</div></div>
                        <span class="badge rounded-pill text-bg-light">{{ $schemes->count() }} схем</span>
                    </div>
                    <div class="d-grid gap-3">
                        @foreach($schemes as $scheme)
                            <article class="theme-card {{ $scheme->is_active ? 'active-theme' : '' }}">
                                <div class="theme-swatches">
                                    @foreach($colorKeys as $key)<span style="background:{{ $scheme->colors[$key] ?? '#ffffff' }}" title="{{ $key }}"></span>@endforeach
                                </div>
                                <div class="p-3">
                                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                                        <div>
                                            <div class="fw-semibold">{{ $scheme->name }}</div>
                                            <div class="small text-secondary">{{ $scheme->slug }}</div>
                                        </div>
                                        <div class="d-flex flex-wrap gap-2">
                                            @if($scheme->is_active)
                                                <span class="badge badge-published align-self-center"><i class="bi bi-check-circle me-1"></i>Активна</span>
                                            @else
                                                <form method="POST" action="{{ route('admin.settings.themes.activate', $scheme) }}">@csrf @method('PUT')<button class="btn btn-sm btn-gold" type="submit">Активировать</button></form>
                                                <form method="POST" action="{{ route('admin.settings.themes.destroy', $scheme) }}" onsubmit="return confirm('Удалить цветовую схему?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash"></i></button></form>
                                            @endif
                                            <button class="btn btn-sm btn-light" type="button" data-bs-toggle="collapse" data-bs-target="#themeEdit{{ $scheme->id }}"><i class="bi bi-pencil"></i></button>
                                        </div>
                                    </div>
                                    <div class="collapse mt-3" id="themeEdit{{ $scheme->id }}">
                                        <form method="POST" action="{{ route('admin.settings.themes.update', $scheme) }}" class="theme-form border-top pt-3">@csrf @method('PUT')
                                            <div class="mb-3"><label class="form-label required">Название</label><input class="form-control" name="name" value="{{ $scheme->name }}" required maxlength="120"></div>
                                            <div class="row g-3">
                                                @foreach($colorKeys as $key)
                                                    <div class="col-md-6"><label class="form-label">{{ $key }}</label><div class="color-field"><input type="color" value="{{ $scheme->colors[$key] ?? '#ffffff' }}" data-color-picker><input class="form-control" name="colors[{{ $key }}]" value="{{ $scheme->colors[$key] ?? '#ffffff' }}" pattern="#[0-9A-Fa-f]{6}" required data-color-text></div></div>
                                                @endforeach
                                            </div>
                                            <button class="btn btn-gold mt-3" type="submit"><i class="bi bi-save me-2"></i>Сохранить изменения</button>
                                        </form>
                                    </div>
                                </div>
                            </article>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="col-xl-5">
                <div class="card-soft p-4 position-sticky" style="top:98px">
                    <h2 class="h4 mb-1">Новая цветовая схема</h2>
                    <p class="text-secondary small mb-4">Создайте вариант, сохраните его в базе и при необходимости сразу активируйте.</p>
                    <form method="POST" action="{{ route('admin.settings.themes.store') }}" class="theme-form" id="newThemeForm">@csrf
                        <div class="mb-3"><label class="form-label required">Название схемы</label><input class="form-control" name="name" value="{{ old('name') }}" placeholder="Например: Пасхальная" required maxlength="120"></div>
                        @php($defaults = $schemes->firstWhere('is_active', true)?->colors ?? $schemes->first()?->colors ?? [])
                        <div class="row g-3">
                            @foreach([
                                'cream'=>'Мягкий фон','paper'=>'Карточки и поверхности','gold'=>'Основной акцент','gold_dark'=>'Тёмный акцент','green'=>'Главный цвет','green_soft'=>'Светлый главный','brown'=>'Дополнительный','ink'=>'Основной текст','muted'=>'Вторичный текст','border'=>'Границы'
                            ] as $key=>$label)
                                <div class="col-12"><label class="form-label mb-1">{{ $label }} <span class="text-secondary small">({{ $key }})</span></label><div class="color-field"><input type="color" value="{{ old('colors.'.$key, $defaults[$key] ?? '#ffffff') }}" data-color-picker><input class="form-control" name="colors[{{ $key }}]" value="{{ old('colors.'.$key, $defaults[$key] ?? '#ffffff') }}" pattern="#[0-9A-Fa-f]{6}" required data-color-text data-preview-color="{{ $key }}"></div></div>
                            @endforeach
                        </div>
                        <div class="form-check form-switch mt-3"><input class="form-check-input" type="checkbox" name="activate_after_save" value="1" id="activateAfterSave"><label class="form-check-label" for="activateAfterSave">Сразу активировать после сохранения</label></div>
                        <button class="btn btn-gold w-100 mt-3" type="submit"><i class="bi bi-plus-circle me-2"></i>Сохранить схему</button>
                    </form>

                    <div class="preview-shell mt-4" id="themePreview">
                        <div class="preview-header"><span class="preview-brand">Московский паломник</span></div>
                        <div class="preview-body"><div class="preview-card"><div class="fw-semibold mb-2">Предпросмотр карточки</div><p class="small mb-3" style="color:var(--preview-muted)">Так будут выглядеть фон, текст, границы и основные кнопки.</p><div class="d-flex gap-2"><button class="preview-primary" type="button">Акцент</button><button class="preview-secondary" type="button">Основная</button></div></div></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="tab-pane fade" id="seoTab">
        <form method="POST" action="{{ route('admin.settings.seo.update') }}">@csrf @method('PUT')
            <div class="row g-4">
                <div class="col-xl-8">
                    <div class="card-soft p-4 mb-4">
                        <h2 class="h4 mb-1">Основные метаданные</h2><p class="text-secondary small mb-4">Используются как значения по умолчанию. У страниц со своими заголовками и описаниями сохраняются индивидуальные данные.</p>
                        <div class="mb-3"><label class="form-label required">Название сайта</label><input class="form-control" name="site_name" value="{{ old('site_name',$seo['site_name']) }}" required maxlength="120"></div>
                        <div class="mb-3"><label class="form-label required">Заголовок по умолчанию <span class="field-counter" data-counter-for="default_title"></span></label><input class="form-control" name="default_title" value="{{ old('default_title',$seo['default_title']) }}" required maxlength="255"><div class="seo-help mt-1">Рекомендуемая длина: 45–65 символов.</div></div>
                        <div class="mb-3"><label class="form-label">Суффикс заголовка</label><input class="form-control" name="title_suffix" value="{{ old('title_suffix',$seo['title_suffix']) }}" maxlength="120"><div class="seo-help mt-1">Добавляется к страницам, где ещё нет названия сайта.</div></div>
                        <div class="mb-3"><label class="form-label required">Описание по умолчанию <span class="field-counter" data-counter-for="default_description"></span></label><textarea class="form-control" name="default_description" required maxlength="320">{{ old('default_description',$seo['default_description']) }}</textarea><div class="seo-help mt-1">Рекомендуемая длина: 120–170 символов.</div></div>
                        <div class="mb-3"><label class="form-label">Ключевые слова</label><textarea class="form-control" name="default_keywords" maxlength="1000">{{ old('default_keywords',$seo['default_keywords']) }}</textarea><div class="seo-help mt-1">Через запятую. Поле второстепенное, основной эффект дают title, description и качественный контент.</div></div>
                        <div><label class="form-label">Основной адрес сайта</label><input class="form-control" type="url" name="canonical_base_url" value="{{ old('canonical_base_url',$seo['canonical_base_url']) }}" placeholder="https://palomnik.example.ru"><div class="seo-help mt-1">Используется для canonical, sitemap и структурированных данных. Пустое значение берётся из текущего домена.</div></div>
                    </div>

                    <div class="card-soft p-4 mb-4">
                        <h2 class="h4 mb-4">Open Graph и социальные сети</h2>
                        <div class="row g-3">
                            <div class="col-md-6"><label class="form-label">Тип Open Graph</label><select class="form-select" name="og_type"><option value="website" @selected($seo['og_type']==='website')>website</option><option value="article" @selected($seo['og_type']==='article')>article</option></select></div>
                            <div class="col-md-6"><label class="form-label">Twitter Card</label><select class="form-select" name="twitter_card"><option value="summary_large_image" @selected($seo['twitter_card']==='summary_large_image')>Большая картинка</option><option value="summary" @selected($seo['twitter_card']==='summary')>Компактная карточка</option></select></div>
                            <div class="col-12"><label class="form-label">Изображение для соцсетей</label><input class="form-control" name="og_image" value="{{ old('og_image',$seo['og_image']) }}" placeholder="/image/social-cover.jpg или https://..."><div class="seo-help mt-1">Рекомендуемый размер: 1200×630 px.</div></div>
                            <div class="col-12"><label class="form-label">Twitter/X аккаунт</label><input class="form-control" name="twitter_site" value="{{ old('twitter_site',$seo['twitter_site']) }}" placeholder="@account"></div>
                        </div>
                    </div>

                    <div class="card-soft p-4">
                        <h2 class="h4 mb-4">Организация и Schema.org</h2>
                        <div class="row g-3">
                            <div class="col-md-6"><label class="form-label required">Название организации</label><input class="form-control" name="organization_name" value="{{ old('organization_name',$seo['organization_name']) }}" required></div>
                            <div class="col-md-6"><label class="form-label">Юридическое название</label><input class="form-control" name="organization_legal_name" value="{{ old('organization_legal_name',$seo['organization_legal_name']) }}"></div>
                            <div class="col-md-6"><label class="form-label">Сайт организации</label><input class="form-control" type="url" name="organization_url" value="{{ old('organization_url',$seo['organization_url']) }}"></div>
                            <div class="col-md-6"><label class="form-label">Логотип</label><input class="form-control" name="organization_logo" value="{{ old('organization_logo',$seo['organization_logo']) }}" placeholder="/image/logo.png или https://..."></div>
                            <div class="col-md-6"><label class="form-label">Телефон</label><input class="form-control" name="organization_phone" value="{{ old('organization_phone',$seo['organization_phone']) }}"></div>
                            <div class="col-md-6"><label class="form-label">Email</label><input class="form-control" type="email" name="organization_email" value="{{ old('organization_email',$seo['organization_email']) }}"></div>
                            <div class="col-12"><label class="form-label">Адрес</label><input class="form-control" name="organization_address" value="{{ old('organization_address',$seo['organization_address']) }}"></div>
                            <div class="col-12"><label class="form-label">Ссылки на официальные страницы</label><textarea class="form-control" name="organization_same_as" placeholder="Каждая ссылка с новой строки">{{ old('organization_same_as',$seo['organization_same_as']) }}</textarea></div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-4">
                    <div class="card-soft p-4 mb-4">
                        <h2 class="h4 mb-4">Индексация</h2>
                        @foreach([
                            'robots_index'=>'Разрешить индексирование','robots_follow'=>'Разрешить переход по ссылкам','sitemap_enabled'=>'Включить sitemap.xml','structured_data_enabled'=>'Включить JSON-LD'
                        ] as $field=>$label)
                            <div class="form-check form-switch mb-3"><input type="hidden" name="{{ $field }}" value="0"><input class="form-check-input" type="checkbox" name="{{ $field }}" value="1" id="{{ $field }}" @checked(old($field,$seo[$field]))><label class="form-check-label" for="{{ $field }}">{{ $label }}</label></div>
                        @endforeach
                        <div class="small text-secondary border-top pt-3"><div><strong>Robots:</strong> <a href="{{ route('robots') }}" target="_blank">{{ route('robots') }}</a></div><div class="mt-2"><strong>Sitemap:</strong> <a href="{{ route('sitemap') }}" target="_blank">{{ route('sitemap') }}</a></div></div>
                    </div>

                    <div class="card-soft p-4 mb-4">
                        <h2 class="h4 mb-4">Подтверждение поисковиков</h2>
                        <div class="mb-3"><label class="form-label">Google Search Console</label><input class="form-control" name="google_site_verification" value="{{ old('google_site_verification',$seo['google_site_verification']) }}" placeholder="Код verification"></div>
                        <div><label class="form-label">Яндекс Вебмастер</label><input class="form-control" name="yandex_verification" value="{{ old('yandex_verification',$seo['yandex_verification']) }}" placeholder="Код verification"></div>
                    </div>

                    <div class="card-soft p-4 position-sticky" style="top:98px">
                        <h2 class="h5">Что будет добавлено</h2>
                        <ul class="small text-secondary ps-3 mb-4">
                            <li>canonical и robots;</li><li>Open Graph и Twitter Card;</li><li>Google и Яндекс verification;</li><li>Organization и WebSite JSON-LD;</li><li>динамические sitemap.xml и robots.txt.</li>
                        </ul>
                        <button class="btn btn-gold w-100" type="submit"><i class="bi bi-save me-2"></i>Сохранить SEO</button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    document.querySelectorAll('.theme-form').forEach(function (form) {
        form.querySelectorAll('[data-color-picker]').forEach(function (picker) {
            const row = picker.closest('.color-field');
            const text = row?.querySelector('[data-color-text]');
            if (!text) return;
            picker.addEventListener('input', function () { text.value = picker.value.toLowerCase(); text.dispatchEvent(new Event('input')); });
            text.addEventListener('input', function () { if (/^#[0-9a-fA-F]{6}$/.test(text.value)) picker.value = text.value; updatePreview(); });
        });
    });

    function updatePreview() {
        const preview = document.getElementById('themePreview');
        if (!preview) return;
        const values = {};
        document.querySelectorAll('#newThemeForm [data-preview-color]').forEach(function (input) { values[input.dataset.previewColor] = input.value; });
        Object.entries(values).forEach(function ([key,value]) { preview.style.setProperty('--preview-' + key.replace('_','-'), value); });
    }
    updatePreview();

    document.querySelectorAll('[data-counter-for]').forEach(function (counter) {
        const field = document.querySelector('[name="' + counter.dataset.counterFor + '"]');
        if (!field) return;
        const render = function () { counter.textContent = field.value.length + ' символов'; };
        field.addEventListener('input', render); render();
    });

    const hash = window.location.hash;
    if (hash === '#seo') bootstrap.Tab.getOrCreateInstance(document.querySelector('[data-bs-target="#seoTab"]')).show();
})();
</script>
@endpush
