class RuleBotChat {
    constructor({ inputId, sendBtnId, messagesId, suggestListId, minHeight = 40, maxHeight = 120 }) {
        this.input = document.getElementById(inputId);
        this.sendBtn = document.getElementById(sendBtnId);
        this.messages = document.getElementById(messagesId);
        this.suggestList = document.getElementById(suggestListId);
        this.minHeight = minHeight;
        this.maxHeight = maxHeight;
        this.suggestTimer = null;

        if (!this.input || !this.sendBtn || !this.messages || !this.suggestList) return;

        this.init();
    }

    init() {
        // PC環境のボタン表記
        if (window.matchMedia('(hover: hover) and (pointer: fine)').matches) {
            this.sendBtn.textContent = '送信 Ctrl+Enter';
        }

        // 送信ボタン
        this.sendBtn.addEventListener('click', () => this.sendMessage());

        // Ctrl + Enter で送信
        this.input.addEventListener('keydown', (e) => {
            if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                e.preventDefault();
                this.sendMessage();
            }
        });

        // テキストエリア自動伸縮
        this.input.addEventListener('input', () => this.autoResize());

        // 《》サジェスト
        this.input.addEventListener('input', () => this.handleSuggest());

        // 外側クリックでサジェスト非表示
        document.addEventListener('click', (e) => {
            if (!this.input.contains(e.target) && !this.suggestList.contains(e.target)) {
                this.suggestList.style.display = 'none';
            }
        });
    }

    autoResize() {
        this.input.style.height = 'auto';
        const newHeight = Math.min(Math.max(this.input.scrollHeight, this.minHeight), this.maxHeight);
        this.input.style.height = newHeight + 'px';
    }

    resetHeight() {
        this.input.style.height = this.minHeight + 'px';
    }

    handleSuggest() {
        clearTimeout(this.suggestTimer);
        const val = this.input.value;
        const cursorPos = this.input.selectionStart;
        const textBeforeCursor = val.slice(0, cursorPos);
        const match = textBeforeCursor.match(/《([^》\n]*)$/);

        if (!match || match[1].trim().length === 0) {
            this.suggestList.style.display = 'none';
            return;
        }

        const query = match[1].trim();
        this.suggestTimer = setTimeout(async () => {
            try {
                const res = await fetch(`/api/cards?q=${encodeURIComponent(query)}`);
                const cards = await res.json();
                if (!cards || cards.length === 0) {
                    this.suggestList.style.display = 'none';
                    return;
                }

                this.suggestList.innerHTML = '';
                const uniqueNames = new Set();
                let count = 0;

                for (const card of cards) {
                    const nameToUse = card.display_name || card.card_name;
                    if (!nameToUse || uniqueNames.has(nameToUse)) continue;
                    uniqueNames.add(nameToUse);

                    const item = document.createElement('div');
                    item.className = 'rule-bot-suggest-item';
                    item.textContent = nameToUse;
                    item.onclick = () => this.selectCardName(nameToUse, match.index, cursorPos);
                    this.suggestList.appendChild(item);

                    if (++count >= 8) break;
                }
                this.suggestList.style.display = count > 0 ? 'block' : 'none';
            } catch (e) {
                this.suggestList.style.display = 'none';
            }
        }, 250);
    }

    selectCardName(cardName, startIndex, endIndex) {
        const val = this.input.value;
        const before = val.slice(0, startIndex);
        let after = val.slice(endIndex);

        // カーソル直後に「》」があれば取り除いて二重括弧を防止
        if (after.startsWith('》')) {
            after = after.slice(1);
        }

        this.input.value = before + '《' + cardName + '》' + after;
        this.suggestList.style.display = 'none';
        this.input.focus();

        const newCursor = (before + '《' + cardName + '》').length;
        this.input.setSelectionRange(newCursor, newCursor);
        this.autoResize();
    }

    async sendMessage() {
        const text = this.input.value.trim();
        if (!text) return;

        this.suggestList.style.display = 'none';

        const userMsg = document.createElement('div');
        userMsg.className = 'rule-bot-msg user';
        userMsg.textContent = text;
        this.messages.appendChild(userMsg);

        this.input.value = '';
        this.resetHeight();
        this.input.disabled = true;
        this.sendBtn.disabled = true;

        const loadingMsg = document.createElement('div');
        loadingMsg.className = 'rule-bot-msg bot';
        loadingMsg.textContent = '判定中...';
        this.messages.appendChild(loadingMsg);
        this.messages.scrollTop = this.messages.scrollHeight;

        try {
            const res = await fetch('/api/bot/chat', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ message: text })
            });
            const data = await res.json();
            if (!res.ok) {
                loadingMsg.textContent = data.error || 'エラーが発生しました。';
            } else {
                loadingMsg.innerHTML = marked.parse(data.reply || '回答を取得できませんでした。');
            }
        } catch (e) {
            loadingMsg.textContent = '通信エラーが発生しました: ' + e.message;
        } finally {
            this.input.disabled = false;
            this.sendBtn.disabled = false;
            this.input.focus();
            this.messages.scrollTop = this.messages.scrollHeight;
        }
    }
}