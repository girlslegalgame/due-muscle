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
        // 残り回数をボタンに反映
        this.updateSendButtonText();

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
                const res = await fetch(`/api/cards?q=${encodeURIComponent(query)}&sort=relevance`);
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
        // ★ 5回制限チェック（LocalStorage管理のためリロードしても保持）
        let askCount = parseInt(localStorage.getItem('rule_bot_ask_count') || '0', 10);
        if (askCount >= 5) {
            this.showRewardModal();
            return;
        }
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

            // ★ テキストとして取得してからJSONパースを試みる
            const resText = await res.text();
            let data;
            try {
                data = JSON.parse(resText);
            } catch (parseErr) {
                // HTML形式のエラーが返ってきた場合、そのまま画面に表示して原因を把握可能にする
                loadingMsg.innerHTML = 'サーバーエラーが発生しました:<br>' + resText;
                return;
            }

            if (!res.ok) {
                loadingMsg.textContent = data.error || 'エラーが発生しました。';
            } else {
                loadingMsg.innerHTML = marked.parse(data.reply || '回答を取得できませんでした。');

                // 送信成功時に質問カウントを加算して保存
                askCount++;
                localStorage.setItem('rule_bot_ask_count', askCount.toString());
                this.updateSendButtonText(); // ★ ボタン表記を更新
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

    // ★ リワード広告表示モーダル
    showRewardModal() {
        let modal = document.getElementById('ruleBotRewardModal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'ruleBotRewardModal';
            modal.className = 'reward-ad-modal';
            modal.innerHTML = `
                <div class="reward-ad-box">
                    <h3 style="margin-top:0; font-size:1.15rem; color:#222;">🎬 質問回数の上限に達しました</h3>
                    <p style="font-size:0.85rem; color:#666; line-height:1.5;">
                        AIジャッジの無料利用枠（5回）に達しました。<br>
                        短い広告動画を視聴すると、続けて<strong>5回</strong>質問できるようになります。
                    </p>
                    <button id="watchAdRewardBtn" style="width:100%; padding:12px; background:#28a745; color:#fff; border:none; border-radius:6px; font-weight:bold; cursor:pointer; font-size:0.95rem;">
                        広告動画を見て質問を再開する
                    </button>
                </div>
            `;
            document.body.appendChild(modal);

            document.getElementById('watchAdRewardBtn').addEventListener('click', () => {
                alert('広告を視聴しました。続けて5回質問が可能です。');
                localStorage.setItem('rule_bot_ask_count', '0'); // カウントリセット
                this.updateSendButtonText(); // ★ ボタン表記を更新
                modal.remove();
            });
        }
        modal.style.display = 'flex';
    }
    // ★ ボタン表示を更新（残り枠 / 5）
    updateSendButtonText() {
        const askCount = parseInt(localStorage.getItem('rule_bot_ask_count') || '0', 10);
        const remaining = Math.max(0, 5 - askCount);
        const isPc = window.matchMedia('(hover: hover) and (pointer: fine)').matches;
        const prefix = isPc ? '送信 Ctrl+Enter' : '送信';

        this.sendBtn.textContent = `${prefix} (${remaining}/5)`;
    }
}