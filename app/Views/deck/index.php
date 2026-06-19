<!-- app/Views/deck/index.php -->
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>マイデッキ一覧</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* --- 基本レイアウト --- */
        body {
            background-color: #f0f0f0;
            font-family: sans-serif;
            margin: 0; padding: 0;
            height: 100vh;          /* ★画面全体の高さを100vhに固定 */
            max-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .container {
            max-width: 1000px;
            width: 100%;
            margin: 0 auto;
            padding: 20px;
            box-sizing: border-box;
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 0;          /* ★flex内の子要素が縮めるようにする設定 */
        }
        .container h2 {
            margin-top: 0;
            flex-shrink: 0;         /* ★タイトルが潰れないように固定 */
        }
        .create-btn {
            display: inline-block;
            padding: 12px 24px;
            background-color: #007bff;
            color: #fff;
            text-decoration: none;
            border-radius: 5px;
            margin-bottom: 20px;
            font-weight: bold;
            flex-shrink: 0;         /* ★ボタンが潰れないように固定 */
            align-self: flex-start;
        }

        /* --- デッキリスト：スマホ1列 / PC3列 --- */
        .deck-list {
            display: grid;
            grid-template-columns: 1fr;
            gap: 20px;
            flex: 1;                /* ★残りの高さをすべて埋める */
            overflow-y: auto;       /* ★デッキが多い場合はこの中だけでスクロールさせる */
            padding-right: 5px;
            align-content: start;   /* ★上詰めで配置 */
        }
        @media (min-width: 768px) {
            .deck-list {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        .deck-item {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            display: flex;
            flex-direction: column;  /* 縦並びに固定 */
            gap: 12px;
        }
        .deck-item h3 { 
            margin: 0; 
            font-size: 1.1rem; 
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis; /* 長いデッキ名を「...」で省略 */
        }
        /* サムネイルを囲う枠（トリミングの基準領域） */
        .deck-thumbnail-wrapper {
            width: 100%;
            height: 120px;           /* トリミング枠の高さ */
            border-radius: 6px;
            overflow: hidden;
            border: 1px solid #eee;
            background-color: #f9f9f9;
        }
        /* 画像を拡大し、カード上部のイラスト付近を中心に切り抜く */
        .deck-thumbnail {
            width: 100%;
            height: 100%;
            object-fit: cover;       /* アスペクト比を維持したまま拡大・トリミング */
            object-position: center 25%; /* イラストが位置するカード上部から25%付近を中心に配置 */
        }
        .deck-meta-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.85rem;
            color: #666;
        }
        
        .format-badge {
            font-size: 0.8rem;
            background: #eee;
            padding: 2px 6px;
            border-radius: 4px;
        }
        
        .btn-group { display: flex; gap: 5px; margin-top: 10px; }
        .btn-view { flex: 2; padding: 10px; background: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; }
        .btn-edit { flex: 1; padding: 10px; background: #ffc107; color: #212529; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; text-decoration: none; text-align: center; font-size: 0.9rem; }
        .btn-delete { padding: 10px; background: #dc3545; color: white; border: none; border-radius: 4px; cursor: pointer; }
        </style>
</head>
<body>

<div class="container">
    <h2>マイデッキ一覧</h2>
    <a href="/decks/new" class="create-btn">＋ 新規作成</a>

    <div class="deck-list">
        <?php if (!empty($decks)): ?>
            <?php foreach ($decks as $deck): ?>
                <div class="deck-item">
                    <!-- 1. デッキ名 -->
                    <h3><?php echo htmlspecialchars($deck['deck_name']); ?></h3>

                    <!-- 2. サムネイル画像（拡大トリミング） -->
                    <div class="deck-thumbnail-wrapper">
                        <?php 
                            $thumbPath = '/images/card/noimage.webp';
                            if (!empty($deck['thumbnail_imagepath'])) {
                                $path = $deck['thumbnail_imagepath'];
                                $thumbPath = '/images/card' . (str_starts_with($path, '/') ? $path : '/' . $path);
                            }
                        ?>
                        <img src="<?php echo htmlspecialchars($thumbPath); ?>" alt="Thumbnail" class="deck-thumbnail" onerror="this.src='/images/card/noimage.webp'; this.onerror=null;">
                    </div>

                    <!-- 3. フォーマット 最終更新日 -->
                    <div class="deck-meta-info">
                        <span class="format-badge"><?php echo htmlspecialchars($deck['format_name']); ?></span>
                        <span><?php echo date('Y/m/d', strtotime($deck['updated_at'])); ?></span>
                    </div>

                    <!-- 4. ボタン群 -->
                    <div class="btn-group" style="margin-top: auto; padding-top: 5px;">
                        <button class="btn-view" onclick="openDeckModal(<?php echo $deck['deck_id']; ?>, '<?php echo htmlspecialchars($deck['deck_name'], ENT_QUOTES); ?>')">内容表示</button>
                        <a href="/decks/edit?deck_id=<?php echo $deck['deck_id']; ?>" class="btn-edit">編集</a>
                        <button class="btn-delete" onclick="deleteDeck(<?php echo $deck['deck_id']; ?>)">✕</button>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p style="grid-column: 1 / -1; text-align: center; color: #666;">デッキが登録されていません。</p>
        <?php endif; ?>
    </div>
</div>

<!-- デッキ詳細モーダル（共通）の読み込み -->
<?php include __DIR__ . '/deck_detail_modal.php'; ?>
<!-- 共通カード詳細モーダルの読み込み -->
<?php include __DIR__ . '/card_detail_modal.php'; ?>

<script>
/**
 * デッキ削除
 */
function deleteDeck(deckId) {
    if (!confirm('本当にこのデッキを削除してもよろしいですか？')) return;

    fetch('/api/decks?deck_id=' + deckId, { method: 'DELETE' })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert('削除しました');
            location.reload();
        } else {
            alert('削除エラー: ' + data.error);
        }
    })
    .catch(() => alert('通信エラーが発生しました'));
}
</script>

</body>
</html>