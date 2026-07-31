<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>デッキメーカー</title>
    <link rel="stylesheet" href="/css/style.css?v=<?php echo time(); ?>">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script> 
    
    <style>
        /* --- ハンバーガーメニュー用のデフォルト非表示設定 --- */
        .menu-toggle {
            display: none;
        }

        /* --- スマートフォン環境（レスポンシブ） --- */
        @media (max-width: 768px) {
            /* ハンバーガーボタン（三本線）の表示 */
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
                background-color: #333;
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

            /* スマホ時のメニュー展開エリアの設定（縦並びのドロップダウン形式） */
            .header-right {
                display: none !important; /* 通常時は非表示（!importantでstyle.cssのflexを上書き） */
                position: fixed;
                top: 60px; /* ヘッダーの高さに合わせる */
                left: 0;
                width: 100%;
                background-color: #fff;
                border-bottom: 1px solid #ddd;
                flex-direction: column;
                align-items: center;
                gap: 0 !important;
                padding: 0;
                box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
                z-index: 1999;
            }

            /* トグル展開時の表示クラス */
            .header-right.active {
                display: flex !important;
            }

            /* メニュー内のリンク要素をブロック化してタップ領域を拡張 */
            .header-right a {
                display: block;
                width: 100%;
                text-align: center;
                padding: 16px 0;
                border-bottom: 1px solid #f2f2f7;
                box-sizing: border-box;
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
            <h1><a href="/mydecks">DECK MAKER</a></h1>
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
            <a href="/decks/new">デッキ作成</a>
            <a href="/help">ヘルプ</a>
            <a href="/account">アカウント</a>
        </div>
    </header>

    <main>
        <?php echo $content ?? ''; ?>
    </main>

    <script src="/js/main.js"></script>
    
    <script>
        // スマホ用ナビゲーションメニューのトグル開閉制御
        function toggleMobileMenu() {
            const toggleBtn = document.querySelector('.menu-toggle');
            const navMenu = document.querySelector('.header-right');
            toggleBtn.classList.toggle('active');
            navMenu.classList.toggle('active');
        }

        // リンクをクリックした際にメニューを自動で閉じる処理
        document.querySelectorAll('.header-right a').forEach(link => {
            link.addEventListener('click', () => {
                const toggleBtn = document.querySelector('.menu-toggle');
                const navMenu = document.querySelector('.header-right');
                if (toggleBtn.classList.contains('active')) {
                    toggleBtn.classList.remove('active');
                    navMenu.classList.remove('active');
                }
            });
        });
    </script>
</body>
</html>