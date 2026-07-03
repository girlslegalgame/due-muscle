<!-- app/Views/deck/search.php -->
<?php
// --- 追加: DBからカード名一覧を重複なし・50音（読み）順で取得 ---
try {
    $pdo_db = \Models\Database::connect();
    // card_nameの重複を除外し、空でないものを読み順（ひらがな）でソートして取得
    $stmt_all_cards = $pdo_db->query("
        SELECT DISTINCT card_name, COALESCE(NULLIF(reading, ''), card_name) as reading_sort 
        FROM card 
        WHERE card_name IS NOT NULL AND card_name <> '' 
        ORDER BY CASE WHEN reading_sort = '' THEN 1 ELSE 0 END, reading_sort ASC, card_name ASC
    ");
    $unique_cards = $stmt_all_cards->fetchAll(PDO::FETCH_ASSOC);
} catch (\Exception $e) {
    $unique_cards = [];
}
?>
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
/* --- 追加: カード選択モーダルのスタイル --- */
        .card-select-modal {
            display: none;
            position: fixed;
            z-index: 10000;
            left: 0; top: 0;
            width: 100%; height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        .card-select-modal-content {
            background-color: #fff;
            margin: 5% auto;
            padding: 20px;
            width: 90%;
            max-width: 600px;
            border-radius: 8px;
            height: 80vh;
            display: flex;
            flex-direction: column;
            box-sizing: border-box;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .card-select-modal-header {
            border-bottom: 1px solid #ddd;
            padding-bottom: 12px;
            margin-bottom: 12px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            flex-shrink: 0;
        }
        .card-select-modal-title {
            font-size: 1.1rem;
            font-weight: bold;
            margin: 0;
        }
        .card-select-modal-search {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .card-select-modal-search input {
            flex: 1;
            padding: 8px 12px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 0.9rem;
        }
        .card-select-modal-logic {
            display: flex;
            gap: 15px;
            font-size: 0.8rem;
            color: #555;
        }
        .card-select-modal-body {
            flex: 1;
            overflow-y: auto;
            border: 1px solid #eee;
            border-radius: 4px;
            padding: 5px;
        }
        .card-select-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px;
            border-bottom: 1px solid #f5f5f5;
            cursor: pointer;
            font-size: 0.9rem;
        }
        .card-select-item:hover {
            background-color: #f9f9f9;
        }
        .card-select-item input {
            cursor: pointer;
        }
        .card-select-modal-footer {
            border-top: 1px solid #ddd;
            padding-top: 12px;
            margin-top: 12px;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            flex-shrink: 0;
        }
        .btn-modal-cancel {
            padding: 8px 16px;
            background: #ccc;
            color: #333;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
        }
        .btn-modal-confirm {
            padding: 8px 16px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
        }

/* --- 追加: 選択済みカードバッジ用のスタイル --- */
        .selected-badges-container {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-top: 5px;
            max-height: 85px;
            overflow-y: auto;
            padding: 5px;
            background: #fdfdfd;
            border: 1px dashed #ccc;
            border-radius: 4px;
        }
        .selected-badge {
            background-color: #e0f2fe;
            color: #0369a1;
            border: 1px solid #bae6fd;
            border-radius: 16px;
            padding: 2px 10px;
            font-size: 0.8rem;
            display: flex;
            align-items: center;
            gap: 6px;
            font-weight: bold;
        }
        .selected-badge-remove {
            cursor: pointer;
            color: #ef4444;
            font-weight: bold;
            font-size: 0.95rem;
            line-height: 1;
            display: inline-block;
            margin-left: 2px;
        }
        .selected-badge-remove:hover {
            color: #b91c1c;
        }
        
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
            <label>採用カード</label>
            <input type="text" id="selected_card_names" name="card_name" 
                   value="<?php echo htmlspecialchars($searchValues['card_name'] ?? ''); ?>" 
                   placeholder="クリックして採用カードを選択..." 
                   readonly 
                   onclick="openCardSelectModal()" 
                   style="cursor: pointer; background-color: #fff;">
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
            <?php 
            $context = 'search'; // 呼び出し元コンテキストを公開デッキ検索に指定 
            foreach ($decks as $deck): 
                include __DIR__ . '/deck_item.php'; 
            endforeach; 
            ?>
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

// PHPから渡された一意の50音順カードリストをJSに受け渡す
const ALL_CARDS = <?php echo json_encode($unique_cards, JSON_UNESCAPED_UNICODE); ?>;

// 現在チェックが入れられているカード名を保持するSet
let tempSelectedCards = new Set();

/**
 * モーダルを開く
 */
function openCardSelectModal() {
    const modal = document.getElementById('cardSelectModal');
    modal.style.display = 'block';

    const currentVal = document.getElementById('selected_card_names').value;
    tempSelectedCards = new Set(currentVal ? currentVal.split(',').map(s => s.trim()) : []);

    document.getElementById('modalCardSearchInput').value = '';
    
    // バッジ表示とカード一覧の初期描画
    updateSelectedBadges();
    renderModalCards(ALL_CARDS);
}

/**
 * 選択中カードバッジ表示の更新
 */
function updateSelectedBadges() {
    const container = document.getElementById('modalSelectedBadges');
    container.innerHTML = '';

    if (tempSelectedCards.size === 0) {
        container.style.display = 'none';
        return;
    }

    container.style.display = 'flex';

    tempSelectedCards.forEach(cardName => {
        const badge = document.createElement('div');
        badge.className = 'selected-badge';
        badge.innerHTML = `
            <span>${escapeHTML(cardName)}</span>
            <span class="selected-badge-remove" onclick="removeSelectedCard('${escapeHTML(cardName)}')">×</span>
        `;
        container.appendChild(badge);
    });
}

/**
 * バッジの×ボタンを押して選択を解除する処理
 */
function removeSelectedCard(cardName) {
    // 選択を削除
    tempSelectedCards.delete(cardName);
    
    // バッジを再描画
    updateSelectedBadges();
    
    // 現在画面に表示されているカード一覧の中に該当するチェックボックスがあれば、チェックを外す
    const checkboxes = document.querySelectorAll('#modalCardList input[type="checkbox"]');
    for (let cb of checkboxes) {
        if (cb.value === cardName) {
            cb.checked = false;
            break;
        }
    }
}

/**
 * モーダルを閉じる
 */
function closeCardSelectModal() {
    document.getElementById('cardSelectModal').style.display = 'none';
}

/**
 * カードリストをDOMに描画する
 */
function renderModalCards(cards) {
    const container = document.getElementById('modalCardList');
    container.innerHTML = '';

    if (cards.length === 0) {
        container.innerHTML = '<div style="text-align:center; padding:20px; color:#888;">一致するカードが見つかりません。</div>';
        return;
    }

    cards.forEach(card => {
        const item = document.createElement('label');
        item.className = 'card-select-item';
        
        const isChecked = tempSelectedCards.has(card.card_name) ? 'checked' : '';

        item.innerHTML = `
            <input type="checkbox" value="${escapeHTML(card.card_name)}" ${isChecked} onchange="toggleCardSelect(this)">
            <span style="display: flex; flex-direction: column;">
                <span style="font-weight: bold;">${escapeHTML(card.card_name)}</span>
                <span style="font-size: 0.75rem; color: #888;">${escapeHTML(card.reading_sort || '')}</span>
            </span>
        `;
        container.appendChild(item);
    });
}

/**
 * チェックボックス変更時の状態保持
 */
function toggleCardSelect(checkbox) {
    if (checkbox.checked) {
        tempSelectedCards.add(checkbox.value);
    } else {
        tempSelectedCards.delete(checkbox.value);
    }
    // バッジをリアルタイム更新
    updateSelectedBadges();
}

/**
 * 検索ワードおよびAND/ORロジックでカードを絞り込む
 */
function filterModalCards() {
    const query = document.getElementById('modalCardSearchInput').value.trim().toLowerCase();
    const isAnd = document.querySelector('input[name="modal_search_logic"]:checked').value === 'AND';

    if (!query) {
        renderModalCards(ALL_CARDS);
        return;
    }

    // スペース（全角・半角）でキーワードを分割
    const keywords = query.split(/[\s　]+/).filter(k => k !== '');

    const filtered = ALL_CARDS.filter(card => {
        const name = (card.card_name || '').toLowerCase();
        const reading = (card.reading_sort || '').toLowerCase();

        if (isAnd) {
            // すべてのキーワードが含まれているか (AND)
            return keywords.every(kw => name.includes(kw) || reading.includes(kw));
        } else {
            // いずれかのキーワードが含まれているか (OR)
            return keywords.some(kw => name.includes(kw) || reading.includes(kw));
        }
    });

    renderModalCards(filtered);
}

/**
 * 選択を確定してインプットへ反映する
 */
function confirmCardSelection() {
    const arr = Array.from(tempSelectedCards);
    document.getElementById('selected_card_names').value = arr.join(',');
    closeCardSelectModal();
}

// モーダルの外枠をクリックした時に閉じる設定
window.addEventListener('click', function(event) {
    const modal = document.getElementById('cardSelectModal');
    if (event.target === modal) {
        closeCardSelectModal();
    }
});

// エスケープ関数
function escapeHTML(str) {
    if (!str) return '';
    return str.replace(/&/g, '&amp;')
              .replace(/</g, '&lt;')
              .replace(/>/g, '&gt;')
              .replace(/"/g, '&quot;')
              .replace(/'/g, '&#39;');
}
</script>

<div id="cardSelectModal" class="card-select-modal">
    <div class="card-select-modal-content">
        <div class="card-select-modal-header">
            <h3 class="card-select-modal-title">採用カードの選択 (複数選択可)</h3>
            
            <!-- 検索ボックス -->
            <div class="card-select-modal-search">
                <input type="text" id="modalCardSearchInput" placeholder="カード名または読み仮名を入力..." oninput="filterModalCards()">
            </div>
            
            <!-- 選択中カードのバッジ表示エリア -->
            <div id="modalSelectedBadges" class="selected-badges-container" style="display: none;">
                <!-- JavaScriptにより動的に選択中のバッジ（×ボタン付き）が追加されます -->
            </div>
            
            <!-- AND / OR 条件切り替え -->
            <div class="card-select-modal-logic">
                条件:
                <label style="cursor: pointer; display: flex; align-items: center; gap: 4px;">
                    <input type="radio" name="modal_search_logic" value="AND" checked onchange="filterModalCards()"> AND検索
                </label>
                <label style="cursor: pointer; display: flex; align-items: center; gap: 4px;">
                    <input type="radio" name="modal_search_logic" value="OR" onchange="filterModalCards()"> OR検索
                </label>
            </div>
        </div>
        
        <!-- カード一覧表示領域 -->
        <div class="card-select-modal-body" id="modalCardList">
            <!-- JavaScriptで動的にカード一覧を出力 -->
        </div>
        
        <div class="card-select-modal-footer">
            <button class="btn-modal-cancel" onclick="closeCardSelectModal()">キャンセル</button>
            <button class="btn-modal-confirm" onclick="confirmCardSelection()">確定する</button>
        </div>
    </div>
</div>
</body>
</html>