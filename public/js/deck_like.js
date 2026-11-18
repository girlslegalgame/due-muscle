// public/js/deck_like.js
(function(global) {
    const ICON_FILLED = `<svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M2 21h4V9H2v12zm22-11c0-1.1-.9-2-2-2h-6.31l.95-4.57.03-.32c0-.41-.17-.79-.44-1.06L14.17 1 7.59 7.59C7.22 7.95 7 8.45 7 9v10c0 1.1.9 2 2 2h9c.83 0 1.54-.5 1.84-1.22l3.02-7.05c.09-.23.14-.47.14-.73v-2z"/></svg>`;
    const ICON_OUTLINE = `<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3zM7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3"></path></svg>`;

    global.toggleDeckItemLike = function(event, btnEl) {
        if (event) {
            event.stopPropagation();
            event.preventDefault();
        }
        if (!btnEl || btnEl.dataset.loading === '1') return;

        const deckId = Number(btnEl.dataset.deckId);
        if (!deckId) return;

        btnEl.dataset.loading = '1';
        fetch('/api/decks/like', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ deck_id: deckId })
        })
        .then(res => {
            if (res.status === 401) {
                window.location.href = '/login';
                return null;
            }
            return res.json();
        })
        .then(data => {
            if (!data || !data.success) return;
            const liked = !!data.liked;
            btnEl.dataset.liked = liked ? '1' : '0';
            btnEl.style.color = liked ? '#e83e8c' : '#64748b';
            btnEl.title = liked ? '高評価を解除する' : '高評価する';
            const iconWrap = btnEl.querySelector('.like-icon-wrap');
            const countWrap = btnEl.querySelector('.like-count-num');
            if (iconWrap) iconWrap.innerHTML = liked ? ICON_FILLED : ICON_OUTLINE;
            if (countWrap) countWrap.textContent = data.likes_count;
        })
        .catch(() => {})
        .finally(() => {
            btnEl.dataset.loading = '0';
        });
    };
})(window);