@if(isset($object) && request()->routeIs('objects.show'))
<section class="pb-5">
    <div class="container">
        @if($object->isInformationCurrent())
            <div class="info-card d-flex flex-wrap align-items-center gap-3" role="status">
                <span class="info-icon flex-shrink-0"><i class="bi bi-patch-check-fill"></i></span>
                <div class="flex-grow-1">
                    <strong class="d-block">Информация подтверждена {{ $object->information_verified_at->locale('ru')->translatedFormat('j F Y года') }}</strong>
                    <span class="small text-secondary">
                        Сведения о расписании, контактах и условиях посещения проверены редакцией.
                        @if($object->next_verification_at)
                            Следующая проверка запланирована до {{ $object->next_verification_at->locale('ru')->translatedFormat('j F Y года') }}.
                        @endif
                    </span>
                </div>
                @if($object->information_source_url)
                    <a class="btn btn-sm btn-outline-pm" href="{{ $object->information_source_url }}" target="_blank" rel="noopener noreferrer">
                        <i class="bi bi-box-arrow-up-right me-1"></i>Источник
                    </a>
                @endif
            </div>
        @elseif($object->information_verified_at)
            <div class="info-card d-flex align-items-start gap-3 border-warning" role="status">
                <span class="info-icon flex-shrink-0"><i class="bi bi-clock-history"></i></span>
                <div>
                    <strong class="d-block">Информация ожидает повторной проверки</strong>
                    <span class="small text-secondary">Последняя проверка проводилась {{ $object->information_verified_at->locale('ru')->translatedFormat('j F Y года') }}. Перед поездкой уточните расписание и условия посещения по официальным контактам.</span>
                </div>
            </div>
        @endif
    </div>
</section>
@endif
