@if($audioGuide?->url)
<section class="mb-5 audio-guide-section">
    <div class="section-kicker mb-2">Аудиогид</div>
    <h2 class="h2 mb-4">{{ $audioGuide->title ?: 'Слушать рассказ' }}</h2>

    <div class="info-card">
        <div class="d-flex align-items-center gap-3 mb-3">
            <span class="info-icon flex-shrink-0"><i class="bi bi-headphones"></i></span>
            <div>
                <div class="fw-semibold">{{ $audioGuide->title ?: 'Аудиогид' }}</div>
                <div class="small text-secondary">Можно слушать во время самостоятельного посещения.</div>
            </div>
        </div>

        <audio class="w-100 offline-asset" controls preload="metadata" src="{{ $audioGuide->url }}">
            Ваш браузер не поддерживает воспроизведение аудио.
        </audio>

        @if($audioGuide->transcript)
            <details class="mt-4">
                <summary class="fw-semibold" style="cursor:pointer">Показать текстовую расшифровку</summary>
                <div class="text-secondary lh-lg mt-3">{!! nl2br(e($audioGuide->transcript)) !!}</div>
            </details>
        @endif
    </div>
</section>
@endif
