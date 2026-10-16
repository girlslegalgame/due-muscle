<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="google-adsense-account" content="ca-pub-1782055298829063">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>デュエ筋</title>
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <link rel="stylesheet" href="/css/style.css?v=<?php echo time(); ?>">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script> 
    <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-1782055298829063"
     crossorigin="anonymous"></script>
    <style>
        /* --- ヘッダーを黒背景・白文字に変更 --- */
        header {
            background-color: #000 !important;
            color: #fff !important;
            border-bottom: 1px solid #333 !important;
        }

        header h1 a {
            color: #fff !important;
            display: flex;
            align-items: center;
        }

        /* ロゴ画像のサイズ調整 */
        .header-logo {
            height: 35px;
            width: auto;
            object-fit: contain;
        }

        /* リンクの文字色を白に変更 */
        header a,
        header a:link,
        header a:visited {
            color: #fff !important;
        }

        /* --- ハンバーガーメニュー用のデフォルト非表示設定 --- */
        .menu-toggle {
            display: none;
        }

        /* --- スマートフォン環境（レスポンシブ） --- */
        @media (max-width: 768px) {
            .menu-toggle {
                display: flex;
                flex-direction: column;
                justify-content: space-between;
                width: 24px;
                height: 18px;
                background: transparent;
                border: none;
                cursor: pointer;
                padding: 0;
                z-index: 2100;
            }
            .menu-toggle span {
                width: 100%;
                height: 2px;
                background-color: #fff;
                transition: all 0.3s ease;
            }

            .menu-toggle.active span:nth-child(1) {
                transform: translateY(8px) rotate(45deg);
            }
            .menu-toggle.active span:nth-child(2) {
                opacity: 0;
            }
            .menu-toggle.active span:nth-child(3) {
                transform: translateY(-8px) rotate(-45deg);
            }

            .header-right {
                display: none !important;
                position: fixed;
                top: 60px;
                left: 0;
                width: 100%;
                background-color: #000;
                border-bottom: 1px solid #333;
                flex-direction: column;
                align-items: center;
                gap: 0 !important;
                padding: 0;
                box-shadow: 0 4px 10px rgba(0, 0, 0, 0.5);
                z-index: 1999;
            }

            .header-right.active {
                display: flex !important;
            }

            .header-right a {
                display: block;
                width: 100%;
                text-align: center;
                padding: 16px 0;
                border-bottom: 1px solid #222;
                box-sizing: border-box;
                color: #fff !important;
            }
            .header-right a:last-child {
                border-bottom: none;
            }
        }

        /* --- ルールボット サジェスト候補（重複なし） --- */
        .rule-bot-input-area {
            position: relative;
        }
        .rule-bot-suggest-list {
            position: absolute;
            bottom: 100%;
            left: 8px;
            right: 8px;
            background: #fff;
            border: 1px solid #ccc;
            border-radius: 6px;
            box-shadow: 0 -4px 12px rgba(0,0,0,0.15);
            max-height: 200px;
            overflow-y: auto;
            display: none;
            z-index: 2600;
        }
        .rule-bot-suggest-item {
            padding: 8px 12px;
            cursor: pointer;
            font-size: 0.85rem;
            color: #333;
            border-bottom: 1px solid #f0f0f0;
            transition: background 0.15s;
        }
        .rule-bot-suggest-item:hover {
            background-color: #e0f2fe;
            color: #0369a1;
        }
        .rule-bot-suggest-item:last-child {
            border-bottom: none;
        }
    </style>
