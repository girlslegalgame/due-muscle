<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="google-adsense-account" content="ca-pub-1782055298829063">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>デュエ筋</title>
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

        /* ロゴ画像のサイズ調整（必要に応じて調整してください） */
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
            /* ハンバーガーボタン（三本線）の表示（スマホ時は白） */
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
                background-color: #fff; /* スマホ時は白に */
                transition: all 0.3s ease;
            }

            /* 三本線から「×」へのアニメーション変形 */
            .menu-toggle.active span:nth-child(1) {
                transform: translateY(8px) rotate(45deg);
            }
            .menu-toggle.active span:nth-child(2) {
                opacity: 0;
            }
            .menu-toggle.active span:nth-child(3) {
                transform: translateY(-8px) rotate(-45deg);
            }

            /* スマホ時のメニュー展開エリアの設定（スマホ時は黒ベースまたは白ベース：ここでは統一して黒背景） */
            .header-right {
                display: none !important;
                position: fixed;
                top: 60px;
                left: 0;
                width: 100%;
                background-color: #000; /* スマホ展開時も黒背景に */
                border-bottom: 1px solid #333;
                flex-direction: column;
                align-items: center;
                gap: 0 !important;
                padding: 0;
                box-shadow: 0 4px 10px rgba(0, 0, 0, 0.5);
                z-index: 1999;
            }

            /* トグル展開時の表示クラス */
            .header-right.active {
                display: flex !important;
            }

            /* メニュー内のリンク要素をブロック化 */
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
    </style>
</head>
<body>
    <header>
        <div class="header-left">
            <!-- ロゴ画像を配置し、トップ画面（/）へリンク -->
            <h1>
                <a href="/">
                    <img src="/images/logo.webp" alt="DECK MAKER" class="header-logo">
                </a>
            </h1>
        </div>
        
        <!-- スマホ用ハンバーガーメニューボタン -->
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
            <!-- ★修正: ログイン状態に応じてリンク先を動的に変更 -->
            <a href="<?php echo isset($_SESSION['user_id']) ? '/account' : '/login'; ?>">アカウント</a>
            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                <a href="/admin" style="color: #ffc107 !important; font-weight: bold;">管理者画面</a>
            <?php endif; ?>
        </div>
    </header>

    <main>
        <?php echo $content ?? ''; ?>
    </main>

    <!-- ★修正: hideFooter が真の場合はフッターを表示しない -->
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
        // ==========================================================
        // ログイン完了直後の自動保存スクリプト（完全二重実行防止ロック付き）
        // ==========================================================
        (function() {
            // このスクリプトブロック自体が同一ページ内で2回読み込まれても、
            // ウィンドウ全体で一度だけ実行されるようにロックフラグを立てます
            if (window._deckAutoSaveExecuted) return;
            window._deckAutoSaveExecuted = true;

            window.addEventListener('DOMContentLoaded', () => {
                const currentPath = window.location.pathname;
                const authPaths = ['/login', '/register', '/login/verify', '/register/verify', '/logout'];
                if (authPaths.includes(currentPath)) {
                    return; 
                }

                // 保存待ちフラグがあるかチェック
                if (localStorage.getItem('pending_deck_save') === 'true') {
                    const savedPayloadStr = localStorage.getItem('pending_deck_payload');
                    
                    // 即座にフラグを消去して重複を防ぐ
                    localStorage.removeItem('pending_deck_save');
                    localStorage.removeItem('pending_deck_payload');
                    localStorage.removeItem('unsaved_deck_draft');

                    if (savedPayloadStr) {
                        try {
                            const payload = JSON.parse(savedPayloadStr);
                            console.log("ログイン完了後の自動保存を実行します（二重ガード適用）...");

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

        /* ハンバーガーメニューの開閉処理 */
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
</body>
</html>