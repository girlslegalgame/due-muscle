<div class="container" style="max-width: 1200px; height: calc(100vh - 120px); display: flex; flex-direction: column;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
        <h2 style="margin: 0; font-size: 1.3rem;">⚖️ デュエマ ルール判定・相談AI (専用画面)</h2>
        <a href="javascript:void(0)" onclick="toggleRuleBot()" style="font-size: 0.85rem; color: #007bff; text-decoration: none;">▶ 小窓モードで開く</a>
    </div>

    <div style="flex: 1; display: grid; grid-template-columns: 320px 1fr; gap: 16px; min-height: 0;">
        <!-- 左カラム：クイック質問ヘルプ＆カード検索 -->
        <div style="background: #fff; border: 1px solid #ddd; border-radius: 8px; padding: 16px; display: flex; flex-direction: column; gap: 12px; overflow-y: auto;">
            <h3 style="margin: 0; font-size: 1rem; border-bottom: 2px solid #007bff; padding-bottom: 6px;">💡 質問のコツ</h3>
            <p style="font-size: 0.85rem; color: #555; line-height: 1.5; margin: 0;">
                カード名を <strong>《》</strong> で囲むと、予測候補から正確なカード名を入力でき、判定精度が上がります。<br>
                (例: 《ヘブンズ・ゲート》)
            </p>
            <div style="margin-top: 10px;">
                <h4 style="margin: 0 0 6px 0; font-size: 0.9rem;">よくある質問例</h4>
                <ul style="font-size: 0.82rem; color: #007bff; padding-left: 18px; margin: 0; display: flex; flex-direction: column; gap: 6px; cursor: pointer;">
                    <li onclick="setPrompt(this.innerText)">スレイヤーは重複しますか？</li>
                    <li onclick="setPrompt(this.innerText)">置換効果は連鎖しますか？</li>
                    <li onclick="setPrompt(this.innerText)">パワーが0になったクリーチャーは破壊されますか？</li>
                    <li onclick="setPrompt(this.innerText)">AP優先の原則とはなんですか？</li>
                </ul>
            </div>
        </div>

        <!-- 右カラム：メインチャットエリア（大画面） -->
        <div style="background: #fff; border: 1px solid #ddd; border-radius: 8px; display: flex; flex-direction: column; overflow: hidden;">
            <div id="fullChatMessages" class="rule-bot-messages" style="flex: 1; padding: 20px; font-size: 0.95rem;">
                <div class="rule-bot-msg bot">
                    こんにちは！公式総合ルール全条文を記憶したAIジャッジです。<br>
                    効果の処理順やルールの疑問があれば質問してください。カード名は《》で囲むと自動補完されます。
                </div>
            </div>

            <!-- 入力エリア -->
            <div class="rule-bot-input-area" style="padding: 12px; border-top: 1px solid #eee;">
                <div id="fullSuggestList" class="rule-bot-suggest-list"></div>
                <input type="text" id="fullChatInput" class="rule-bot-input" style="font-size: 1rem; padding: 10px 14px;" placeholder="質問を入力 (例: 《龍風混成 ザーディクリカ》で...)">
                <button id="fullChatSendBtn" class="rule-bot-send" style="padding: 0 20px; font-size: 0.95rem;" onclick="sendFullChatMessage()">送信</button>
            </div>
        </div>
    </div>
</div>

<script>
function setPrompt(text) {
    const input = document.getElementById('fullChatInput');
    input.value = text;
    input.focus();
}

async function sendFullChatMessage() {
    const input = document.getElementById('fullChatInput');
    const sendBtn = document.getElementById('fullChatSendBtn');
    const container = document.getElementById('fullChatMessages');
    const text = input.value.trim();
    if (!text) return;

    // ユーザー発言描画
    const userMsg = document.createElement('div');
    userMsg.className = 'rule-bot-msg user';
    userMsg.textContent = text;
    container.appendChild(userMsg);
    input.value = '';
    input.disabled = true;
    sendBtn.disabled = true;

    // ロード中表示
    const loadingMsg = document.createElement('div');
    loadingMsg.className = 'rule-bot-msg bot';
    loadingMsg.textContent = '判定中...';
    container.appendChild(loadingMsg);
    container.scrollTop = container.scrollHeight;

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
        input.disabled = false;
        sendBtn.disabled = false;
        input.focus();
        container.scrollTop = container.scrollHeight;
    }
}

document.getElementById('fullChatInput').addEventListener('keydown', function(e) {
    if (e.key === 'Enter') sendFullChatMessage();
});
</script>