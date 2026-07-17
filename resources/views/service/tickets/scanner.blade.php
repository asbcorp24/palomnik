@extends('site.layouts.app')

@section('title', 'Проверка QR-билетов — Московский паломник')

@push('styles')
<style>
.scanner-shell{max-width:980px;margin:0 auto}.scanner-camera{min-height:320px;border:2px dashed var(--pm-border);border-radius:22px;overflow:hidden;background:#111}.scan-result{display:none}.scan-result.show{display:block}.scan-valid{border-left:5px solid #198754}.scan-invalid{border-left:5px solid #dc3545}
</style>
@endpush

@section('content')
<section class="page-hero"><div class="container scanner-shell"><nav aria-label="breadcrumb"><ol class="breadcrumb small mb-3"><li class="breadcrumb-item"><a href="{{ route('service.dashboard') }}">Кабинет представителя</a></li><li class="breadcrumb-item active">Проверка билетов</li></ol></nav><div class="section-kicker mb-2">Посадка и регистрация</div><h1 class="section-title mb-3">Сканер QR-билетов</h1><p class="section-lead mb-0">Наведите камеру на QR-код билета или введите код вручную. Один билет можно отметить только один раз.</p></div></section>

<section class="section-space pt-5"><div class="container scanner-shell"><div class="row g-4">
<div class="col-lg-7"><div class="filter-card"><div class="d-flex justify-content-between align-items-center gap-3 mb-3"><h2 class="h5 mb-0">Камера</h2><button class="btn btn-sm btn-outline-pm" id="restartScanner" type="button"><i class="bi bi-arrow-clockwise me-1"></i>Перезапустить</button></div><div class="scanner-camera" id="qrReader"></div><div class="small text-secondary mt-3"><i class="bi bi-shield-lock me-1"></i>Камера используется только в браузере для чтения QR-кода.</div></div></div>
<div class="col-lg-5"><div class="filter-card mb-4"><h2 class="h5 mb-3">Ручная проверка</h2><label class="form-label" for="manualToken">Идентификатор или содержимое QR</label><input class="form-control" id="manualToken" placeholder="MP-TICKET:... или 64 символа"><button class="btn btn-pm-green w-100 mt-3" id="manualLookup" type="button"><i class="bi bi-search me-2"></i>Проверить билет</button></div><div class="info-card scan-result" id="scanResult"></div></div>
</div></div></section>
@endsection

@push('scripts')
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
(() => {
    const lookupUrl = @json(route('service.tickets.lookup'));
    const checkInUrl = @json(route('service.tickets.check-in'));
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const result = document.getElementById('scanResult');
    const manualInput = document.getElementById('manualToken');
    let scanner = null;
    let busy = false;

    const tokenFromValue = value => {
        value = String(value || '').trim();
        if (value.startsWith('MP-TICKET:')) value = value.slice(10);
        const match = value.match(/[a-f0-9]{64}/i);
        return match ? match[0] : '';
    };

    const escapeHtml = value => String(value ?? '').replace(/[&<>'"]/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[char]));

    function renderBooking(booking) {
        const closed = booking.is_closed;
        const used = booking.is_checked_in;
        result.className = 'info-card scan-result show ' + (closed || used ? 'scan-invalid' : 'scan-valid');
        result.innerHTML = `
            <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                <div><div class="small text-secondary">Билет</div><div class="fw-bold">${escapeHtml(booking.ticket_code)}</div></div>
                <span class="badge rounded-pill ${closed || used ? 'text-bg-danger' : 'text-bg-success'}">${closed ? 'Недействителен' : (used ? 'Уже использован' : 'Действителен')}</span>
            </div>
            <h3 class="h5 mb-2">${escapeHtml(booking.trip_title)}</h3>
            <div class="small text-secondary mb-3">${escapeHtml(booking.starts_at || 'Дата уточняется')} · ${escapeHtml(booking.meeting_point || 'Место уточняется')}</div>
            <div class="row g-3 small mb-3">
                <div class="col-7"><div class="text-secondary">Участник</div><strong>${escapeHtml(booking.contact_name)}</strong></div>
                <div class="col-5"><div class="text-secondary">Количество</div><strong>${booking.participants_count}</strong></div>
                <div class="col-6"><div class="text-secondary">Бронирование</div><strong>${escapeHtml(booking.status)}</strong></div>
                <div class="col-6"><div class="text-secondary">Оплата</div><strong>${escapeHtml(booking.payment_status)}</strong></div>
            </div>
            ${used ? `<div class="alert alert-warning mb-0">Отмечен ${escapeHtml(booking.checked_in_at)} пользователем ${escapeHtml(booking.checked_in_by || '—')}. Участников: ${booking.checked_in_participants}.</div>` : ''}
            ${!closed && !used ? `<label class="form-label mt-2" for="checkInParticipants">Отметить участников</label><input class="form-control" id="checkInParticipants" type="number" min="1" max="${booking.participants_count}" value="${booking.participants_count}"><button class="btn btn-pm-gold w-100 mt-3" id="confirmCheckIn" type="button"><i class="bi bi-check-circle me-2"></i>Подтвердить вход</button>` : ''}
        `;
        document.getElementById('confirmCheckIn')?.addEventListener('click', () => checkIn(booking.token || manualInput.dataset.token));
    }

    function renderError(message) {
        result.className = 'info-card scan-result show scan-invalid';
        result.innerHTML = `<div class="text-danger fw-semibold mb-2"><i class="bi bi-x-circle me-2"></i>Билет не подтверждён</div><div>${escapeHtml(message)}</div>`;
    }

    async function lookup(rawValue) {
        const token = tokenFromValue(rawValue);
        if (!token) { renderError('QR-код не похож на билет Московского паломника.'); return; }
        if (busy) return;
        busy = true;
        manualInput.dataset.token = token;
        try {
            const response = await fetch(lookupUrl + '?token=' + encodeURIComponent(token), {headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'},credentials:'same-origin'});
            if (!response.ok) {
                const payload = await response.json().catch(() => ({}));
                throw new Error(payload.message || 'Билет не найден.');
            }
            const booking = await response.json();
            booking.token = token;
            renderBooking(booking);
            if (scanner) await scanner.pause(true).catch(() => {});
        } catch (error) { renderError(error.message); } finally { busy = false; }
    }

    async function checkIn(token) {
        const participants = document.getElementById('checkInParticipants')?.value || 1;
        try {
            const response = await fetch(checkInUrl, {method:'POST',headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest','X-CSRF-TOKEN':csrf,'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify({token,participants:Number(participants)})});
            const payload = await response.json().catch(() => ({}));
            if (!response.ok) throw new Error(Object.values(payload.errors || {}).flat()[0] || payload.message || 'Не удалось подтвердить билет.');
            payload.booking.token = token;
            renderBooking(payload.booking);
            alert(payload.message);
        } catch (error) { renderError(error.message); }
    }

    async function startScanner() {
        if (scanner) await scanner.clear().catch(() => {});
        scanner = new Html5QrcodeScanner('qrReader', {fps:10,qrbox:{width:240,height:240},rememberLastUsedCamera:true}, false);
        scanner.render(text => lookup(text), () => {});
    }

    document.getElementById('manualLookup').addEventListener('click', () => lookup(manualInput.value));
    manualInput.addEventListener('keydown', event => { if (event.key === 'Enter') { event.preventDefault(); lookup(manualInput.value); } });
    document.getElementById('restartScanner').addEventListener('click', startScanner);
    startScanner();
})();
</script>
@endpush
