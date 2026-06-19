<!-- app/Views/deck/search.php -->
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>公開デッキ検索</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* --- 基本レイアウト --- */
        body {
            background-color: #f0f0f0;
            font-family: sans-serif;
            margin: 0; padding: 0;
            height: 100vh;
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
            min-height: 0;
        }
        .container h2 {
            margin-top: 0;
            margin-bottom: 15px;
            flex-shrink: 0;
        }

        /* --- 検索フォーム --- */
        .search-form {
            background: #fff;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid #ddd;
            margin-bottom: 20px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            align-items: end;
            flex-shrink: 0;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .search-form label {
            font-size: 0.8rem;
            font-weight: bold;
            display: block;
            margin-bottom: 5px;
            color: #555;
        }
        .search-form input[type="text"],
        .search-form select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
            font-size: 13px;
        }

        /* --- デッキリスト：スマホ1列 / PC3列 --- */
        .deck-list {
            display: grid;
            grid-template-columns: 1fr;
            gap: 20px;
            flex: 1;
            overflow-y: auto;
            padding-right: 5px;
            align-content: start;
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
            flex-direction: column;
            gap: 12px;
        }
        .deck-item h3 { 
            margin: 0; 
            font-size: 1.1rem; 
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        /* サムネイルの拡大トリミング領域 */
        .deck-thumbnail-wrapper {
            width: 100%;
            height: 120px;
            border-radius: 6px;
            overflow: hidden;
            border: 1px solid #eee;
            background-color: #f9f9f9;
        }
        .deck-thumbnail {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center 25%;
            cursor: pointer !important;
        }
        .deck-creator {
            font-size: 0.85rem;
            color: #555;
            font-weight: bold;
            margin-top: -4px;
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
        
        .btn-group { display: flex; gap: 8px; margin-top: 10px; }
        .btn-view { flex: 2; padding: 10px; background: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; }
        .btn-edit { flex: 1; padding: 10px; background: #ffc107; color: #212529; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; text-decoration: none; text-align: center; font-size: 0.9rem; }
    </style>
</head>
<body>

<div class="container">
    <h2>公開デッキ検索</h2>

    <!-- 検索フォーム -->
    <form method="GET" action="/search" class="search-form">
        <div>
            <label>デッキ名</label>
            <input type="text" name="deck_name" value="<?php echo htmlspecialchars($searchValues['deck_name'] ?? ''); ?>" placeholder="キーワードを入力...">
        </div>
        <div>
            <label>採用カード名</label>
            <input type="text" name="card_name" value="<?php echo htmlspecialchars($searchValues['card_name'] ?? ''); ?>" placeholder="採用されているカード...">
        </div>
        <div>
            <label>フォーマット</label>
            <select name="format_id">
                <option value="">すべて</option>
                <?php foreach ($formats as $f): ?>
                    <option value="<?php echo $f['format_id']; ?>" <?php echo (isset($searchValues['format_id']) && $f['format_id'] == $searchValues['format_id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($f['format_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
<!-- app/Views/deck/search.php 内の文明選択部分の修正 -->

        <!-- 文明選択（チェックボックスによる複数選択） -->
        <div style="grid-column: span 2;">
            <label style="font-size: 0.8rem; font-weight: bold; display: block; margin-bottom: 5px; color: #555;">採用カードの文明 (複数選択可)</label>
            <div style="display: flex; flex-direction: column; gap: 8px; background: #fafafa; border: 1px solid #ccc; border-radius: 4px; padding: 10px 12px; box-sizing: border-box;">
                
                <!-- チェックボックス一覧 -->
                <div style="display: flex; flex-wrap: wrap; gap: 15px; align-items: center;">
                    <?php foreach ($civilizations as $c): ?>
                        <label style="font-size: 0.85rem; font-weight: normal; display: flex; align-items: center; gap: 5px; cursor: pointer; margin: 0;">
                            <input type="checkbox" name="civ_ids[]" value="<?php echo $c['civilization_id']; ?>" 
                                <?php echo (isset($searchValues['civ_ids']) && in_array($c['civilization_id'], $searchValues['civ_ids'])) ? 'checked' : ''; ?>>
                            <?php echo htmlspecialchars($c['civilization_name']); ?>
                        </label>
                    <?php endforeach; ?>
                </div>

                <div style="border-top: 1px dashed #ddd; margin: 5px 0;"></div>

                <!-- ★「含む」と「のみ」の条件選択ラジオボタンを追加 -->
                <div style="display: flex; gap: 20px; align-items: center;">
                    <label style="font-size: 0.85rem; font-weight: normal; display: flex; align-items: center; gap: 5px; cursor: pointer; margin: 0; color: #333;">
                        <input type="radio" name="civ_logic" value="include" <?php echo ($searchValues['civ_logic'] ?? 'include') === 'include' ? 'checked' : ''; ?>>
                        選択した文明を含む（混色可）
                    </label>
                    <label style="font-size: 0.85rem; font-weight: normal; display: flex; align-items: center; gap: 5px; cursor: pointer; margin: 0; color: #333;">
                        <input type="radio" name="civ_logic" value="only" <?php echo ($searchValues['civ_logic'] ?? 'include') === 'only' ? 'checked' : ''; ?>>
                        選択した文明のみ（それ以外を排除）
                    </label>
                </div>

            </div>
        </div>
        
        <div style="grid-column: 1 / -1; display:flex; gap:10px; justify-content:flex-end;">
            <a href="/search" style="padding:10px 20px; background:#ccc; color:#333; text-decoration:none; border-radius:4px; font-weight:bold; font-size:13px; text-align:center;">クリア</a>
            <button type="submit" style="padding:10px 20px; background:#007bff; color:white; border:none; border-radius:4px; cursor:pointer; font-weight:bold; font-size:13px;">検索する</button>
        </div>
    </form>

    <!-- 検索結果一覧 -->
    <div class="deck-list">
        <?php if (!empty($decks)): ?>
            <?php foreach ($decks as $deck): ?>
                <div class="deck-item">
                    <!-- 1. デッキ名 -->
                    <h3><?php echo htmlspecialchars($deck['deck_name']); ?></h3>

                    <!-- 2. サムネイル画像 -->
                    <div class="deck-thumbnail-wrapper">
                        <?php 
                            $thumbPath = '/images/card/noimage.webp';
                            if (!empty($deck['thumbnail_imagepath'])) {
                                $path = $deck['thumbnail_imagepath'];
                                $thumbPath = '/images/card' . (str_starts_with($path, '/') ? $path : '/' . $path);
                            }
                        ?>
                        <img src="<?php echo htmlspecialchars($thumbPath); ?>" alt="Thumbnail" class="deck-thumbnail" onclick="openDeckModal(<?php echo $deck['deck_id']; ?>, '<?php echo htmlspecialchars($deck['deck_name'], ENT_QUOTES); ?>')" onerror="this.src='/images/card/noimage.webp'; this.onerror=null;">
                    </div>

                    <!-- 3. 製作者名（サムネの下） -->
                    <div class="deck-creator">
                        製作者: <?php echo htmlspecialchars($deck['creator_name']); ?>
                    </div>

                    <!-- 4. フォーマット 最終更新日 -->
                    <div class="deck-meta-info">
                        <span class="format-badge"><?php echo htmlspecialchars($deck['format_name']); ?></span>
                        <span><?php echo date('Y/m/d', strtotime($deck['updated_at'])); ?></span>
                    </div>

                    <!-- 5. ボタン群 -->
                    <div class="btn-group" style="margin-top: auto; padding-top: 5px;">
                        <button class="btn-view" onclick="openDeckModal(<?php echo $deck['deck_id']; ?>, '<?php echo htmlspecialchars($deck['deck_name'], ENT_QUOTES); ?>')">内容表示</button>
                        <!-- コピーボタン -->
                        <button class="btn-edit" onclick="copyDeck(<?php echo $deck['deck_id']; ?>)" style="background-color: #ffc107; color: #212529;">コピー</button>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p style="grid-column: 1 / -1; text-align: center; color: #666;">該当する公開デッキが見つかりませんでした。</p>
        <?php endif; ?>
    </div>
</div>

<!-- デッキ詳細モーダル（共通）の読み込み -->
<?php include __DIR__ . '/deck_detail_modal.php'; ?>

<!-- 共通カード詳細モーダルの読み込み -->
<?php include __DIR__ . '/card_detail_modal.php'; ?>

<script>

/**
 * デッキのコピー（新しく自分のものとして登録）
 */
function copyDeck(deckId) {
    if (!confirm("このデッキをコピーしてマイデッキに登録しますか？")) return;

    fetch('/api/decks/copy', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ deck_id: deckId })
    })
    // 500などのエラー時でも、サーバー側が返したエラー原因テキスト（JSON）を読み込んでキャッチに回します
    .then(res => res.json().then(data => ({ status: res.status, ok: res.ok, body: data })))
    .then(res => {
        if (!res.ok) {
            throw new Error(res.body.error || "通信エラーが発生しました。");
        }
        if (res.body.success) {
            alert("コピーに成功しました！マイデッキ一覧に移動します。");
            window.location.href = '/mydecks';
        } else {
            alert("コピーに失敗しました: " + res.body.error);
        }
    })
    .catch(err => {
        alert("コピー処理中にエラーが発生しました:\n" + err.message);
    });
}
</script>
</body>
</html>