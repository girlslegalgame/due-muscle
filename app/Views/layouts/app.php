<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>デッキメーカー</title>
    <link rel="stylesheet" href="/css/style.css?v=<?php echo time(); ?>">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script> 
</head>
<body>
    <header>
        <div class="header-left">
            <h1><a href="/mydecks">DECK MAKER</a></h1>
        </div>
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
</body>
</html>