</head>
<body>
    <header>
        <div class="header-left">
            <h1>
                <a href="/">
                    <img src="/images/logo.webp" alt="DECK MAKER" class="header-logo">
                </a>
            </h1>
        </div>
        
        <button class="menu-toggle" onclick="toggleMobileMenu()" aria-label="メニューを開閉する">
            <span></span>
            <span></span>
            <span></span>
        </button>

        <div class="header-right">
            <a href="/mydecks">マイデッキ</a>
            <a href="/search">デッキ検索</a>
            <a href="/decks/new" onclick="handleDeckCreateClick(event)">デッキ作成</a>
            <a href="/help">ヘルプ</a>
            <?php 
                $unreadCnt = getUnreadNotificationCount(); 
                $badgeText = $unreadCnt > 9 ? '9+' : $unreadCnt;
            ?>
            <a href="/notifications" style="position: relative; display: inline-flex; align-items: center;">
                お知らせ
                <?php if ($unreadCnt > 0): ?>
                    <span style="background-color: #ef4444; color: #fff; font-size: 0.7rem; font-weight: bold; border-radius: 10px; padding: 1px 6px; margin-left: 4px; line-height: 1.2;">
                        <?= $badgeText ?>
                    </span>
                <?php endif; ?>
            </a>
            <a href="<?php echo isset($_SESSION['user_id']) ? '/account' : '/login'; ?>">アカウント</a>
            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                <a href="/admin" style="color: #ffc107 !important; font-weight: bold;">管理者画面</a>
            <?php endif; ?>
        </div>
    </header>

    <main>
        <?php echo $content ?? ''; ?>
    </main>

    <?php if (empty($hideFooter)): ?>
    <footer>
        <ul>
            <li><a href="/help">ヘルプ</a></li>
            <li><a href="/terms">利用規約</a></li>
        </ul>
        <div style="font-size: 0.8rem; color: #888; margin-top: 10px;">
            &copy; <?= date('Y') ?> デュエ筋 All rights reserved.
        </div>
    </footer>
    <?php endif; ?>

    <script src="/js/main.js"></script>
    
    <script>
        // ログイン完了直後の自動保存スクリプト
        (function() {
            if (window._deckAutoSaveExecuted) return;
            window._deckAutoSaveExecuted = true;

            window.addEventListener('DOMContentLoaded', () => {
                const currentPath = window.location.pathname;
                const authPaths = ['/login', '/register', '/login/verify', '/register/verify', '/logout'];
                if (authPaths.includes(currentPath)) return; 

                if (localStorage.getItem('pending_deck_save') === 'true') {
                    const savedPayloadStr = localStorage.getItem('pending_deck_payload');
                    localStorage.removeItem('pending_deck_save');
                    localStorage.removeItem('pending_deck_payload');
                    localStorage.removeItem('unsaved_deck_draft');

                    if (savedPayloadStr) {
                        try {
                            const payload = JSON.parse(savedPayloadStr);
                            fetch('/api/decks', {
                                method: payload.deck_id ? 'PUT' : 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify(payload)
                            })
                            .then(res => res.json())
                            .then(data => {
                                if (data && data.success) {
                                    alert("ログインが完了し、作成していたデッキの自動保存に成功しました！");
                                    window.location.replace('/mydecks');
                                } else {
                                    alert("ログインは完了しましたが、デッキの自動保存に失敗しました: " + (data ? data.error : '不明なエラー'));
                                }
                            })
                            .catch(err => {
                                console.error(err);
                                alert("自動保存中に通信エラーが発生しました。");
                            });
                        } catch (e) {
                            console.error(e);
                        }
                    }
                }
            });
        })();

        function toggleMobileMenu() {
            const menuToggle = document.querySelector('.menu-toggle');
            const headerRight = document.querySelector('.header-right');
            if (menuToggle && headerRight) {
                menuToggle.classList.toggle('active');
                headerRight.classList.toggle('active');
            }
        }
    </script>
