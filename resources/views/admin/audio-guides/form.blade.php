@php($audioGuide = $guideable->audioGuide)

<div class="card-soft p-4 mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h2 class="h5 mb-1"><i class="bi bi-headphones me-2"></i>Аудиогид</h2>
            <div class="small text-secondary">Основной рассказ, который посетитель сможет слушать на странице.</div>
        </div>
        @if($audioGuide)
            <span class="badge badge-published">Загружен</span>
        @endif
    </div>

    @if($audioGuide?->url)
        <div class="border rounded-4 p-3 bg-light mb-4">
            <div class="fw-semibold mb-2">{{ $audioGuide->title ?: 'Аудиогид' }}</div>
            <audio class="w-100" controls preload="metadata" src="{{ $audioGuide->url }}"></audio>
            @if($audioGuide->original_name)
                <div class="small text-secondary mt-2">Файл: {{ $audioGuide->original_name }}</div>
            @endif
        </div>
    @endif

    <form method="POST" enctype="multipart/form-data" action="{{ route($updateRouteName, $guideable) }}">
        @csrf
        @method('PUT')

        <div class="row g-3">
            <div class="col-12">
                <label class="form-label" for="{{ $fieldPrefix }}_audio_title">Название аудиогида</label>
                <input
                    class="form-control"
                    id="{{ $fieldPrefix }}_audio_title"
                    name="title"
                    value="{{ old('title', $audioGuide?->title) }}"
                    maxlength="255"
                    placeholder="Например, История Покровского монастыря"
                >
            </div>
            <div class="col-12">
                <label class="form-label {{ $audioGuide ? '' : 'required' }}" for="{{ $fieldPrefix }}_audio_file">
                    {{ $audioGuide ? 'Заменить аудиофайл' : 'Аудиофайл' }}
                </label>
                <input
                    class="form-control"
                    id="{{ $fieldPrefix }}_audio_file"
                    type="file"
                    name="audio_file"
                    accept="audio/mpeg,audio/mp4,audio/aac,audio/ogg,audio/wav,audio/webm,.mp3,.m4a,.aac,.ogg,.oga,.wav,.webm"
                    @required(! $audioGuide)
                >
                <div class="form-text">MP3, M4A, AAC, OGG, WAV или WebM. Максимальный размер — 100 МБ.</div>
            </div>
            <div class="col-12">
                <label class="form-label" for="{{ $fieldPrefix }}_audio_transcript">Текстовая расшифровка</label>
                <textarea
                    class="form-control"
                    id="{{ $fieldPrefix }}_audio_transcript"
                    name="transcript"
                    rows="8"
                    placeholder="Полный текст аудиогида для слабослышащих посетителей и поисковых систем"
                >{{ old('transcript', $audioGuide?->transcript) }}</textarea>
            </div>
        </div>

        <button class="btn btn-gold mt-4" type="submit">
            <i class="bi bi-cloud-arrow-up me-1"></i>{{ $audioGuide ? 'Сохранить изменения' : 'Загрузить аудиогид' }}
        </button>
    </form>

    @if($audioGuide)
        <form class="mt-3" method="POST" action="{{ route($destroyRouteName, $guideable) }}" onsubmit="return confirm('Удалить аудиогид и аудиофайл?')">
            @csrf
            @method('DELETE')
            <button class="btn btn-outline-danger" type="submit">
                <i class="bi bi-trash me-1"></i>Удалить аудиогид
            </button>
        </form>
    @endif
</div>
