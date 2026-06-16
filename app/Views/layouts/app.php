<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>デッキメーカー</title>
    <link rel="stylesheet" href="/css/style.css">
    
    <style>
        /* 
         * ヘッダーとフッターを固定し、中間にあるメインコンテンツが
         * 上下にはみ出して隠れないように、上（55px）と下（60px）に余白を設定します。
         */
        main {
            padding-top: 55px !important;    /* ★追加：ヘッダーの高さ分の余白 */
            padding-bottom: 60px !important; /* フッターの高さ分の余白 */
            box-sizing: border-box;
        }

        /* ★ヘッダーを最上部に固定するスタイル */
        header {
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            width: 100% !important;
            z-index: 2000 !important; /* 最前面に表示 */
            background-color: #fff;  /* コンテンツが後ろを通り抜けるため、透過を防ぐ背景色 */
            border-bottom: 1px solid #ddd;
            padding: 10px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-sizing: border-box;
            height: 50px;             /* 高さを一貫させるために固定 */
        }

        /* ★「アカウント」リンク（未訪問・訪問済み）の色を固定 */
        header nav a,
        header nav a:link,
        header nav a:visited {
            color: #333 !important;          /* 訪問済みであっても常に #333（黒系）に固定 */
            text-decoration: none !important;
        }
        header nav a:hover {
            color: #007bff !important;       /* ホバーした時だけ青色に変化 */
        }

        header h1 {
            margin: 0;
            font-size: 1.5rem;
        }

        header h1 a,
        header h1 a:visited {
            text-decoration: none;
            color: #333 !important;          /* タイトルリンクも訪問済みで色を変えさせない */
        }

        /* フッター固定配置 */
        footer {
            position: fixed !important;
            bottom: 0 !important;
            left: 0 !important;
            width: 100% !important;
            z-index: 2000 !important;
        }
    </style>
</head>
<body>
    <header>
        <h1><a href="/mydecks">DECK MAKER</a></h1>
        <nav><a href="/account">アカウント</a></nav>
    </header>
    <main>
        <?php echo $content; ?>
    </main>
    <?php if (!isset($hideFooter) || !$hideFooter): ?>
    <footer>
        <nav>
            <ul>
                <li><a href="/mydecks">トップ</a></li>
                <li><a href="/help/search">ヘルプ</a></li>
                <li><a href="/search">デッキ検索</a></li>
                <li><a href="/decks/new">デッキ作成</a></li>
            </ul>
        </nav>
    </footer>
    <?php endif; ?>
    <script src="/js/main.js"></script>
</body>
</html>