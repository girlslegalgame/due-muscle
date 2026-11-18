// public/js/deck_article.js
(function(global) {
    function injectArticleStyles() {
        if (document.getElementById('deck-article-common-styles')) return;
        const style = document.createElement('style');
        style.id = 'deck-article-common-styles';
        style.textContent = `
            .deck-article-rendered {
                font-size: 0.96rem;
                line-height: 1.8;
                color: #2d3748;
                word-break: break-word;
            }
            .deck-article-rendered h2 {
                font-size: 1.35rem;
                margin: 22px 0 10px;
                padding-bottom: 6px;
                border-bottom: 2px solid #007bff;
                color: #1a202c;
            }
            .deck-article-rendered h3 {
                font-size: 1.15rem;
                margin: 18px 0 8px;
                padding-left: 10px;
                border-left: 4px solid #007bff;
                color: #1a202c;
            }
            .deck-article-rendered h4 {
                font-size: 1.02rem;
                margin: 14px 0 6px;
                font-weight: bold;
                color: #2d3748;
            }
            .deck-article-rendered p {
                margin: 0 0 10px 0;
            }
            .deck-article-rendered ul {
                margin: 8px 0 12px 20px;
                padding: 0;
            }
            .deck-article-rendered li {
                margin-bottom: 4px;
            }
            .deck-article-rendered blockquote {
                margin: 10px 0;
                padding: 10px 14px;
                background: #f8fafc;
                border-left: 4px solid #cbd5e1;
                color: #4a5568;
                border-radius: 0 6px 6px 0;
            }
            .deck-article-rendered hr {
                border: none;
                border-top: 1px solid #e2e8f0;
                margin: 18px 0;
            }
            .article-card-grid {
                display: flex;
                flex-wrap: wrap;
                gap: 10px;
                margin: 12px 0 16px;
            }
            .article-card-chip {
                width: 100px;
                display: flex;
                flex-direction: column;
                align-items: center;
                gap: 4px;
                cursor: pointer;
                transition: transform 0.15s ease;
                position: relative;
            }
            .article-card-remove-btn {
                position: absolute;
                top: -6px;
                right: -6px;
                width: 20px;
                height: 20px;
                border-radius: 50%;
                background: #dc3545;
                color: #fff;
                border: 1.5px solid #fff;
                font-size: 12px;
                font-weight: bold;
                line-height: 1;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                box-shadow: 0 1px 4px rgba(0,0,0,0.3);
                z-index: 2;
                padding: 0;
            }
            .article-card-remove-btn:hover {
                background: #b91c1c;
            }
            .article-card-chip:hover {
                transform: translateY(-2px);
            }
            .article-card-chip img {
                width: 100%;
                height: auto;
                aspect-ratio: 110 / 154;
                object-fit: contain;
                border-radius: 6px;
                border: 1px solid #cbd5e1;
                box-shadow: 0 2px 6px rgba(0,0,0,0.12);
                background: #fff;
            }
            .article-card-chip span {
                font-size: 0.72rem;
                color: #475569;
                text-align: center;
                line-height: 1.25;
                display: -webkit-box;
                -webkit-line-clamp: 2;
                -webkit-box-orient: vertical;
                overflow: hidden;
            }
            @media (max-width: 768px) {
                .article-card-chip {
                    width: 82px;
                }
            }
        `;
        document.head.appendChild(style);
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function resolveCardImg(cardId, imagepath) {
        if (typeof getCardImageUrl === 'function') {
            return getCardImageUrl({ card_id: cardId, imagepath: imagepath });
        }
        if (!imagepath) return '/images/card/noimage.webp';
        if (imagepath.startsWith('http') || imagepath.startsWith('/') || imagepath.startsWith('data:')) {
            return imagepath;
        }
        return '/images/card/' + imagepath;
    }

    function formatInline(escapedLine) {
        let s = escapedLine;
        // **太文字**
        s = s.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
        // *斜体*
        s = s.replace(/\*(.+?)\*/g, '<em>$1</em>');
        // ~~取り消し線~~
        s = s.replace(/~~(.+?)~~/g, '<del>$1</del>');
        return s;
    }

    function buildCardChipHtml(cardId, cardName, imagepath, editable = false) {
        const safeId = escapeHtml(cardId);
        const safeName = escapeHtml(cardName);
        const src = escapeHtml(resolveCardImg(cardId, imagepath));
        if (editable) {
            return `<div class="article-card-chip" contenteditable="false" data-card-id="${safeId}" data-card-name="${safeName}" data-imagepath="${escapeHtml(imagepath)}">` +
                   `<button type="button" class="article-card-remove-btn" contenteditable="false" title="画像を削除" onclick="if(typeof removeArticleCardChip==='function') removeArticleCardChip(this)">×</button>` +
                   `<img src="${src}" alt="${safeName}" draggable="false" onerror="this.src='/images/card/noimage.webp';this.onerror=null;">` +
                   `<span>${safeName}</span>` +
                   `</div>`;
        }
        return `<div class="article-card-chip" onclick="if(typeof openCardDetail==='function' && !String('${safeId}').startsWith('dummy_')) openCardDetail('${safeId}', this)">` +
               `<img src="${src}" alt="${safeName}" onerror="this.src='/images/card/noimage.webp';this.onerror=null;">` +
               `<span>${safeName}</span>` +
               `</div>`;
    }

    const cardTagRegex = /\[\[card:([^|\]]+)\|([^|\]]*)\|([^\]]*)\]\]/g;

    function render(rawText, options = {}) {
        injectArticleStyles();
        const editable = !!options.editable;
        if (!rawText || !String(rawText).trim()) {
            return editable ? '' : '<p style="color:#888;">記事はまだ作成されていません。</p>';
        }

        const lines = String(rawText).replace(/\r\n/g, '\n').split('\n');
        const out = [];
        let inList = false;
        let inQuote = false;
        let cardBuffer = [];

        const flushList = () => {
            if (inList) { out.push('</ul>'); inList = false; }
        };
        const flushQuote = () => {
            if (inQuote) { out.push('</blockquote>'); inQuote = false; }
        };
        const flushCards = () => {
            if (cardBuffer.length > 0) {
                const ceAttr = editable ? ' contenteditable="false"' : '';
                out.push(`<div class="article-card-grid"${ceAttr}>` + cardBuffer.join('') + '</div>');
                cardBuffer = [];
            }
        };

        for (let i = 0; i < lines.length; i++) {
            const rawLine = lines[i];
            const trimmed = rawLine.trim();

            const strippedCards = trimmed.replace(cardTagRegex, '').trim();
            if (trimmed !== '' && strippedCards === '') {
                flushList();
                flushQuote();
                let m;
                cardTagRegex.lastIndex = 0;
                while ((m = cardTagRegex.exec(trimmed)) !== null) {
                    cardBuffer.push(buildCardChipHtml(m[1], m[2], m[3], editable));
                }
                continue;
            }
            flushCards();

            if (trimmed === '') {
                flushList();
                flushQuote();
                continue;
            }

            if (trimmed === '---' || trimmed === '***') {
                flushList();
                flushQuote();
                out.push('<hr>');
                continue;
            }

            if (trimmed.startsWith('### ')) {
                flushList();
                flushQuote();
                out.push('<h4>' + formatInline(escapeHtml(trimmed.slice(4))) + '</h4>');
                continue;
            }
            if (trimmed.startsWith('## ')) {
                flushList();
                flushQuote();
                out.push('<h3>' + formatInline(escapeHtml(trimmed.slice(3))) + '</h3>');
                continue;
            }
            if (trimmed.startsWith('# ')) {
                flushList();
                flushQuote();
                out.push('<h2>' + formatInline(escapeHtml(trimmed.slice(2))) + '</h2>');
                continue;
            }

            if (trimmed.startsWith('- ') || trimmed.startsWith('* ')) {
                flushQuote();
                if (!inList) {
                    out.push('<ul>');
                    inList = true;
                }
                out.push('<li>' + renderInlineWithCards(trimmed.slice(2), editable) + '</li>');
                continue;
            }
            flushList();

            if (trimmed.startsWith('> ')) {
                if (!inQuote) {
                    out.push('<blockquote>');
                    inQuote = true;
                }
                out.push('<p>' + renderInlineWithCards(trimmed.slice(2), editable) + '</p>');
                continue;
            }
            flushQuote();

            out.push('<p>' + renderInlineWithCards(rawLine, editable) + '</p>');
        }

        flushCards();
        flushList();
        flushQuote();

        if (editable) {
            return out.join('\n');
        }
        return '<div class="deck-article-rendered">' + out.join('\n') + '</div>';
    }

    function renderInlineWithCards(text, editable = false) {
        const parts = [];
        let lastIdx = 0;
        cardTagRegex.lastIndex = 0;
        let match;
        while ((match = cardTagRegex.exec(text)) !== null) {
            if (match.index > lastIdx) {
                parts.push(formatInline(escapeHtml(text.slice(lastIdx, match.index))));
            }
            parts.push(buildCardChipHtml(match[1], match[2], match[3], editable));
            lastIdx = cardTagRegex.lastIndex;
        }
        if (lastIdx < text.length) {
            parts.push(formatInline(escapeHtml(text.slice(lastIdx))));
        }
        return parts.join('');
    }

    function serializeInline(node) {
        if (node.nodeType === Node.TEXT_NODE) {
            return node.nodeValue.replace(/\u00A0/g, ' ');
        }
        if (node.nodeType !== Node.ELEMENT_NODE) return '';

        const tag = node.tagName;
        if (tag === 'BR') return '\n';
        if (node.classList.contains('article-card-chip')) {
            const id = node.dataset.cardId || '';
            const name = node.dataset.cardName || '';
            const path = node.dataset.imagepath || '';
            return `[[card:${id}|${name}|${path}]]`;
        }
        if (node.classList.contains('article-card-grid')) {
            const chips = Array.from(node.querySelectorAll('.article-card-chip'));
            return chips.map(ch => serializeInline(ch)).join(' ');
        }

        const inner = Array.from(node.childNodes).map(serializeInline).join('');
        if (tag === 'STRONG' || tag === 'B') {
            return inner.trim() ? `**${inner}**` : inner;
        }
        if (tag === 'EM' || tag === 'I') {
            return inner.trim() ? `*${inner}*` : inner;
        }
        if (tag === 'DEL' || tag === 'S' || tag === 'STRIKE') {
            return inner.trim() ? `~~${inner}~~` : inner;
        }
        return inner;
    }

    function serializeEditor(editorEl) {
        if (!editorEl) return '';
        const blocks = [];

        const processNode = (node) => {
            if (node.nodeType === Node.TEXT_NODE) {
                const txt = node.nodeValue.replace(/\u00A0/g, ' ').trim();
                if (txt) blocks.push(txt);
                return;
            }
            if (node.nodeType !== Node.ELEMENT_NODE) return;

            const tag = node.tagName;
            if (node.classList.contains('article-card-grid')) {
                const chips = Array.from(node.querySelectorAll('.article-card-chip'));
                if (chips.length > 0) {
                    blocks.push(chips.map(ch => serializeInline(ch)).join(' '));
                }
                return;
            }
            if (node.classList.contains('article-card-chip')) {
                blocks.push(serializeInline(node));
                return;
            }
            if (tag === 'HR') {
                blocks.push('---');
                return;
            }
            if (tag === 'H1' || tag === 'H2') {
                const t = Array.from(node.childNodes).map(serializeInline).join('').trim();
                if (t) blocks.push('# ' + t);
                return;
            }
            if (tag === 'H3') {
                const t = Array.from(node.childNodes).map(serializeInline).join('').trim();
                if (t) blocks.push('## ' + t);
                return;
            }
            if (tag === 'H4') {
                const t = Array.from(node.childNodes).map(serializeInline).join('').trim();
                if (t) blocks.push('### ' + t);
                return;
            }
            if (tag === 'UL' || tag === 'OL') {
                const lis = Array.from(node.children).filter(el => el.tagName === 'LI');
                const lines = lis.map(li => '- ' + Array.from(li.childNodes).map(serializeInline).join('').trim()).filter(l => l !== '- ');
                if (lines.length > 0) blocks.push(lines.join('\n'));
                return;
            }
            if (tag === 'BLOCKQUOTE') {
                const raw = Array.from(node.childNodes).map(ch => {
                    if (ch.nodeType === Node.ELEMENT_NODE && (ch.tagName === 'P' || ch.tagName === 'DIV')) {
                        return Array.from(ch.childNodes).map(serializeInline).join('').trim();
                    }
                    return serializeInline(ch).trim();
                }).filter(Boolean).join('\n');
                if (raw) {
                    blocks.push(raw.split('\n').map(l => '> ' + l).join('\n'));
                }
                return;
            }
            if (tag === 'P' || tag === 'DIV') {
                // 内側にブロック要素が含まれるDIVは再帰処理
                const hasBlockChild = Array.from(node.children).some(ch =>
                    ['P', 'DIV', 'H1', 'H2', 'H3', 'H4', 'UL', 'OL', 'BLOCKQUOTE', 'HR'].includes(ch.tagName) ||
                    ch.classList.contains('article-card-grid')
                );
                if (hasBlockChild) {
                    Array.from(node.childNodes).forEach(processNode);
                    return;
                }
                const t = Array.from(node.childNodes).map(serializeInline).join('').replace(/\n+$/g, '').trim();
                if (t) blocks.push(t);
                return;
            }
            const t = serializeInline(node).trim();
            if (t) blocks.push(t);
        };

        Array.from(editorEl.childNodes).forEach(processNode);
        return blocks.join('\n\n');
    }

    function insertAtCursor(textarea, before, after = '', placeholder = '') {
        if (!textarea) return;
        const start = textarea.selectionStart || 0;
        const end = textarea.selectionEnd || 0;
        const val = textarea.value;
        const selected = val.substring(start, end) || placeholder;
        const nextVal = val.substring(0, start) + before + selected + after + val.substring(end);
        textarea.value = nextVal;
        const cursorPos = start + before.length + selected.length + after.length;
        textarea.focus();
        textarea.setSelectionRange(cursorPos, cursorPos);
        textarea.dispatchEvent(new Event('input', { bubbles: true }));
    }

    global.DeckArticle = {
        render,
        buildCardChipHtml,
        serializeEditor,
        insertAtCursor,
        injectArticleStyles
    };
})(window);