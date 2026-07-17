(function () {
    const container = document.getElementById('discussionMessages');
    const discussion = document.getElementById('discussion');
    const form = discussion?.querySelector('form[action$="/messages"]');

    if (!container || !form) {
        return;
    }

    const feedUrl = window.location.pathname.replace(/\/$/, '') + '/messages-feed';
    const reportedUserInput = document.getElementById('reportedUserId');
    const defaultReportedUserId = reportedUserInput?.value || '';
    let firstLoad = true;
    let requestInProgress = false;

    function bindReportButtons() {
        document.querySelectorAll('.report-message-button').forEach((button) => {
            if (button.dataset.reportBound === '1') {
                return;
            }

            button.dataset.reportBound = '1';
            button.addEventListener('click', () => {
                const messageInput = document.getElementById('reportedMessageId');
                const userInput = document.getElementById('reportedUserId');

                if (messageInput) messageInput.value = button.dataset.messageId || '';
                if (userInput) userInput.value = button.dataset.userId || defaultReportedUserId;
            });
        });
    }

    async function refreshMessages() {
        if (requestInProgress || document.hidden) {
            return;
        }

        requestInProgress = true;
        const wasNearBottom = container.scrollHeight - container.scrollTop - container.clientHeight < 90;

        try {
            const response = await fetch(feedUrl, {
                headers: {
                    'Accept': 'text/html',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin',
                cache: 'no-store'
            });

            if (!response.ok) {
                return;
            }

            const html = await response.text();
            if (html !== container.innerHTML) {
                container.innerHTML = html;
                bindReportButtons();

                if (firstLoad || wasNearBottom) {
                    container.scrollTop = container.scrollHeight;
                }
            }
        } catch (error) {
            console.debug('Не удалось обновить обсуждение:', error);
        } finally {
            firstLoad = false;
            requestInProgress = false;
        }
    }

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        const textarea = form.querySelector('textarea[name="body"]');
        const submitButton = form.querySelector('button[type="submit"]');
        const body = textarea?.value.trim() || '';

        if (body.length < 2) {
            textarea?.focus();
            return;
        }

        if (submitButton) submitButton.disabled = true;

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            });

            if (response.status === 422) {
                const payload = await response.json();
                const firstError = Object.values(payload.errors || {}).flat()[0] || payload.message;
                alert(firstError || 'Проверьте текст сообщения.');
                return;
            }

            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }

            if (textarea) textarea.value = '';
            await refreshMessages();
            container.scrollTop = container.scrollHeight;
        } catch (error) {
            console.error('Не удалось отправить сообщение:', error);
            alert('Не удалось отправить сообщение. Проверьте соединение и повторите попытку.');
        } finally {
            if (submitButton) submitButton.disabled = false;
        }
    });

    document.getElementById('reportModal')?.addEventListener('hidden.bs.modal', () => {
        const messageInput = document.getElementById('reportedMessageId');
        const userInput = document.getElementById('reportedUserId');
        if (messageInput) messageInput.value = '';
        if (userInput) userInput.value = defaultReportedUserId;
    });

    bindReportButtons();
    refreshMessages();
    window.setInterval(refreshMessages, 5000);
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) refreshMessages();
    });
})();