<!-- デッキ作成先 選択モーダル -->
<div id="deck-type-select-modal" class="sub-modal" style="display: none; z-index: 3500;">
    <div class="sub-modal-content" style="max-width: 420px; border-radius: 12px; overflow: hidden;">
        <div class="sub-modal-header" style="background: #111; color: #fff; padding: 14px 20px;">
            <span style="font-weight: bold; font-size: 1rem;">作成するデッキを選択</span>
            <button type="button" onclick="closeDeckTypeSelectModal()" style="background: none; border: none; color: #fff; font-size: 1.4rem; cursor: pointer;">×</button>
        </div>
        <div class="sub-modal-body" style="padding: 24px; text-align: center;">
            <p style="margin-top: 0; margin-bottom: 20px; font-size: 0.95rem; color: #333; font-weight: bold;">
                どちらのデッキを作成しますか？
            </p>
            <div style="display: flex; gap: 12px; margin-bottom: 20px;">
                <button type="button" onclick="selectDeckType('/decks/new')" style="flex: 1; padding: 16px 10px; background: #007bff; color: #fff; border: none; border-radius: 8px; font-weight: bold; cursor: pointer; font-size: 0.95rem;">
                    デュエル・マスターズ
                </button>
                <button type="button" onclick="selectDeckType('/tools/ccfolia')" style="flex: 1; padding: 16px 10px; background: #28a745; color: #fff; border: none; border-radius: 8px; font-weight: bold; cursor: pointer; font-size: 0.95rem;">
                    デュエマ以外<br><span style="font-size: 0.75rem; font-weight: normal;">(ココフォリア出力)</span>
                </button>
            </div>
            
            <div style="border-top: 1px solid #eee; padding-top: 15px; text-align: left;">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <!-- ログイン中：チェックボックスを表示 -->
                    <label style="display: flex; align-items: center; gap: 6px; font-size: 0.85rem; color: #555; cursor: pointer;">
                        <input type="checkbox" id="deck-type-remember" style="width: 16px; height: 16px;">
                        次回からこの画面を表示しない
                    </label>
                    <p style="font-size: 0.75rem; color: #888; margin: 6px 0 0 22px; line-height: 1.3;">
                        ※この設定は後から「アカウント設定」画面で変更・リセットできます。
                    </p>
                <?php else: ?>
                    <!-- 未ログイン：チェックボックスは非表示にし、案内文を表示 -->
                    <p style="font-size: 0.8rem; color: #666; margin: 0; line-height: 1.4; text-align: center;">
                        <a href="/login" style="color: #007bff !important; text-decoration: underline !important; font-weight: bold;">ログイン</a>すると、次回以降この選択をスキップしてデフォルトで開く画面を設定できます。
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
// デッキ作成クリック時の振り分け
function handleDeckCreateClick(e) {
    e.preventDefault();
    const savedTarget = localStorage.getItem('default_deck_builder');
    if (savedTarget === 'dm') {
        window.location.href = '/decks/new';
        return;
    } else if (savedTarget === 'other') {
        window.location.href = '/tools/ccfolia';
        return;
    }
    document.getElementById('deck-type-select-modal').style.display = 'flex';
}

function closeDeckTypeSelectModal() {
    document.getElementById('deck-type-select-modal').style.display = 'none';
}

