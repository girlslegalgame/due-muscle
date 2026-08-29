<!-- app/Views/deck/index.php -->
<?php
try {
    $pdo_db = \Models\Database::connect();
} catch (\Exception $e) {
    $pdo_db = null;
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>マイデッキ一覧</title>
    <link rel="stylesheet" href="/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <!-- index.php の <style> 変更後 -->
<style>
/* 3. 画像出力用の一時的な非表示コンテナのスタイル（1200px固定）のみ残します */
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

.export-card-grid {
    display: grid;
    gap: 6px;
}
.grid-main {
    grid-template-columns: repeat(8, 1fr);
}
.grid-sub {
    grid-template-columns: repeat(4, 1fr);
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
            <?php 
            $context = 'index'; // 呼び出し元コンテキストをマイデッキ一覧に指定 
            foreach ($decks as $deck): 
                include __DIR__ . '/deck_item.php'; 
            endforeach; 
            ?>
        <?php else: ?>
            <!-- ★追加: 未ログイン、またはデッキが1件もない場合の表示切り替え -->
            <div style="grid-column: 1 / -1; text-align: center; padding: 40px 20px; background: #fff; border-radius: 8px; border: 1px solid #ddd;">
                <?php if (!isset($_SESSION['user_id'])): ?>
                    <p style="font-size: 1.1rem; color: #333; font-weight: bold; margin-bottom: 10px;">アカウントを作成すると、デッキを保存できます。</p>
                    <p style="color: #666; font-size: 0.9rem; margin-bottom: 25px;">作成したデッキをクラウドに保存して、いつでも編集や公開ができるようになります。</p>
                    <a href="/register" style="display: inline-block; padding: 12px 30px; background-color: #28a745; color: #fff; text-decoration: none; border-radius: 6px; font-weight: bold; font-size: 1rem;">アカウントを作成する</a>
                <?php else: ?>
                    <p style="color: #666;">デッキが登録されていません。</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
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

            const qty = parseInt(card.quantity || card.qty || 1);

            for (let i = 0; i < qty; i++) {
                if (zone === 'gr') {
                    grCards.push(card);
                } else if (
                    zone === 'psychic' || 
                    zone === 'super_psychic' || 
                    zone === 'super_dimensional' // ★超次元ゾーンの判定に super_dimensional を追加します
                ) {
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
// ★追加: ログイン・新規登録後にマイデッキ一覧に戻ってきた際、保存待ちのデッキデータがあれば自動で保存を実行する
window.addEventListener('DOMContentLoaded', () => {
    if (localStorage.getItem('pending_deck_save') === 'true') {
        const savedPayloadStr = localStorage.getItem('pending_deck_payload');
        if (savedPayloadStr) {
            try {
                const payload = JSON.parse(savedPayloadStr);
                console.log("ログイン完了を検知しました。デッキの自動保存を直ちに実行します...");

                fetch('/api/decks', {
                    method: payload.deck_id ? 'PUT' : 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                })
                .then(res => res.json())
                .then(data => {
                    // 保存処理が終わったらフラグをすべてクリア
                    localStorage.removeItem('pending_deck_save');
                    localStorage.removeItem('pending_deck_payload');
                    localStorage.removeItem('unsaved_deck_draft');
                    
                    if (data.success) {
                        alert("ログインが完了し、作成していたデッキの保存に成功しました！");
                        // マイデッキ一覧をリロードして最新の保存されたデッキを表示
                        location.reload();
                    } else {
                        alert("ログインは成功しましたが、デッキの自動保存に失敗しました: " + (data.error || '不明なエラー'));
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert("自動保存中に通信エラーが発生しました。");
                    localStorage.removeItem('pending_deck_save');
                    localStorage.removeItem('pending_deck_payload');
                });
            } catch (e) {
                console.error(e);
                localStorage.removeItem('pending_deck_save');
                localStorage.removeItem('pending_deck_payload');
            }
        }
    }
});
</script>

</body>
</html>