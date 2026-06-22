<!-- app/Views/deck/index.php -->
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>マイデッキ一覧</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
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

/* 2. ボタン用のスタイル追加 */
.btn-image {
    flex: 1.5;
    padding: 10px;
    background: #17a2b8;
    color: white;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-weight: bold;
    font-size: 0.9rem;
}
.btn-image:hover {
    background: #138496;
}

/* 3. 画像出力用の一時的な非表示コンテナのスタイル（1200px固定） */
#deck-export-container {
    position: absolute;
    left: -9999px;
    top: 0;
    width: 1200px;
    background-color: #fff;
    color: #000;
    font-family: sans-serif;
    padding: 30px;
    box-sizing: border-box;
}

/* 画像化レイアウトのCSS */
.export-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    border-bottom: 2px solid #ccc;
    padding-bottom: 15px;
    margin-bottom: 20px;
}
.export-title {
    font-size: 2.2rem;
    font-weight: bold;
    margin: 0;
}
.export-meta {
    text-align: right;
}
.export-colors {
    display: flex;
    gap: 5px;
    justify-content: flex-end;
    margin-bottom: 8px;
}
.export-color-badge {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    color: #fff;
    font-weight: bold;
    font-size: 0.85rem;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 1px 1px 3px rgba(0,0,0,0.3);
}
/* 文明色マッピング */
.bg-fire    { background-color: #e6193c; } 
.bg-water   { background-color: #1972e6; } 
.bg-light   { background-color: #e6b800; color: #000 !important; } 
.bg-dark    { background-color: #4b2c80; } 
.bg-nature  { background-color: #2ca043; } 
.bg-zero    { background-color: #7d8285; }

.export-format {
    font-size: 1.2rem;
    color: #444;
    font-weight: bold;
}

.export-body {
    display: flex;
    gap: 30px;
}
/* サブデッキ群の有無でグリッド分割比を切り替え */
.export-body.single-column .export-main-deck-wrapper {
    width: 100%;
}
.export-body.two-column .export-main-deck-wrapper {
    width: 72%;
}
.export-body.two-column .export-sub-decks-wrapper {
    width: 28%;
}

.export-section-title {
    font-size: 1.4rem;
    font-weight: bold;
    text-align: center;
    border-bottom: 1px solid #ddd;
    padding-bottom: 5px;
    margin: 0 0 15px 0;
}

/* カード画像配置用グリッド */
.export-card-grid {
    display: grid;
    gap: 6px;
}
.grid-main {
    grid-template-columns: repeat(8, 1fr); /* メインは1行8枚 */
}
.grid-sub {
    grid-template-columns: repeat(4, 1fr);  /* サブは1行4枚 */
}

.export-card-item {
    position: relative;
    aspect-ratio: 51 / 73;
    overflow: hidden;
    border-radius: 4px;
    border: 1px solid #ddd;
    background-color: #f5f5f5;
}
.export-card-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.export-sub-zone {
    margin-bottom: 25px;
}

.export-footer {
    text-align: center;
    margin-top: 30px;
    font-size: 0.9rem;
    font-weight: bold;
    color: #777;
    letter-spacing: 2px;
}

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
                        <!-- 追加: 画像出力ボタン。引数にデッキ名とフォーマット名を渡します -->
                        <button class="btn-image" onclick="exportDeckImage(<?php echo $deck['deck_id']; ?>, '<?php echo htmlspecialchars($deck['deck_name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($deck['format_name'], ENT_QUOTES); ?>', this)">画像出力</button>
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

/* <script> の末尾に追加 */

/**
 * デッキ画像の出力および保存処理
 */
function exportDeckImage(deckId, deckName, formatName, buttonElement) {
    if (buttonElement) {
        buttonElement.innerText = '生成中...';
        buttonElement.disabled = true;
    }

    const apiEndpoint = `/api/decks/view?deck_id=${deckId}`;

    fetch(apiEndpoint)
    .then(res => res.json())
    .then(cards => {
        if (!Array.isArray(cards)) {
            alert('デッキ情報の取得に失敗しました。');
            resetBtn();
            return;
        }

        // 1. 保存された並び順（sort_order）を維持するため、明示的にソートを行う
        cards.sort((a, b) => {
            const orderA = a.sort_order !== undefined ? parseInt(a.sort_order) : (a.order !== undefined ? parseInt(a.order) : 0);
            const orderB = b.sort_order !== undefined ? parseInt(b.sort_order) : (b.order !== undefined ? parseInt(b.order) : 0);
            return orderA - orderB;
        });

        const mainCards = [];
        const grCards = [];
        const psychicCards = [];
        const specialCards = [];
        const colors = new Set();

        // 2. ゾーン分けおよび文明情報のチェック、枚数の再現展開
        cards.forEach(card => {
            // 文明色判定
            if (card.civ_fire || card.civilization_id == 4) colors.add('fire');
            if (card.civ_water || card.civilization_id == 2) colors.add('water');
            if (card.civ_light || card.civilization_id == 1) colors.add('light');
            if (card.civ_dark || card.civilization_id == 3) colors.add('dark');
            if (card.civ_nature || card.civilization_id == 5) colors.add('nature');
            if (card.civ_zero || card.civilization_id == 6) colors.add('zero');

            const zone = (card.card_type_in_deck || 'main').toLowerCase();
            
            // APIデータが集約（quantityが2以上）されているケースにも対応できるよう、
            // quantity の数だけ配列に繰り返し追加し、枚数を維持して展開します。
            const qty = parseInt(card.quantity || card.qty || 1);

            for (let i = 0; i < qty; i++) {
                if (zone === 'gr') {
                    grCards.push(card);
                } else if (zone === 'psychic' || zone === 'super_psychic') {
                    psychicCards.push(card);
                } else if (zone === 'special') {
                    specialCards.push(card);
                } else {
                    mainCards.push(card);
                }
            }
        });

        const hasSubDeck = (grCards.length > 0 || psychicCards.length > 0 || specialCards.length > 0);

        // 3. 一時出力用DOMの構築
        const exportContainer = document.createElement('div');
        exportContainer.id = 'deck-export-container';

        // 文明バッジ
        const colorLabels = { fire: '火', water: '水', light: '光', dark: '闇', nature: '自然', zero: 'ゼロ' };
        let colorHtml = '';
        ['fire', 'water', 'light', 'dark', 'nature', 'zero'].forEach(c => {
            if (colors.has(c)) {
                colorHtml += `<div class="export-color-badge bg-${c}">${colorLabels[c]}</div>`;
            }
        });

        exportContainer.innerHTML = `
            <div class="export-header">
                <h1 class="export-title">${escapeHTML(deckName)}</h1>
                <div class="export-meta">
                    <div class="export-colors">${colorHtml}</div>
                    <div class="export-format">フォーマット : ${escapeHTML(formatName || '未指定')}</div>
                </div>
            </div>
            <div class="export-body ${hasSubDeck ? 'two-column' : 'single-column'}">
                <!-- メインデッキ (1列8枚) -->
                <div class="export-main-deck-wrapper">
                    <h2 class="export-section-title">メインデッキ</h2>
                    <div class="export-card-grid grid-main" id="export-main-grid"></div>
                </div>
                <!-- サブデッキ (1列4枚) -->
                ${hasSubDeck ? `
                    <div class="export-sub-decks-wrapper">
                        ${grCards.length > 0 ? `
                            <div class="export-sub-zone">
                                <h2 class="export-section-title">超GRゾーン</h2>
                                <div class="export-card-grid grid-sub" id="export-gr-grid"></div>
                            </div>
                        ` : ''}
                        ${psychicCards.length > 0 ? `
                            <div class="export-sub-zone">
                                <h2 class="export-section-title">超次元ゾーン</h2>
                                <div class="export-card-grid grid-sub" id="export-psychic-grid"></div>
                            </div>
                        ` : ''}
                        ${specialCards.length > 0 ? `
                            <div class="export-sub-zone">
                                <h2 class="export-section-title">特殊</h2>
                                <div class="export-card-grid grid-sub" id="export-special-grid"></div>
                            </div>
                        ` : ''}
                    </div>
                ` : ''}
            </div>
            <div class="export-footer">⚡ DECK MAKER</div>
        `;

        document.body.appendChild(exportContainer);

        // カード画像配置処理
        const renderGrid = (cardsArray, gridId) => {
            const grid = document.getElementById(gridId);
            if (!grid) return;
            cardsArray.forEach(card => {
                const imgWrap = document.createElement('div');
                imgWrap.className = 'export-card-item';
                
                const path = card.imagepath || '';
                const thumbPath = path ? '/images/card' + (path.startsWith('/') ? path : '/' + path) : '/images/card/noimage.webp';

                imgWrap.innerHTML = `<img src="${thumbPath}" class="export-card-img" onerror="this.src='/images/card/noimage.webp';">`;
                grid.appendChild(imgWrap);
            });
        };

        renderGrid(mainCards, 'export-main-grid');
        if (grCards.length > 0) renderGrid(grCards, 'export-gr-grid');
        if (psychicCards.length > 0) renderGrid(psychicCards, 'export-psychic-grid');
        if (specialCards.length > 0) renderGrid(specialCards, 'export-special-grid');

        // 画像ロードの完了を待機してからレンダリング
        const images = exportContainer.querySelectorAll('img');
        const promises = Array.from(images).map(img => {
            return new Promise(resolve => {
                if (img.complete) resolve();
                else {
                    img.onload = () => resolve();
                    img.onerror = () => resolve();
                }
            });
        });

        Promise.all(promises).then(() => {
            html2canvas(exportContainer, {
                useCORS: true,
                scale: 2 // 高画質化
            }).then(canvas => {
                const link = document.createElement('a');
                link.download = `${deckName}.png`;
                link.href = canvas.toDataURL('image/png');
                link.click();

                document.body.removeChild(exportContainer);
                resetBtn();
            }).catch(err => {
                console.error(err);
                alert('画像作成中にエラーが発生しました。');
                document.body.removeChild(exportContainer);
                resetBtn();
            });
        });
    })
    .catch(err => {
        console.error(err);
        alert('通信エラーが発生しました。');
        resetBtn();
    });

    function resetBtn() {
        if (buttonElement) {
            buttonElement.innerText = '画像出力';
            buttonElement.disabled = false;
        }
    }

    function escapeHTML(str) {
        if (!str) return '';
        return str.replace(/&/g, '&amp;')
                  .replace(/</g, '&lt;')
                  .replace(/>/g, '&gt;')
                  .replace(/"/g, '&quot;')
                  .replace(/'/g, '&#39;');
    }
}
</script>

</body>
</html>