package layouts

import (
	"html/template"
	"io"
	"net/http"
	"strconv"
	"time"
)

// View引数用データ構造体
type AppLayoutData struct {
	Content    template.HTML
	HideFooter bool
}

// セッション情報などのコンテキスト（プロジェクトの実装に合わせて調整可能）
type SessionData struct {
	UserID string
	Role   string
}

// 仮の未読通知数取得関数（既存のビジネスロジックと連携させてください）
var GetUnreadNotificationCount = func(r *http.Request) int {
	return 0
}

// テンプレート内で使用するヘルパー関数
var tmplFuncs = template.FuncMap{
	"time": func() int64 {
		return time.Now().Unix()
	},
	"year": func() int {
		return time.Now().Year()
	},
	"badgeText": func(count int) string {
		if count > 9 {
			return "9+"
		}
		return strconv.Itoa(count)
	},
}

// テンプレート定義
var appTemplate = template.Must(template.New("app.html").Funcs(tmplFuncs).Parse(appHTML))

// レイアウトレンダリング関数
func RenderApp(w io.Writer, r *http.Request, session *SessionData, data AppLayoutData) error {
	unreadCnt := GetUnreadNotificationCount(r)

	viewData := struct {
		AppLayoutData
		Session   *SessionData
		UnreadCnt int
	}{
		AppLayoutData: data,
		Session:       session,
		UnreadCnt:     unreadCnt,
	}

	return appTemplate.Execute(w, viewData)
}

