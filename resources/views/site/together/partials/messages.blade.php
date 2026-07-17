<div class="d-grid gap-3">
    @forelse($messages as $message)
        <div class="{{ $message->is_system ? 'small text-secondary text-center py-2' : 'info-card' }}" data-message-id="{{ $message->id }}">
            @if($message->is_system)
                <i class="bi bi-info-circle me-1"></i>{{ $message->body }}
            @else
                <div class="d-flex justify-content-between gap-3 mb-2">
                    <strong>{{ optional($message->user)->name }}</strong>
                    <span class="small text-secondary">{{ $message->created_at->format('d.m.Y H:i') }}</span>
                </div>
                <div class="lh-lg">{!! nl2br(e($message->body)) !!}</div>
                @if($message->user_id !== $currentUserId)
                    <button class="btn btn-link btn-sm text-danger p-0 mt-2 report-message-button"
                            type="button"
                            data-bs-toggle="modal"
                            data-bs-target="#reportModal"
                            data-message-id="{{ $message->id }}"
                            data-user-id="{{ $message->user_id }}">
                        Пожаловаться на сообщение
                    </button>
                @endif
            @endif
        </div>
    @empty
        <div class="text-secondary text-center py-4">Обсуждение ещё не началось. Напишите первое сообщение.</div>
    @endforelse
</div>
