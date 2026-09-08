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
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
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
</div>

<?php include __DIR__ . '/deck_detail_modal.php'; ?>
<?php include __DIR__ . '/card_detail_modal.php'; ?>

<!-- デッキ出力方法選択モーダル -->
<div id="deck-export-choice-modal" class="sub-modal" style="display: none;">
    <div class="sub-modal-content" style="max-width: 400px;">
        <div class="sub-modal-header">
            <span>デッキ出力</span>
            <button type="button" onclick="closeExportChoiceModal()" style="background:none; border:none; color:#fff; font-size:1.2rem; cursor:pointer;">×</button>
        </div>
        <div class="sub-modal-body" style="padding: 30px; text-align: center;">
            <p style="margin-top: 0; margin-bottom: 25px; font-weight: bold; color: #333;">出力形式を選択してください</p>
            <div style="display: flex; gap: 15px; justify-content: center;">
                <button type="button" id="btn-export-image" style="flex: 1; padding: 12px; background: #17a2b8; color: white; border: none; border-radius: 6px; font-weight: bold; cursor: pointer;">画像で出力</button>
                <button type="button" id="btn-export-zip" style="flex: 1; padding: 12px; background: #28a745; color: white; border: none; border-radius: 6px; font-weight: bold; cursor: pointer;">ZIPで出力</button>
            </div>
        </div>
    </div>
</div>

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

let currentExportDeckId = null;
let currentExportDeckName = null;
let currentExportFormatName = null;
let currentExportButton = null;

/**
 * デッキ出力ボタン押下時（モーダルを表示）
 */
function exportDeckImage(deckId, deckName, formatName, buttonElement) {
    currentExportDeckId = deckId;
    currentExportDeckName = deckName;
    currentExportFormatName = formatName;
    currentExportButton = buttonElement;

    const modal = document.getElementById('deck-export-choice-modal');
    if (modal) modal.style.display = 'flex';
}

function closeExportChoiceModal() {
    const modal = document.getElementById('deck-export-choice-modal');
    if (modal) modal.style.display = 'none';
}

// モーダルのイベントリスナー設定
document.addEventListener('DOMContentLoaded', () => {
    const btnImage = document.getElementById('btn-export-image');
    const btnZip = document.getElementById('btn-export-zip');

    if (btnImage) {
        btnImage.addEventListener('click', () => {
            closeExportChoiceModal();
            executeImageExport(currentExportDeckId, currentExportDeckName, currentExportFormatName, currentExportButton);
        });
    }

    if (btnZip) {
        btnZip.addEventListener('click', () => {
            closeExportChoiceModal();
            executeZipExport(currentExportDeckId, currentExportDeckName, currentExportButton);
        });
    }
});

/**
 * ZIPファイル形式での出力処理（特定の禁断カードを除外＆メインデッキ限定）
 */
async function executeZipExport(deckId, deckName, buttonElement) {
    if (buttonElement) {
        buttonElement.innerText = 'ZIP生成中...';
        buttonElement.disabled = true;
    }

    try {
        const res = await fetch(`/api/decks/view?deck_id=${deckId}`);
        const cards = await res.json();

        if (!Array.isArray(cards) || cards.length === 0) {
            alert('デッキ情報の取得に失敗しました。');
            resetBtn();
            return;
        }

        const zip = new JSZip();
        
        try {
            const tokenRes = await fetch('/images/.token');
            if (tokenRes.ok) {
                const tokenBlob = await tokenRes.blob();
                zip.file('.token', tokenBlob);
            } else {
                zip.file('.token', '');
            }
        } catch (e) {
            zip.file('.token', '');
        }

        const itemsObj = {};
        const resourcesObj = {};
        
        function generateId(length = 20) {
            const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
            let result = '';
            for (let i = 0; i < length; i++) {
                result += chars.charAt(Math.floor(Math.random() * chars.length));
            }
            return result;
        }

        async function calculateSha256(blob) {
            const buffer = await blob.arrayBuffer();
            const hashBuffer = await crypto.subtle.digest('SHA-256', buffer);
            const hashArray = Array.from(new Uint8Array(hashBuffer));
            const hashHex = hashArray.map(b => b.toString(16).padStart(2, '0')).join('');
            return hashHex;
        }

        for (const card of cards) {
            const path = card.imagepath || '';
            if (!path) continue;

            // 1. ゾーンの判定 (メインデッキのみ対象)
            const zone = (card.card_type_in_deck || 'main').toLowerCase();
            if (zone !== 'main') {
                continue; // メインデッキ以外（超次元、GRなど）は除外
            }

            // 2. 除外するカード名の判定
            const cardName = card.card_name ? card.card_name.trim() : '';
            if (cardName === '禁断 ～封印されしX～' || cardName === '伝説の禁断 ドキンダムX') {
                continue; // 指定された禁断カードは除外
            }

            const fullImagePath = '/images/card' + (path.startsWith('/') ? path : '/' + path);

            try {
                const imgRes = await fetch(fullImagePath);
                if (imgRes.ok) {
                    const imgBlob = await imgRes.blob();
                    
                    const sha256Hash = await calculateSha256(imgBlob);
                    
                    let ext = 'webp';
                    if (imgBlob.type === 'image/jpeg') ext = 'jpeg';
                    else if (imgBlob.type === 'image/png') ext = 'png';

                    const hashedFilename = `${sha256Hash}.${ext}`;

                    // 重複追加を防ぐため、すでに同じリソースがなければ追加
                    if (!resourcesObj[hashedFilename]) {
                        zip.file(hashedFilename, imgBlob);
                        resourcesObj[hashedFilename] = {
                            "type": imgBlob.type || "image/webp"
                        };
                    }

                    const itemId = generateId();
                    itemsObj[itemId] = {
                        "imageUrl": hashedFilename,
                        "memo": ""
                    };
                }
            } catch (err) {
                console.warn(`画像取得失敗: ${fullImagePath}`, err);
            }
        }

        const deckRandomId = generateId();
        const dataJson = {
            "meta": {
                "version": "1.1.0"
            },
            "entities": {
                "room": {},
                "items": {},
                "decks": {
                    [deckRandomId]: {
                        "x": -2,
                        "y": -3,
                        "z": 99,
                        "zIndex": 1,
                        "width": 4,
                        "height": 6,
                        "locked": false,
                        "freezed": false,
                        "coverImageUrl": null,
                        "items": itemsObj
                    }
                },
                "notes": {},
                "characters": {},
                "effects": {},
                "scenes": {},
                "savedatas": {},
                "snapshots": {}
            },
            "resources": resourcesObj
        };

        zip.file('__data.json', JSON.stringify(dataJson, null, 2));

        const content = await zip.generateAsync({ type: 'blob' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(content);
        link.download = `${deckName}.zip`;
        link.click();

        resetBtn();
    } catch (err) {
        console.error(err);
        alert('ZIP作成中にエラーが発生しました。');
        resetBtn();
    }

    function resetBtn() {
        if (buttonElement) {
            buttonElement.innerText = 'デッキ出力';
            buttonElement.disabled = false;
        }
    }
}

/**
 * 従来の画像出力処理
 */
function executeImageExport(deckId, deckName, formatName, buttonElement) {
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

        cards.forEach(card => {
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
                    zone === 'super_dimensional'
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

        const exportContainer = document.createElement('div');
        exportContainer.id = 'deck-export-container';

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
                <div class="export-main-deck-wrapper">
                    <h2 class="export-section-title">メインデッキ</h2>
                    <div class="export-card-grid grid-main" id="export-main-grid"></div>
                </div>
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
                scale: 2
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
            buttonElement.innerText = 'デッキ出力';
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