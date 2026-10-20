<div class="container" style="max-width: 860px; height: calc(100vh - 120px); display: flex; flex-direction: column;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
        <h2 style="margin: 0; font-size: 1.3rem;">AIジャッジ</h2>
    </div>

    <!-- メインチャットエリア（中央・大画面） -->
    <div style="flex: 1; width: 100%; background: #fff; border: 1px solid #ddd; border-radius: 8px; display: flex; flex-direction: column; overflow: hidden; min-height: 0;">
        <div id="fullChatMessages" class="rule-bot-messages" style="flex: 1; padding: 20px; font-size: 0.95rem;">
            <div class="rule-bot-msg bot">こんにちは！公式総合ルール全条文を記憶したAIジャッジです。<br>効果の処理順やルールの疑問があれば質問してください。カード名は《》で囲むと自動補完されます。<br><span style="font-size:0.8rem; color:#888;">※サーバー維持のため、5回の質問ごとに短い広告の視聴が必要です。</span></div>
        </div>

        <!-- 入力エリア -->
        <div class="rule-bot-input-area" style="padding: 12px; border-top: 1px solid #eee; display: flex; align-items: flex-end; gap: 8px;">
            <div id="fullSuggestList" class="rule-bot-suggest-list"></div>
            <textarea id="fullChatInput" class="rule-bot-input" style="height: 44px; min-height: 44px; max-height: 150px; font-size: 0.95rem; padding: 10px 12px; resize: none; box-sizing: border-box; margin: 0; overflow-y: auto;" placeholder="質問を入力"></textarea>
            <button id="fullChatSendBtn" class="rule-bot-send" style="height: 44px; padding: 0 16px; font-size: 0.88rem; white-space: nowrap; flex-shrink: 0; box-sizing: border-box; margin: 0; align-self: flex-end;">送信</button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    new RuleBotChat({
        inputId: 'fullChatInput',
        sendBtnId: 'fullChatSendBtn',
        messagesId: 'fullChatMessages',
        suggestListId: 'fullSuggestList',
        minHeight: 44,
        maxHeight: 150
    });
});
</script>