const appHTML = `<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="google-adsense-account" content="ca-pub-1782055298829063">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>デュエ筋</title>
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <link rel="stylesheet" href="/css/style.css?v={{ time }}">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script> 
    <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-1782055298829063"
     crossorigin="anonymous"></script>
    <script src="/js/rule-bot-chat.js?v={{ time }}"></script>
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

        body {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            margin: 0;
        }

        .side-ad-wrapper {
            flex: 1;
            display: flex;
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
                    <img src="/images/logo.webp" alt="デュエ筋" class="header-logo">
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
            <a href="javascript:void(0)" onclick="handleRuleBotClick()" style="cursor: pointer;">AIジャッジ</a>
            <a href="/help">ヘルプ</a>           
            <a href="/notifications">
                お知らせ
                {{ if gt .UnreadCnt 0 }}
                    <span style="background-color: #ef4444; color: #fff; font-size: 0.7rem; font-weight: bold; border-radius: 10px; padding: 1px 6px; margin-left: 4px; line-height: 1.2;">
                        {{ badgeText .UnreadCnt }}
                    </span>
                {{ end }}
            </a>
            <a href="{{ if and .Session .Session.UserID }}/account{{ else }}/login{{ end }}">アカウント</a>
            {{ if and .Session (eq .Session.Role "admin") }}
                <a href="/admin" style="color: #ffc107 !important; font-weight: bold;">管理者画面</a>
            {{ end }}
        </div>
    </header>

    <!-- 左右両端の追従広告（PC専用） -->
    <div class="side-ad-wrapper">
        <aside class="side-ad side-ad-left">
            <ins class="adsbygoogle"
                 style="display:inline-block; width:160px; height:600px;"
                 data-ad-client="ca-pub-1782055298829063"
                 data-ad-slot="LEFT_AD_SLOT_ID"></ins>
            <script>
                (function() {
                    var el = document.currentScript.previousElementSibling;
                    if (el && el.parentElement && el.parentElement.offsetWidth > 0) {
                        (adsbygoogle = window.adsbygoogle || []).push({});
                    }
                })();
            </script>
        </aside>

        <main>
            {{ .Content }}
        </main>

        <aside class="side-ad side-ad-right">
            <ins class="adsbygoogle"
                 style="display:inline-block; width:160px; height:600px;"
                 data-ad-client="ca-pub-1782055298829063"
                 data-ad-slot="LEFT_AD_SLOT_ID"></ins>
            <script>
                (function() {
                    var el = document.currentScript.previousElementSibling;
                    if (el && el.parentElement && el.parentElement.offsetWidth > 0) {
                        (adsbygoogle = window.adsbygoogle || []).push({});
                    }
                })();
            </script>
        </aside>
    </div>

    {{ if not .HideFooter }}
    <footer>
        <ul>
            <li><a href="/terms">利用規約</a></li>
            <li><a href="/profile">運営者情報</a></li>
            <li><a href="/contact">お問い合わせ</a></li>
        </ul>
        <div style="font-size: 0.8rem; color: #888; margin-top: 10px;">
            &copy; {{ year }} デュエ筋 All rights reserved.
        </div>
    </footer>
    {{ end }}

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
                            {{ if and .Session .Session.UserID }}
                                <label style="display: flex; align-items: center; gap: 6px; font-size: 0.85rem; color: #555; cursor: pointer;">
                                    <input type="checkbox" id="deck-type-remember" style="width: 16px; height: 16px;">
                                    次回からこの画面を表示しない
                                </label>
                                <p style="font-size: 0.75rem; color: #888; margin: 6px 0 0 22px; line-height: 1.3;">
                                    ※この設定は後から「アカウント設定」画面で変更・リセットできます。
                                </p>
                            {{ else }}
                                <p style="font-size: 0.8rem; color: #666; margin: 0; line-height: 1.4; text-align: center;">
                                    <a href="/login" style="color: #007bff !important; text-decoration: underline !important; font-weight: bold;">ログイン</a>すると、次回以降この選択をスキップしてデフォルトで開く画面を設定できます。
                                </p>
                            {{ end }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- ★ ルールAI起動方法 選択モーダル -->
            <div id="ruleBotChoiceModal" class="sub-modal" style="display: none; position: fixed; z-index: 3600; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); align-items: center; justify-content: center;">
                <div style="background: #fff; width: 380px; max-width: 90%; border-radius: 12px; padding: 24px; box-shadow: 0 8px 30px rgba(0,0,0,0.3); box-sizing: border-box; text-align: center;">
                    <h3 style="margin-top: 0; font-size: 1.15rem; color: #222;">ルールAIの開き方を選択</h3>
                    <p style="font-size: 0.85rem; color: #666; margin-bottom: 20px;">チャットの表示形式を選択してください。</p>

                    <div style="display: flex; flex-direction: column; gap: 10px; margin-bottom: 16px;">
                        <button type="button" onclick="executeRuleBotChoice('popup')" style="padding: 12px; background: #007bff; color: #fff; border: none; border-radius: 6px; font-weight: bold; font-size: 0.95rem; cursor: pointer;">
                            右下の小窓で開く
                        </button>
                        <button type="button" onclick="executeRuleBotChoice('fullscreen')" style="padding: 12px; background: #28a745; color: #fff; border: none; border-radius: 6px; font-weight: bold; font-size: 0.95rem; cursor: pointer;">
                            全画面（専用ページ）で開く
                        </button>
                    </div>

                    <!-- ★ ログイン状態による切り替え -->
                    <div style="margin-bottom: 16px; font-size: 0.82rem; color: #555;">
                        {{ if and .Session .Session.UserID }}
                            <label style="display: flex; align-items: center; justify-content: center; gap: 6px; cursor: pointer;">
                                <input type="checkbox" id="dontShowRuleBotModal">
                                次回以降この確認を表示しない
                            </label>
                        {{ else }}
                            <p style="margin: 0; line-height: 1.4; color: #666; font-size: 0.8rem;">
                                ※<a href="/login" style="color: #007bff !important; text-decoration: underline !important; font-weight: bold;">ログイン</a>すると、次回以降この確認をスキップして固定表示するように設定できます。
                            </p>
                        {{ end }}
                    </div>

                    <button type="button" onclick="closeRuleBotChoiceModal()" style="background: none; border: none; color: #888; font-size: 0.85rem; cursor: pointer; text-decoration: underline;">
                        キャンセル
                    </button>
                </div>
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


    <div class="rule-bot-window" id="ruleBotWindow">
        <div class="rule-bot-header">
            <span>デュエマ ルール相談AI</span>
            <div style="display: flex; gap: 10px; align-items: center;">
                <!-- ★ 専用画面への切り替えリンク -->
                <a href="/rule-bot" style="color: #bbb; text-decoration: none; font-size: 0.8rem;" title="専用の大画面で開く">全画面 ↗</a>
                <button class="rule-bot-close" onclick="toggleRuleBot()">✕</button>
            </div>
        </div>
        
        <!-- ★ ここを追加: メッセージ表示コンテナ -->
        <div class="rule-bot-messages" id="ruleBotMessages">
            <div class="rule-bot-msg bot">こんにちは！公式総合ルール全条文を記憶したAIジャッジです。<br>効果の処理順やルールの疑問があれば質問してください。カード名は《》で囲むと自動補完されます。<br><span style="font-size:0.75rem; color:#888;">※5回の質問ごとに広告の視聴が必要です。</span></div>
        </div>

        <!-- サジェストリスト付き入力エリア -->
        <div class="rule-bot-input-area" style="padding: 8px; border-top: 1px solid #eee; display: flex; align-items: flex-end; gap: 6px;">
            <div id="ruleBotSuggestList" class="rule-bot-suggest-list"></div>
            <textarea id="ruleBotInput" class="rule-bot-input" style="height: 40px; min-height: 40px; max-height: 120px; font-size: 0.88rem; padding: 8px 10px; resize: none; box-sizing: border-box; margin: 0; overflow-y: auto;" placeholder="質問を入力"></textarea>
            <button id="ruleBotSendBtn" class="rule-bot-send" style="height: 40px; padding: 0 12px; font-size: 0.8rem; white-space: nowrap; flex-shrink: 0; box-sizing: border-box; margin: 0; align-self: flex-end;">送信</button>
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

        // PC判定ならボタン文字変更
        if (window.matchMedia('(hover: hover) and (pointer: fine)').matches) {
            const botBtn = document.getElementById('ruleBotSendBtn');
            if (botBtn) botBtn.textContent = '送信 Ctrl+Enter';
        }

        // ==========================================
        // 《》カード名オートコンプリート（重複完全排除対応）
        // ==========================================
        const botInput = document.getElementById('ruleBotInput');
        const suggestList = document.getElementById('ruleBotSuggestList');
        let suggestTimer = null;
        document.addEventListener('DOMContentLoaded', () => {
            new RuleBotChat({
                inputId: 'ruleBotInput',
                sendBtnId: 'ruleBotSendBtn',
                messagesId: 'ruleBotMessages',
                suggestListId: 'ruleBotSuggestList',
                minHeight: 40,
                maxHeight: 120
            });
        });
        // ルールAIクリック時の振り分け処理
        function handleRuleBotClick() {
            // ★ 未ログイン時は保存設定を無視して必ず毎回モーダルを表示
            const isLoggedIn = {{ if and .Session .Session.UserID }}true{{ else }}false{{ end }};
            const savedMode = isLoggedIn ? localStorage.getItem('default_rule_bot_mode') : null;
            
            if (savedMode === 'popup') {
                toggleRuleBot();
            } else if (savedMode === 'fullscreen') {
                window.location.href = '/rule-bot';
            } else {
                const modal = document.getElementById('ruleBotChoiceModal');
                const checkbox = document.getElementById('dontShowRuleBotModal');
                if (checkbox) checkbox.checked = false;
                
                if (modal) {
                    modal.style.setProperty('display', 'flex', 'important');
                }
            }
        }

        // 選択実行処理
        function executeRuleBotChoice(mode) {
            const checkbox = document.getElementById('dontShowRuleBotModal');
            if (checkbox && checkbox.checked) {
                localStorage.setItem('default_rule_bot_mode', mode);
            }
            closeRuleBotChoiceModal();

            if (mode === 'popup') {
                toggleRuleBot();
            } else if (mode === 'fullscreen') {
                window.location.href = '/rule-bot';
            }
        }

        function closeRuleBotChoiceModal() {
            const modal = document.getElementById('ruleBotChoiceModal');
            if (modal) {
                modal.style.setProperty('display', 'none', 'important');
            }
        }
        const botInputArea = document.getElementById('ruleBotInput');
        botInputArea.addEventListener('input', function() {
            this.style.height = 'auto'; // 一旦リセットして正確なscrollHeightを取得
            const newHeight = Math.min(Math.max(this.scrollHeight, 40), 120);
            this.style.height = newHeight + 'px';
        });
    </script>
</body>
</html>`