function selectDeckType(url) {
    const rememberCheckbox = document.getElementById('deck-type-remember');
    const remember = rememberCheckbox ? rememberCheckbox.checked : false;
    if (remember) {
        localStorage.setItem('default_deck_builder', url === '/decks/new' ? 'dm' : 'other');
    }
    window.location.href = url;
}
</script>

    <!-- ★ ルールAIボット ウィジェット -->
    <button class="rule-bot-launcher" onclick="toggleRuleBot()" title="ルール判定AIに聞く">⚖️</button>

    <div class="rule-bot-window" id="ruleBotWindow">
        <div class="rule-bot-header">
            <span>デュエマ ルール相談AI</span>
            <button class="rule-bot-close" onclick="toggleRuleBot()">✕</button>
        </div>
        <div class="rule-bot-messages" id="ruleBotMessages">
            <div class="rule-bot-msg bot">こんにちは！デュエマのルールや裁定、効果の解決順などで気になることがあれば質問してください。<br>カード名は《》で囲むと予測変換から正確に入力できます。</div>
        </div>
        
        <!-- サジェストリスト付き入力エリア -->
        <div class="rule-bot-input-area">
            <div id="ruleBotSuggestList" class="rule-bot-suggest-list"></div>
            <input type="text" id="ruleBotInput" class="rule-bot-input" placeholder="質問を入力 (例: 《ヘブンズ・ゲート》で...)">
            <button id="ruleBotSendBtn" class="rule-bot-send" onclick="sendRuleBotMessage()">送信</button>
        </div>
    </div>

    <script>
        function toggleRuleBot() {
            const win = document.getElementById('ruleBotWindow');
            win.classList.toggle('active');
            if (win.classList.contains('active')) {
                document.getElementById('ruleBotInput').focus();
            }
        }

        async function sendRuleBotMessage() {
            const input = document.getElementById('ruleBotInput');
            const sendBtn = document.getElementById('ruleBotSendBtn');
            const container = document.getElementById('ruleBotMessages');
            const text = input.value.trim();
            if (!text) return;

            // サジェストを閉じる
            document.getElementById('ruleBotSuggestList').style.display = 'none';

            // ユーザーメッセージ描画
            const userMsg = document.createElement('div');
            userMsg.className = 'rule-bot-msg user';
            userMsg.textContent = text;
            container.appendChild(userMsg);
            input.value = '';
            input.disabled = true;
            sendBtn.disabled = true;

            // ローディング表示
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
                    loadingMsg.textContent = data.error || 'エラーが発生しました。(Status: ' + res.status + ')';
                } else {
                    // ★ marked.parse を使ってマークダウン記号を綺麗なHTMLに変換
                    loadingMsg.innerHTML = marked.parse(data.reply || '回答を取得できませんでした。');
                }
            } catch (err) {
                loadingMsg.textContent = '通信エラーが発生しました: ' + err.message;
            } finally {
                input.disabled = false;
                sendBtn.disabled = false;
                input.focus();
                container.scrollTop = container.scrollHeight;
            }
        }

        // Enterキー送信対応
        document.getElementById('ruleBotInput').addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                sendRuleBotMessage();
            }
        });

        // ==========================================
        // 《》カード名オートコンプリート（重複完全排除対応）
        // ==========================================
        const botInput = document.getElementById('ruleBotInput');
        const suggestList = document.getElementById('ruleBotSuggestList');
        let suggestTimer = null;

        botInput.addEventListener('input', function() {
            clearTimeout(suggestTimer);
            const val = this.value;
            const cursorPos = this.selectionStart;
            
            // カーソル直前の「《」を検索（「》」で閉じられていない部分）
            const textBeforeCursor = val.slice(0, cursorPos);
            const match = textBeforeCursor.match(/《([^》\n]*)$/);

            if (!match) {
                suggestList.style.display = 'none';
                return;
            }

            const query = match[1].trim();
            if (query.length === 0) {
                suggestList.style.display = 'none';
                return;
            }

            // 入力後250ms待ってからカード検索APIを呼び出し
            suggestTimer = setTimeout(async () => {
                try {
                    const res = await fetch(`/api/cards?q=${encodeURIComponent(query)}`);
                    const cards = await res.json();
                    
                    if (!cards || cards.length === 0) {
                        suggestList.style.display = 'none';
                        return;
                    }

                    suggestList.innerHTML = '';
                    const uniqueNames = new Set();
                    let count = 0;

                    // ★ 重複（同名カードの別バージョン等）を完全に排除して最大8件追加
                    for (const card of cards) {
                        if (!card.card_name || uniqueNames.has(card.card_name)) {
                            continue;
                        }
                        uniqueNames.add(card.card_name);

                        const item = document.createElement('div');
                        item.className = 'rule-bot-suggest-item';
                        item.textContent = card.card_name;
                        item.onclick = () => selectCardName(card.card_name, match.index, cursorPos);
                        suggestList.appendChild(item);

                        count++;
                        if (count >= 8) break; // 上位8件まで表示
                    }

                    suggestList.style.display = count > 0 ? 'block' : 'none';
                } catch (e) {
                    suggestList.style.display = 'none';
                }
            }, 250);
        });

        // 候補クリック時のカード名挿入処理
        function selectCardName(cardName, startIndex, endIndex) {
            const val = botInput.value;
            const before = val.slice(0, startIndex);
            const after = val.slice(endIndex);
            botInput.value = before + '《' + cardName + '》' + after;
            
            suggestList.style.display = 'none';
            botInput.focus();
            
            // カーソルを「》」の後ろへ自動移動
            const newCursor = (before + '《' + cardName + '》').length;
            botInput.setSelectionRange(newCursor, newCursor);
        }

        // 外側クリックでサジェストを閉じる
        document.addEventListener('click', function(e) {
            if (!botInput.contains(e.target) && !suggestList.contains(e.target)) {
                suggestList.style.display = 'none';
            }
        });
    </script>
</body>
</html>