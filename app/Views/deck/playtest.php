<!-- app/Views/deck/playtest.php -->
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>1人回し - <?php echo htmlspecialchars($deck['deck_name'] ?? 'デッキ'); ?></title>
    <style>
        body {
            background-color: #121212;
            color: #ffffff;
            font-family: sans-serif;
            margin: 0; padding: 10px;
            user-select: none;
        }
        .playtest-container {
            max-width: 100%;
            margin: 0 auto;
        }
        .header-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #333;
            padding-bottom: 8px;
            margin-bottom: 12px;
        }
        .header-bar h2 { margin: 0; font-size: 1.25rem; }
        .btn-exit {
            padding: 6px 16px;
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 4px;
            text-decoration: none;
            font-weight: bold;
            font-size: 13px;
        }

        /* 警告表示 */
        .error-overlay {
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.9);
            z-index: 30000;
            display: none;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            color: #ff4d4d;
            text-align: center;
            padding: 20px;
        }
        .error-overlay h1 { margin-bottom: 10px; font-size: 2rem; }

        /* --- 盤面レイアウト --- */
        .playtest-board {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .playtest-row {
            display: flex;
            gap: 12px;
            width: 100%;
        }
        .playtest-zone {
            background: rgba(255,255,255,0.05);
            border: 2px dashed rgba(255,255,255,0.15);
            border-radius: 8px;
            position: relative;
            box-sizing: border-box;
            display: flex;
            flex-direction: row;
            align-items: center;
            gap: 12px;
            overflow-x: auto;
            white-space: nowrap;
        }
        
        /* ゾーン別の高さ・幅の再定義 */
        .zone-battle { width: 100%; min-height: 180px; padding: 25px 12px 10px 12px; }
        .zone-shield { flex: 1; min-height: 180px; padding: 25px 12px 10px 12px; }

        /* 固定型ゾーン（山札・墓地・超次元・超GR） */
        .fixed-zones-container {
            display: flex;
            gap: 12px;
            flex-shrink: 0;
        }
        .fixed-zone {
            width: 105px;
            height: 147px;
            border: 2px solid #444;
            border-radius: 6px;
            position: relative;
            background: rgba(255,255,255,0.03);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            flex-shrink: 0;
            cursor: pointer;
        }
        .playtest-zone-title {
            position: absolute;
            top: 4px; left: 6px;
            font-size: 11px;
            color: #aaa;
            font-weight: bold;
            z-index: 10;
        }

        .zone-mana { width: 100%; min-height: 170px; padding: 25px 12px 10px 12px; }
        .zone-hand { width: 100%; min-height: 170px; padding: 25px 12px 10px 12px; justify-content: center; }

        /* --- カードデザイン --- */
        .playtest-card {
            width: 90px;
            height: 126px;
            position: relative;
            cursor: grab;
            transition: transform 0.15s;
            border-radius: 4px;
            box-shadow: 1px 1px 5px rgba(0,0,0,0.6);
            display: inline-block;
            box-sizing: border-box;
            flex-shrink: 0;

            /* 追加：画像が読み込めなくてもカードの存在がわかるようにする */
            background-color: #2a2a2a; 
            border: 1px solid #444;
            overflow: hidden;
        }
        .playtest-card:active { cursor: grabbing; }
        .playtest-card img {
            width: 100%;
            height: 100%;
            object-fit: fill;
            border-radius: 4px;
            pointer-events: none; /* 子要素画像へのドラッグ干渉を無効化 */
        }

        /* 状態クラス */
        .playtest-card.tapped { transform: rotate(90deg); }
        .playtest-card.inverted { transform: rotate(180deg); }
        .playtest-card.inverted.tapped { transform: rotate(270deg); }

        /* ドラッグ中の視覚効果 */
        .playtest-card.dragging {
            opacity: 0.5;
        }

        /* 選択中カード */
        .playtest-card.selected {
            outline: 3px solid #007bff;
            box-shadow: 0 0 10px #007bff;
        }

        .playtest-under-count {
            position: absolute;
            top: -4px; right: -4px;
            background: #dc3545;
            color: white;
            border-radius: 50%;
            width: 20px; height: 20px;
            font-size: 11px;
            font-weight: bold;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 5;
            border: 1px solid white;
        }
        .playtest-zone-center-text {
            position: absolute;
            background: rgba(0,0,0,0.8);
            color: white;
            font-size: 14px;
            font-weight: bold;
            padding: 4px 8px;
            border-radius: 4px;
            pointer-events: none;
            z-index: 5;
        }

        /* コンテキストメニュー */
        .context-menu {
            position: fixed;
            background: #252525;
            color: #eee;
            border: 1px solid #444;
            box-shadow: 2px 2px 10px rgba(0,0,0,0.5);
            z-index: 10000;
            display: none;
            border-radius: 4px;
            padding: 5px 0;
            min-width: 180px;
            font-size: 13px;
        }
        .context-menu li {
            padding: 8px 15px;
            cursor: pointer;
            list-style: none;
        }
        .context-menu li:hover { background: #007bff; color: white; }
        .context-menu hr { border: 0; border-top: 1px solid #444; margin: 4px 0; }

        /* ダイアログ/モーダル */
        .pt-modal {
            display: none;
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.85);
            z-index: 20000;
            justify-content: center;
            align-items: center;
        }
        .pt-modal-content {
            background: #1e1e1e;
            border: 1px solid #333;
            border-radius: 8px;
            width: 90%;
            max-width: 700px;
            max-height: 85vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,0.5);
        }
        .pt-modal-header {
            padding: 12px 16px;
            border-bottom: 1px solid #333;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .pt-modal-header h3 { margin: 0; font-size: 1.1rem; }
        .pt-modal-close {
            background: none; border: none; color: #aaa; font-size: 24px; cursor: pointer;
        }
        .pt-modal-close:hover { color: #fff; }
        .pt-modal-body {
            padding: 16px;
            overflow-y: auto;
            flex: 1;
        }
        .pt-modal-footer {
            padding: 12px 16px;
            border-top: 1px solid #333;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        .btn-pt-primary {
            background: #007bff; color: white; border: none; padding: 6px 14px; border-radius: 4px; cursor: pointer;
        }
        .btn-pt-secondary {
            background: #444; color: white; border: none; padding: 6px 14px; border-radius: 4px; cursor: pointer;
        }

        /* モーダル用カードグリッド */
        .pt-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            gap: 12px;
        }
        .pt-grid-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            background: rgba(255,255,255,0.03);
            padding: 8px;
            border-radius: 4px;
            border: 1px solid #333;
            cursor: pointer;
            position: relative;
        }
        .pt-grid-item.selected {
            border-color: #007bff;
            background: rgba(0,123,255,0.1);
        }
        .pt-grid-item img {
            width: 80px; height: 112px; object-fit: fill; border-radius: 2px;
        }
        .pt-grid-item .card-qty {
            font-size: 11px; margin-top: 4px; color: #aaa;
            text-align: center;
            white-space: normal;
        }
        .pt-grid-item .badge-count {
            position: absolute; top: 4px; right: 4px;
            background: #007bff; color: white; border-radius: 50%;
            width: 18px; height: 18px; font-size: 10px;
            display: flex; align-items: center; justify-content: center;
        }

        .pt-grid-item .badge-selected-count {
            position: absolute; top: 4px; left: 4px;
            background: #28a745; color: white; border-radius: 4px;
            padding: 2px 4px; font-size: 10px;
            display: flex; align-items: center; justify-content: center;
            font-weight: bold;
            z-index: 15;
        }
    </style>
</head>
<body>

<div class="error-overlay" id="ptErrorOverlay">
    <h1>デッキエラー</h1>
    <p>《禁断 ～封印されしX～》と《FORBIDDEN STAR ～世界最後の日～》が両方デッキに含まれているため、1人回しを開始できません。</p>
    <a href="/mydecks" class="btn-exit" style="margin-top: 20px;">終了して戻る</a>
</div>

<div class="playtest-container">
    <div class="header-bar">
        <h2>1人回し盤面: <?php echo htmlspecialchars($deck['deck_name'] ?? 'デッキ'); ?></h2>
        <div>
            <!-- 追加：アンタップメニューボタン -->
            <button class="btn-exit" style="background:#17a2b8; margin-right:8px;" onclick="openUntapMenu()">カードをアンタップ</button>
            <button class="btn-exit" style="background:#28a745; margin-right:8px;" onclick="toggleSelectMode()">一括選択モード切替</button>
            <a href="/mydecks" class="btn-exit">終了する</a>
        </div>
    </div>

<div class="playtest-board" id="playtestBoard">
        <!-- 1段目：バトルゾーン（横いっぱいに配置） -->
        <div class="playtest-row">
            <div class="playtest-zone zone-battle" id="pt-zone-battle" ondragover="allowDrop(event)" ondrop="handleDrop(event, 'battle')">
                <div class="playtest-zone-title">バトルゾーン</div>
            </div>
        </div>

        <!-- 2段目：シールドゾーン ＆ 固定ゾーン（山札・墓地・超次元・超GR） -->
        <div class="playtest-row">
            <div class="playtest-zone zone-shield" id="pt-zone-shield" ondragover="allowDrop(event)" ondrop="handleDrop(event, 'shield')">
                <div class="playtest-zone-title">シールドゾーン</div>
            </div>

            <!-- 固定ゾーン群 -->
            <div class="fixed-zones-container">
                <div class="fixed-zone" id="pt-zone-deck" onclick="ptDrawCard()" oncontextmenu="openPtDeckMenu(event)">
                    <div class="playtest-zone-title">山札</div>
                    <img src="/images/card/backimage.webp" style="width:100%; height:100%; object-fit:fill;">
                    <div class="playtest-zone-center-text" id="pt-deck-count">0</div>
                </div>

                <div class="fixed-zone" id="pt-zone-graveyard" onclick="openPtGraveyardViewer()" ondragover="allowDrop(event)" ondrop="handleDrop(event, 'graveyard')">
                    <div class="playtest-zone-title">墓地</div>
                    <div class="playtest-zone-center-text" id="pt-graveyard-count">0</div>
                </div>

                <div class="fixed-zone" id="pt-zone-psychic" onclick="openPtPsychicViewer()" ondragover="allowDrop(event)" ondrop="handleDrop(event, 'psychic')">
                    <div class="playtest-zone-title">超次元</div>
                </div>

                <div class="fixed-zone" id="pt-zone-gr" onclick="ptPlayGRAny()" oncontextmenu="openPtGRMenu(event)" style="display: none;">
                    <div class="playtest-zone-title">超GR</div>
                </div>
            </div>
        </div>

        <!-- 3段目：マナゾーン -->
        <div class="playtest-row">
            <div class="playtest-zone zone-mana" id="pt-zone-mana" ondragover="allowDrop(event)" ondrop="handleDrop(event, 'mana')">
                <div class="playtest-zone-title">マナゾーン</div>
            </div>
        </div>

        <!-- 4段目：手札 -->
        <div class="playtest-row">
            <div class="playtest-zone zone-hand" id="pt-zone-hand" ondragover="allowDrop(event)" ondrop="handleDrop(event, 'hand')">
                <div class="playtest-zone-title">手札</div>
            </div>
        </div>
    </div>
</div>

<!-- コンテキストメニュー -->
<ul id="ptContextMenu" class="context-menu"></ul>

<!-- リスト選択用汎用モーダル -->
<div id="ptListModal" class="pt-modal" onclick="closePtModal()">
    <div class="pt-modal-content" onclick="event.stopPropagation()">
        <div class="pt-modal-header">
            <h3 id="ptModalTitle">カード選択</h3>
            <button class="pt-modal-close" onclick="closePtModal()">&times;</button>
        </div>
        <div class="pt-modal-body" id="ptModalBody"></div>
        <div class="pt-modal-footer" id="ptModalFooter"></div>
    </div>
</div>

<script>
// PHPからの初期デッキカードデータをJSへ展開 (安全にフォールバック処理を挟む)
const initialCardsRaw = <?php echo json_encode($cards ?? []); ?>;
const initialCards = Array.isArray(initialCardsRaw) ? initialCardsRaw : [];
let ptLookingCards = []; // 現在山札の上から閲覧しているカード配列
let ptReturnOrder = [];  // 山札の下に戻すための選択順リスト

// 指定枚数を見るためのプロンプト表示
function ptLookAtDeckTopPrompt() {
    let input = prompt("山札の上から何枚見ますか？", "3");
    if (input === null) return;
    let count = parseInt(input);
    if (isNaN(count) || count < 1) {
        alert("1以上の数値を入力してください。");
        return;
    }
    ptLookAtDeckTop(count);
}

// 閲覧開始
function ptLookAtDeckTop(count) {
    ptLookingCards = [];
    ptReturnOrder = [];
    
    const actualCount = Math.min(count, ptState.deck.length);
    if (actualCount === 0) {
        alert("山札にカードがありません。");
        return;
    }

    // 山札の「上（末尾）」から指定枚数を一時退避（pop）して閲覧
    for (let i = 0; i < actualCount; i++) {
        let card = ptState.deck.pop();
        card.faceDown = false; // 表向きにする
        ptLookingCards.push(card);
    }
    
    renderLookingCardsModal();
}

// さらに上から1枚追加でめくる
function ptAddMoreCardToLooking() {
    if (ptState.deck.length === 0) {
        alert("山札にカードが残っていません。");
        return;
    }
    let card = ptState.deck.pop();
    card.faceDown = false;
    ptLookingCards.push(card);
    renderLookingCardsModal();
}

// カードをクリックして戻す順番（最下層から順に）を指定する
function selectReturnOrder(cardId) {
    const idx = ptReturnOrder.indexOf(cardId);
    if (idx !== -1) {
        ptReturnOrder.splice(idx, 1); // 選択解除
    } else {
        ptReturnOrder.push(cardId);    // 選択
    }
    updateReturnOrderUI();
}

// 戻す順番表示用のUIバッジ更新
function updateReturnOrderUI() {
    const items = document.querySelectorAll('#ptLookingGrid .pt-grid-item');
    items.forEach(item => {
        const cardId = item.dataset.cardId;
        const oIdx = ptReturnOrder.indexOf(cardId);
        let badge = item.querySelector('.badge-selected-count');
        
        if (oIdx !== -1) {
            item.classList.add('selected');
            if (!badge) {
                badge = document.createElement('div');
                badge.className = 'badge-selected-count';
                item.appendChild(badge);
            }
            badge.innerText = `${oIdx + 1}番目`;
            badge.style.display = 'flex';
        } else {
            item.classList.remove('selected');
            if (badge) badge.style.display = 'none';
        }
    });

    // 「選択した順に山札の下に置く」ボタンの制御
    const btn = document.getElementById('btn-return-ordered');
    if (btn) {
        if (ptReturnOrder.length === ptLookingCards.length) {
            btn.disabled = false;
            btn.style.opacity = '1';
            btn.innerText = `選択した順に山札の下に置く`;
        } else {
            btn.disabled = true;
            btn.style.opacity = '0.5';
            btn.innerText = `好きな順で山札の下に置く (${ptReturnOrder.length}/${ptLookingCards.length}選択中)`;
        }
    }
}

// 閲覧用モーダルの描画
function renderLookingCardsModal() {
    const modal = document.getElementById('ptListModal');
    const title = document.getElementById('ptModalTitle');
    const body = document.getElementById('ptModalBody');
    const footer = document.getElementById('ptModalFooter');

    title.innerText = `山札の上から閲覧中`;
    body.innerHTML = `
        <p style="margin-bottom:12px;">カードをクリックして「戻す順番（1番目が一番下）」を指定してください。各カード下のボタンから個別移動も可能です。</p>
        <div class="pt-grid" id="ptLookingGrid"></div>
    `;

    const grid = document.getElementById('ptLookingGrid');
    ptLookingCards.forEach(card => {
        const itemEl = document.createElement('div');
        itemEl.className = 'pt-grid-item';
        itemEl.dataset.cardId = card.id;
        itemEl.innerHTML = `
            <img src="${card.src}">
            <div class="card-qty">${card.name}</div>
            <div style="margin-top:6px; display:flex; gap:3px; justify-content:center;">
                <button class="btn-pt-primary" style="padding:2px 6px; font-size:10px;" onclick="event.stopPropagation(); ptMoveLookingCardDirectly('${card.id}', 'hand')">手札</button>
                <button class="btn-pt-primary" style="padding:2px 6px; font-size:10px;" onclick="event.stopPropagation(); ptMoveLookingCardDirectly('${card.id}', 'mana')">マナ</button>
                <button class="btn-pt-primary" style="padding:2px 6px; font-size:10px;" onclick="event.stopPropagation(); ptMoveLookingCardDirectly('${card.id}', 'graveyard')">墓地</button>
            </div>
        `;
        itemEl.onclick = () => selectReturnOrder(card.id);
        grid.appendChild(itemEl);
    });

    footer.innerHTML = `
        <button class="btn-pt-primary" onclick="ptAddMoreCardToLooking()">＋ さらに1枚見る</button>
        <button class="btn-pt-secondary" id="btn-return-ordered" onclick="ptReturnLookingCards('ordered_bottom')">好きな順で山札の下に置く</button>
        <button class="btn-pt-secondary" onclick="ptReturnLookingCards('shuffle_all')">すべて山札に戻してシャッフル</button>
        <button class="btn-pt-secondary" onclick="ptReturnLookingCards('shuffle_bottom')">すべてシャッフルして山札の下に置く</button>
    `;

    modal.style.display = 'flex';
    updateReturnOrderUI();
}

// 見ている最中に特定のカードのみを他のゾーンに直接移動させる処理
function ptMoveLookingCardDirectly(cardId, targetZone) {
    const idx = ptLookingCards.findIndex(c => c.id === cardId);
    if (idx === -1) return;
    
    let card = ptLookingCards.splice(idx, 1)[0];
    
    // 順序リストから削除
    const oIdx = ptReturnOrder.indexOf(cardId);
    if (oIdx !== -1) ptReturnOrder.splice(oIdx, 1);
    
    // 一時的に山札（deck）へ戻した状態にして、通常の移動処理(ptMoveCard)を実行
    ptState.deck.push(card);
    ptMoveCard(cardId, 'deck', targetZone);
    
    if (ptLookingCards.length === 0) {
        closePtModal();
    } else {
        renderLookingCardsModal();
    }
}

// 条件に応じて、閲覧中のカード群を山札に戻す
function ptReturnLookingCards(action) {
    if (action === 'ordered_bottom') {
        if (ptReturnOrder.length !== ptLookingCards.length) {
            alert("すべてのカードの順番を指定してください。");
            return;
        }
        // 1番目に選択したカードが一番下になるよう、順番に山札の底(unshift)へ戻す
        ptReturnOrder.forEach(cardId => {
            const card = ptLookingCards.find(c => c.id === cardId);
            if (card) {
                card.faceDown = true;
                ptState.deck.unshift(card); 
            }
        });
    } else if (action === 'shuffle_all') {
        // すべて山札の上に戻し、全体をシャッフル
        ptLookingCards.forEach(card => {
            card.faceDown = true;
            ptState.deck.push(card);
        });
        ptShuffle(ptState.deck);
    } else if (action === 'shuffle_bottom') {
        // 見ていたカード自体をシャッフルし、山札の底に戻す
        ptShuffle(ptLookingCards);
        ptLookingCards.forEach(card => {
            card.faceDown = true;
            ptState.deck.unshift(card); 
        });
    }

    ptLookingCards = [];
    ptReturnOrder = [];
    closePtModal();
    renderPtBoard();
}


// 画像パスを安全に解決する関数
function getCardImageUrl(imagepath) {
    if (!imagepath) {
        return '/images/card/backimage.webp';
    }
    if (imagepath.startsWith('http://') || imagepath.startsWith('https://')) {
        return imagepath;
    }
    let cleanPath = imagepath.replace(/\\/g, '/');
    if (cleanPath.startsWith('/')) {
        cleanPath = cleanPath.substring(1);
    }
    if (cleanPath.startsWith('images/card/')) {
        cleanPath = cleanPath.replace('images/card/', '');
    }
    return '/images/card/' + cleanPath;
}

let ptState = {
    deck: [], hand: [], mana: [], graveyard: [], battle: [], shield: [], psychic: [], gr: []
};
let ptDraggedCardId = null;
let selectedCards = new Set(); 
let isSelectMode = false;

window.onload = function() {
    try {
        initPlaytestGame();
    } catch (e) {
        console.error("Game init error:", e);
    }
    
    document.addEventListener('click', () => {
        document.getElementById('ptContextMenu').style.display = 'none';
    });
    
    document.getElementById('playtestBoard').addEventListener('dragover', (e) => {
        e.preventDefault();
    });
};

function initPlaytestGame() {
    ptState = { deck: [], hand: [], mana: [], graveyard: [], battle: [], shield: [], psychic: [], gr: [] };
    selectedCards.clear();
    
    let mainCards = [];
    let hasKindan = false;
    let hasForbiddenStar = false;

    initialCards.forEach((card, idx) => {
        if (!card) return;
        const qty = parseInt(card.quantity || 1);
        
        const src = getCardImageUrl(card.imagepath);
        const cardName = card.card_name || 'カード';

        if (cardName.includes('禁断 ～封印されしX～')) hasKindan = true;
        if (cardName.includes('FORBIDDEN STAR ～世界最後の日～')) hasForbiddenStar = true;

        // カンマ区切りのID群を数値配列に変換
        const cardtypeIds = (card.cardtype_ids || '').split(',').map(id => parseInt(id)).filter(id => !isNaN(id));
        const characteristicsIds = (card.characteristics_ids || '').split(',').map(id => parseInt(id)).filter(id => !isNaN(id));

        for (let i = 0; i < qty; i++) {
            let instance = {
                id: 'pt_' + Date.now() + '_' + Math.floor(Math.random() * 100000) + '_' + idx + '_' + i,
                card_id: card.card_id || '0',
                name: cardName,
                src: src,
                original_src: src,
                creature_src: card.combination_imagepath ? getCardImageUrl(card.combination_imagepath) : src,
                type: card.card_type_in_deck || 'main',
                twinpact: card.twinpact || '0',
                combination_id: card.combination_id || null, 
                is_multicolor: parseInt(card.civ_count || 1) >= 2, // ★追加：文明数が2以上なら多色と判定
                cardtype_ids: cardtypeIds,               
                characteristics_ids: characteristicsIds, 
                underCards: [], tapped: false, inverted: false, faceDown: false, flipped: false
            };

            if (instance.type === 'gr') {
                ptState.gr.push(instance);
            } else if (instance.type === 'super_dimensional') {
                ptState.psychic.push(instance);
            } else {
                mainCards.push(instance);
            }
        }
    });

    if (hasKindan && hasForbiddenStar) {
        document.getElementById('ptErrorOverlay').style.display = 'flex';
        return;
    }

    if (ptState.psychic.length > 0) {
        ptState.psychic.sort((a,b) => parseInt(a.card_id || 0) - parseInt(b.card_id || 0));
    }
    updatePsychicHeaderImage();
    if (ptState.gr.length > 0) {
        document.getElementById('pt-zone-gr').style.display = 'flex';
        ptShuffle(ptState.gr);
        document.getElementById('pt-zone-gr').innerHTML = `<div class="playtest-zone-title">超GR</div><img src="/images/card/backimage.webp" style="width:100%; height:100%; object-fit:fill;">`;
    }

    ptShuffle(mainCards);

    let startCard = null;
    let seals = 0;
    mainCards = mainCards.filter(c => {
        if (c.name.includes('禁断 ～封印されしX～')) { startCard = c; seals = 6; return false; }
        if (c.name.includes('FORBIDDEN STAR ～世界最後の日～')) { startCard = c; seals = 4; return false; }
        return true;
    });

    ptState.deck = mainCards;

    if (startCard) {
        // cardtype_idsのいずれかが6, 7, 8に含まれるか
        if (startCard.cardtype_ids.some(id => [6, 7, 8].includes(id))) {
            startCard.tapped = true;
        }
        ptState.battle.push(startCard);
        for(let i=0; i<seals; i++) {
            if (ptState.deck.length > 0) {
                let seal = ptState.deck.pop();
                seal.faceDown = true;
                startCard.underCards.push(seal);
            }
        }
    }

    if (ptState.deck.length >= 10) {
        for(let i=0; i<5; i++) {
            let shield = ptState.deck.pop();
            shield.faceDown = true;
            ptState.shield.push(shield);
        }
        for(let i=0; i<5; i++) {
            ptState.hand.push(ptState.deck.pop());
        }
    }

    renderPtBoard();
}

function ptShuffle(arr) {
    for (let i = arr.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [arr[i], arr[j]] = [arr[j], arr[i]];
    }
}

function updatePsychicHeaderImage() {
    const pz = document.getElementById('pt-zone-psychic');
    if (ptState.psychic.length > 0) {
        const minCard = ptState.psychic.reduce((prev, curr) => (parseInt(prev.card_id) < parseInt(curr.card_id)) ? prev : curr);
        pz.innerHTML = `<div class="playtest-zone-title">超次元</div><img src="${minCard.src}" style="width:100%; height:100%; object-fit:fill;">`;
    } else {
        pz.innerHTML = `<div class="playtest-zone-title">超次元</div><div class="playtest-zone-center-text">0</div>`;
    }
}

// 描画エラーをキャッチして処理を止めないように強化
function renderPtBoard() {
    try {
        document.getElementById('pt-deck-count').innerText = ptState.deck.length;
        document.getElementById('pt-graveyard-count').innerText = ptState.graveyard.length;

        const grave = document.getElementById('pt-zone-graveyard');
        if (ptState.graveyard.length > 0) {
            const last = ptState.graveyard[ptState.graveyard.length - 1];
            grave.style.backgroundImage = `url('${last.src}')`;
            grave.style.backgroundSize = 'cover';
            grave.innerHTML = `<div class="playtest-zone-title">墓地</div><div class="playtest-zone-center-text">${ptState.graveyard.length}</div>`;
        } else {
            grave.style.backgroundImage = 'none';
            grave.innerHTML = `<div class="playtest-zone-title">墓地</div><div class="playtest-zone-center-text">0</div>`;
        }
    } catch (e) {
        console.error("Count render error:", e);
    }

    try {
        updatePsychicHeaderImage();
    } catch (e) {
        console.error("Psychic header render error:", e);
    }

    try { renderZone('pt-zone-battle', ptState.battle, 'バトルゾーン'); } catch (e) { console.error("Battle zone render error:", e); }
    try { renderZone('pt-zone-shield', ptState.shield, 'シールドゾーン'); } catch (e) { console.error("Shield zone render error:", e); }
    try { renderZone('pt-zone-mana', ptState.mana, 'マナゾーン'); } catch (e) { console.error("Mana zone render error:", e); }
    try { renderZone('pt-zone-hand', ptState.hand, '手札'); } catch (e) { console.error("Hand zone render error:", e); }
}

// --- app/Views/deck/playtest.php (renderZone関数全体) ---
function renderZone(zoneId, arr, name) {
    const zone = document.getElementById(zoneId);
    zone.innerHTML = `<div class="playtest-zone-title">${name}</div>`;
    
    // 【手札の場合のみグルーピング表示】
    if (zoneId === 'pt-zone-hand') {
        const groups = {};
        arr.forEach(card => {
            const key = getCardGroupKey(card);
            if (!groups[key]) groups[key] = [];
            groups[key].push(card);
        });

        Object.keys(groups).forEach(key => {
            const groupCards = groups[key];
            const card = groupCards[0]; // 代表として1枚目を使用
            const count = groupCards.length;

            const el = document.createElement('div');
            el.className = 'playtest-card';
            el.id = card.id;
            el.draggable = true;
            
            if (card.tapped) el.classList.add('tapped');
            if (card.inverted) el.classList.add('inverted');
            if (card.faceDown) el.classList.add('face-down');
            if (selectedCards.has(card.id)) el.classList.add('selected');

            const displaySrc = card.faceDown ? '/images/card/backimage.webp' : card.src;
            el.innerHTML = `<img src="${displaySrc}">`;
            
            // まとめた枚数バッジを右下に配置（2枚以上の場合）
            if (count > 1) {
                const bCount = document.createElement('div');
                bCount.className = 'playtest-under-count';
                bCount.style.top = 'auto'; bCount.style.bottom = '-4px'; // 右下に配置
                bCount.style.background = '#007bff';
                bCount.innerText = count;
                el.appendChild(bCount);
            }

            el.ondragstart = (e) => {
                ptDraggedCardId = card.id; // ドラッグ時は代表のカードIDを移動
                el.classList.add('dragging');
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/plain', card.id);
            };

            el.ondragend = () => { el.classList.remove('dragging'); };
            el.ondragover = (e) => { e.preventDefault(); e.stopPropagation(); };

            el.onclick = (e) => {
                e.stopPropagation();
                if (isSelectMode) {
                    if (selectedCards.has(card.id)) {
                        selectedCards.delete(card.id);
                    } else {
                        selectedCards.add(card.id);
                    }
                    renderPtBoard();
                    return;
                }
                renderPtBoard();
            };

            el.oncontextmenu = (e) => {
                e.preventDefault();
                e.stopPropagation();
                openCardMenu(e, card, 'hand');
            };

            zone.appendChild(el);
        });
        return;
    }

    // 手札以外は従来の個別表示
    arr.forEach(card => {
        const el = document.createElement('div');
        el.className = 'playtest-card';
        el.id = card.id;
        el.draggable = true;
        
        if (card.tapped) el.classList.add('tapped');
        if (card.inverted) el.classList.add('inverted');
        if (card.faceDown) el.classList.add('face-down');
        if (selectedCards.has(card.id)) el.classList.add('selected');

        const displaySrc = card.faceDown ? '/images/card/backimage.webp' : card.src;
        el.innerHTML = `<img src="${displaySrc}">`;
        
        if (card.underCards.length > 0) {
            const b = document.createElement('div');
            b.className = 'playtest-under-count';
            b.innerText = card.underCards.length;
            el.appendChild(b);
        }

        el.ondragstart = (e) => {
            ptDraggedCardId = card.id;
            el.classList.add('dragging');
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', card.id);
        };

        el.ondragend = () => { el.classList.remove('dragging'); };
        el.ondragover = (e) => { e.preventDefault(); e.stopPropagation(); };

        el.onclick = (e) => {
            e.stopPropagation();
            if (isSelectMode) {
                if (selectedCards.has(card.id)) {
                    selectedCards.delete(card.id);
                } else {
                    selectedCards.add(card.id);
                }
                renderPtBoard();
                return;
            }

            const simpleZone = zoneId.replace('pt-zone-', '');
            if (simpleZone === 'battle') {
                if (card.faceDown) return; 
                if (card.name.includes('禁断 ～封印されしX～') || card.name.includes('FORBIDDEN STAR ～世界最後の日～')) {
                    ptFlipKindan(card);
                    return;
                }
                card.tapped = !card.tapped;
            } else if (simpleZone === 'mana') {
                card.tapped = !card.tapped;
            } else if (simpleZone === 'shield') {
                // ★直接移動ではなく、確認モーダルを開く処理に変更
                ptOpenShieldBreakModal(card);
            }
            renderPtBoard();
        };

        el.oncontextmenu = (e) => {
            e.preventDefault();
            e.stopPropagation();
            openCardMenu(e, card, zoneId.replace('pt-zone-', ''));
        };

        zone.appendChild(el);
    });
}

function ptFlipKindan(card) {
    if (!card.flipped) {
        card.src = card.creature_src;
        card.flipped = true;
    } else {
        card.src = card.original_src;
        card.flipped = false;
    }
    renderPtBoard();
}

function toggleSelectMode() {
    isSelectMode = !isSelectMode;
    if (!isSelectMode) {
        if (selectedCards.size > 0) {
            openBatchMenu();
        }
    }
}

function allowDrop(e) {
    e.preventDefault();
}

function handleDrop(e, targetZone) {
    e.preventDefault();
    e.stopPropagation();
    
    const id = e.dataTransfer.getData('text/plain') || ptDraggedCardId;
    if (!id) return;

    let fromZone = findCardZone(id);
    if (!fromZone || fromZone === targetZone) {
        ptDraggedCardId = null;
        return;
    }

    if (targetZone === 'battle') {
        if (fromZone === 'hand') {
            openHandToBattleMenu(id);
            ptDraggedCardId = null;
            return;
        } else if (fromZone === 'mana') {
            openManaToBattleMenu(id);
            ptDraggedCardId = null;
            return;
        }
    }

    ptMoveCard(id, fromZone, targetZone);
    ptDraggedCardId = null;
}

function findCardZone(cardId) {
    let found = '';
    ['hand','mana','battle','shield','graveyard','psychic','gr','deck'].forEach(z => {
        if (ptState[z] && ptState[z].some(c => c.id === cardId)) {
            found = z;
        }
    });
    return found;
}

function ptMoveCard(cardId, from, to, opts = {}) {
    let index = ptState[from].findIndex(c => c.id === cardId);
    if (index === -1) return;
    let card = ptState[from].splice(index, 1)[0];

    if (card.underCards && card.underCards.length > 0 && !opts.skipUnderCheck) {
        if (confirm("下にある重ねられたカードもすべて一緒に移動させますか？\n(「キャンセル」で上のカード1枚のみを移動し、下カードは墓地へ送ります)")) {
            card.underCards.forEach(u => {
                u.faceDown = false;
                ptState[to].push(u);
            });
            card.underCards = [];
        } else {
            card.underCards.forEach(u => {
                u.faceDown = false;
                ptState.graveyard.push(u);
            });
            card.underCards = [];
        }
    }

    // 特殊カード（GR・サイキック）移動制約
    const hasGR = card.characteristics_ids.includes(10);
    const hasPsychic = card.characteristics_ids.some(id => [3, 6].includes(id));
    if (to !== 'battle' && to !== 'psychic' && to !== 'gr') {
        if (hasGR) { 
            card.tapped = false; card.inverted = false; card.faceDown = false;
            ptState.gr.unshift(card); 
            renderPtBoard();
            return;
        }
        if (hasPsychic) { 
            card.tapped = false; card.inverted = false; card.faceDown = false;
            ptState.psychic.push(card);
            renderPtBoard();
            return;
        }
    }

    // ゾーンデフォルト角度設定
    if (to === 'battle' && card.cardtype_ids.some(id => [6, 7, 8].includes(id))) {
        card.tapped = true;
    } else if (to === 'mana') {
        // ★修正：マナゾーンに移動する際、多色カードなら強制的にタップ(タップイン)にする
        if (card.is_multicolor) {
            card.tapped = true;
        }
    } else {
        card.tapped = false;
    }

    if (to === 'hand') {
        card.inverted = false; card.faceDown = false;
        ptState.hand.push(card);
    } else if (to === 'mana') {
        card.inverted = true; card.faceDown = (opts.faceDown !== undefined) ? opts.faceDown : false;
        ptState.mana.push(card);
    } else if (to === 'battle') {
        card.inverted = false; card.faceDown = (opts.faceDown !== undefined) ? opts.faceDown : false;
        ptState.battle.push(card);
    } else if (to === 'shield') {
        card.inverted = false; card.faceDown = (opts.faceDown !== undefined) ? opts.faceDown : true;
        ptState.shield.push(card);
    } else if (to === 'graveyard') {
        card.inverted = false; card.faceDown = false;
        ptState.graveyard.push(card);
    } else if (to === 'deck') {
        card.inverted = false; card.faceDown = false;
        if (opts.bottom) {
            ptState.deck.unshift(card); 
        } else {
            ptState.deck.push(card); 
        }
    } else if (to === 'psychic') {
        card.inverted = false; card.faceDown = false;
        ptState.psychic.push(card);
    }

    renderPtBoard();
}

function ptDrawCard() {
    if (ptState.deck.length === 0) return;
    ptState.hand.push(ptState.deck.pop());
    renderPtBoard();
}

// コンテキストメニュー構築
// --- app/Views/deck/playtest.php (openCardMenu関数を以下に差し替え) ---
function openCardMenu(e, card, zone) {
    const menu = document.getElementById('ptContextMenu');
    menu.innerHTML = '';

    // すべてのカードメニュー共通で最上部に「拡大表示」を追加
    let menuHtml = `
        <li onclick="ptShowCardDetailModalById('${card.id}', '${zone}')">🔎 拡大表示</li>
        <hr>
    `;

    if (zone === 'battle') {
        if (card.faceDown) {
            menuHtml += `
                <li onclick="ptMoveCard('${card.id}', 'battle', 'hand')">手札に置く</li>
                <li onclick="ptMoveCard('${card.id}', 'battle', 'mana')">マナに置く</li>
                <li onclick="ptMoveCard('${card.id}', 'battle', 'shield')">シールドに置く</li>
                <li onclick="ptMoveCard('${card.id}', 'battle', 'graveyard')">墓地に置く</li>
                <li onclick="ptMoveCard('${card.id}', 'battle', 'deck', {bottom:true})">山札の下に置く</li>
            `;
        } else {
            menuHtml += `
                <li onclick="ptMoveCard('${card.id}', 'battle', 'hand')">手札に置く</li>
                <li onclick="ptMoveCard('${card.id}', 'battle', 'graveyard')">墓地に置く</li>
                <li onclick="ptMoveCard('${card.id}', 'battle', 'mana', {faceDown: false})">マナ（表）に置く</li>
                <li onclick="ptMoveCard('${card.id}', 'battle', 'mana', {faceDown: true})">マナ（裏）に置く</li>
                <li onclick="openBattleToShieldMenu('${card.id}')">シールドに置く...</li>
                <li onclick="openBattleToDeckMenu('${card.id}')">山札に置く...</li>
                <hr>
                <li onclick="ptStackUnderCard('${card.id}')">この下にカードを置く</li>
                <li onclick="ptStackOverCard('${card.id}')">この上にカードを置く</li>
                <li onclick="ptRotate180('${card.id}')">上下を裏返す(180度)</li>
            `;
        }
    } else if (zone === 'mana') {
        const title = card.faceDown ? '表向きにする' : '裏向きにする';
        menuHtml += `
            <li onclick="ptToggleFacedown('${card.id}')">${title}</li>
            <hr>
            <li onclick="ptMoveCard('${card.id}', 'mana', 'hand')">手札に置く</li>
            <li onclick="openManaToBattleMenu('${card.id}')">バトルゾーンに置く...</li>
            <li onclick="openManaToDeckMenu('${card.id}')">山札に置く...</li>
            <li onclick="ptMoveCard('${card.id}', 'mana', 'graveyard')">墓地に置く</li>
            <li onclick="ptMoveCard('${card.id}', 'mana', 'psychic')">超次元ゾーンに置く</li>
        `;
    } else if (zone === 'shield') {
        menuHtml += `
            <li onclick="ptMoveCard('${card.id}', 'shield', 'hand')">手札に加える</li>
            <li onclick="openShieldToBattleMenu('${card.id}')">バトルゾーンに置く...</li>
            <li onclick="openShieldToDeckMenu('${card.id}')">山札に置く...</li>
            <li onclick="ptMoveCard('${card.id}', 'shield', 'graveyard')">墓地に置く</li>
            <li onclick="ptMoveCard('${card.id}', 'shield', 'mana')">マナゾーンに置く</li>
            <li onclick="ptMoveCard('${card.id}', 'shield', 'psychic')">超次元ゾーンに置く</li>
        `;
    } else if (zone === 'hand') {
        menuHtml += `
            <li onclick="openHandToBattleMenu('${card.id}')">バトルゾーンに置く...</li>
            <li onclick="ptMoveCard('${card.id}', 'hand', 'mana')">マナに置く</li>
            <li onclick="ptMoveCard('${card.id}', 'hand', 'graveyard')">墓地に置く</li>
            <li onclick="ptMoveCard('${card.id}', 'hand', 'shield')">シールドに置く</li>
            <li onclick="openHandToDeckMenu('${card.id}')">山札に置く...</li>
        `;
    }

    menu.innerHTML = menuHtml;
    menu.style.display = 'block';
    adjustMenuPosition(e, menu);
}

function openPtDeckMenu(e) {
    e.preventDefault();
    e.stopPropagation();
    const menu = document.getElementById('ptContextMenu');
    menu.innerHTML = `
        <li onclick="ptViewDeck()">山札を見る (全体)</li>
        <li onclick="ptLookAtDeckTop(1)">山札の上から1枚見る</li>
        <li onclick="ptLookAtDeckTopPrompt()">山札の上から指定枚数見る</li>
        <li onclick="ptShuffleDeckAction()">山札をシャッフル</li>
        <hr>
        <li onclick="ptMoveDeckTopTo('mana')">1枚目をマナに置く</li>
        <li onclick="ptMoveDeckTopTo('graveyard')">1枚目を墓地に置く</li>
        <li onclick="ptMoveDeckTopTo('shield')">1枚目をシールド(裏)に置く</li>
        <li onclick="ptMoveDeckTopTo('battle')">1枚目をバトルゾーンに置く</li>
        <li onclick="ptMoveDeckTopTo('psychic')">1枚目を超次元ゾーンに置く</li>
    `;
    menu.style.display = 'block';
    adjustMenuPosition(e, menu);
}

function openPtGRMenu(e) {
    e.preventDefault();
    e.stopPropagation();
    const menu = document.getElementById('ptContextMenu');
    menu.innerHTML = `
        <li onclick="ptRevealGRTop()">表向きにする</li>
        <hr>
        <li onclick="ptMoveGRCardTo('hand')">手札に置く</li>
        <li onclick="ptMoveGRCardTo('mana')">マナに置く</li>
        <li onclick="ptMoveGRCardTo('shield')">シールドに置く</li>
        <li onclick="ptMoveGRCardTo('graveyard')">墓地に置く</li>
    `;
    menu.style.display = 'block';
    adjustMenuPosition(e, menu);
}

function adjustMenuPosition(e, menu) {
    let x = e.clientX + window.scrollX;
    let y = e.clientY + window.scrollY;
    
    const menuWidth = menu.offsetWidth || 180;
    const menuHeight = menu.offsetHeight || 200;
    
    if (e.clientX + menuWidth > window.innerWidth) {
        x = window.innerWidth - menuWidth - 10 + window.scrollX;
    }
    if (e.clientY + menuHeight > window.innerHeight) {
        y = window.innerHeight - menuHeight - 10 + window.scrollY;
    }
    
    menu.style.left = x + 'px';
    menu.style.top = y + 'px';
}

function ptToggleFacedown(id) {
    let found = null;
    ['mana', 'battle', 'shield'].forEach(z => {
        let card = ptState[z].find(c => c.id === id);
        if (card) found = card;
    });
    if (found) {
        found.faceDown = !found.faceDown;
        renderPtBoard();
    }
}

function ptRotate180(id) {
    let card = ptState.battle.find(c => c.id === id);
    if (card) {
        card.inverted = !card.inverted;
        renderPtBoard();
    }
}

function ptShuffleDeckAction() {
    ptShuffle(ptState.deck);
    alert("山札をシャッフルしました。");
    renderPtBoard();
}

function openHandToBattleMenu(id) {
    const modal = document.getElementById('ptListModal');
    const title = document.getElementById('ptModalTitle');
    const body = document.getElementById('ptModalBody');
    const footer = document.getElementById('ptModalFooter');

    title.innerText = "バトルゾーンの出し方";
    body.innerHTML = `
        <p>バトルゾーンへの出し方を選択してください。</p>
        <button class="btn-pt-primary" onclick="ptMoveCard('${id}', 'hand', 'battle'); closePtModal();" style="width:100%; margin-bottom:10px;">単体でバトルゾーンに置く</button>
        <button class="btn-pt-secondary" onclick="openStackFromHandSelector('${id}')" style="width:100%;">他カードの上に重ねる（進化元を下に束ねる）</button>
    `;
    footer.innerHTML = `<button class="btn-pt-secondary" onclick="closePtModal()">閉じる</button>`;
    modal.style.display = 'flex';
}

function openStackFromHandSelector(id) {
    const title = document.getElementById('ptModalTitle');
    const body = document.getElementById('ptModalBody');
    const footer = document.getElementById('ptModalFooter');

    title.innerText = "下に重ねるカードの選択";
    body.innerHTML = `<p>下に重ねてバトルゾーンに出すカードを、他のゾーンから選択してください。</p><div class="pt-grid" id="ptStackGrid"></div>`;
    
    const grid = document.getElementById('ptStackGrid');
    let targets = [];

    ['mana', 'graveyard', 'shield'].forEach(zone => {
        ptState[zone].forEach(c => {
            targets.push({ card: c, zone: zone });
        });
    });

    targets.forEach(item => {
        const itemEl = document.createElement('div');
        itemEl.className = 'pt-grid-item';
        itemEl.innerHTML = `
            <img src="${item.card.src}">
            <div class="card-qty">${item.card.name} (${zoneJapaneseName(item.zone)})</div>
        `;
        itemEl.onclick = () => {
            itemEl.classList.toggle('selected');
        };
        itemEl.dataset.cardId = item.card.id;
        itemEl.dataset.zone = item.zone;
        grid.appendChild(itemEl);
    });

    footer.innerHTML = `
        <button class="btn-pt-secondary" onclick="closePtModal()">キャンセル</button>
        <button class="btn-pt-primary" onclick="ptExecuteStackFromHand('${id}')">選択したカードを下に重ねて出す</button>
    `;
}

function ptExecuteStackFromHand(handCardId) {
    const items = document.querySelectorAll('#ptStackGrid .pt-grid-item.selected');
    let handCard = ptState.hand.find(c => c.id === handCardId);
    if (!handCard) return;

    ptMoveCard(handCardId, 'hand', 'battle', {skipUnderCheck: true});
    
    items.forEach(el => {
        const cid = el.dataset.cardId;
        const fromZone = el.dataset.zone;
        const index = ptState[fromZone].findIndex(c => c.id === cid);
        if (index !== -1) {
            let underC = ptState[fromZone].splice(index, 1)[0];
            underC.faceDown = true;
            handCard.underCards.push(underC);
        }
    });

    closePtModal();
    renderPtBoard();
}

// --- app/Views/deck/playtest.php (山札関連関数群の差し替え) ---
// --- app/Views/deck/playtest.php (山札関連の3関数を以下に差し替え) ---
function ptViewDeck() {
    const modal = document.getElementById('ptListModal');
    const title = document.getElementById('ptModalTitle');
    const body = document.getElementById('ptModalBody');
    const footer = document.getElementById('ptModalFooter');

    title.innerText = "山札を見る";
    body.innerHTML = `
        <p>カードをクリックして選択（枚数指定可能）し、下のボタンから操作してください。</p>
        <div class="pt-grid" id="ptDeckViewGrid"></div>
    `;

    const grid = document.getElementById('ptDeckViewGrid');
    const reversedDeck = [...ptState.deck].reverse();
    
    const groups = {};
    reversedDeck.forEach(card => {
        const key = getCardGroupKey(card);
        if (!groups[key]) {
            groups[key] = { card: card, ids: [] };
        }
        groups[key].ids.push(card.id);
    });

    Object.keys(groups).forEach(key => {
        const group = groups[key];
        const itemEl = document.createElement('div');
        itemEl.className = 'pt-grid-item';
        itemEl.innerHTML = `
            <img src="${group.card.src}">
            <div class="badge-count">${group.ids.length}</div>
            <div class="card-qty">${group.card.name}</div>
        `;
        // 追加したヘルパー関数を呼び出す
        itemEl.onclick = () => handleGroupItemClick(itemEl, group.ids);
        grid.appendChild(itemEl);
    });

    footer.innerHTML = `
        <button class="btn-pt-secondary" onclick="ptBatchDeckAction('top')">山札の一番上に戻す</button>
        <button class="btn-pt-secondary" onclick="ptBatchDeckAction('bottom')">山札の一番下に置く</button>
        <button class="btn-pt-secondary" onclick="ptBatchDeckAction('shuffle_deck')">山札に加えてシャッフル</button>
        <button class="btn-pt-primary" onclick="ptBatchMoveFromDeck('hand')">手札に加える</button>
        <button class="btn-pt-primary" onclick="ptBatchMoveFromDeck('battle')">バトルゾーンに置く</button>
        <button class="btn-pt-primary" onclick="ptBatchMoveFromDeck('mana')">マナに置く</button>
        <button class="btn-pt-primary" onclick="ptBatchMoveFromDeck('graveyard')">墓地に置く</button>
        <button class="btn-pt-primary" onclick="ptBatchMoveFromDeck('shield_select')">シールドに置く...</button>
    `;
    modal.style.display = 'flex';
}

function ptBatchMoveFromDeck(targetZone) {
    const items = document.querySelectorAll('#ptDeckViewGrid .pt-grid-item.selected');
    if (items.length === 0) { alert('カードを選択してください。'); return; }

    let allIds = [];
    items.forEach(item => {
        // data-selected-idsから「実際に選択した分」のID配列を取得
        const ids = JSON.parse(item.getAttribute('data-selected-ids') || '[]');
        allIds = allIds.concat(ids);
    });

    if (targetZone === 'shield_select') {
        const isFaceup = confirm("シールドに「表向き」で置きますか？\n(「キャンセル」で裏向きに置きます)");
        allIds.forEach(id => {
            ptMoveCard(id, 'deck', 'shield', { faceDown: !isFaceup });
        });
    } else {
        allIds.forEach(id => {
            ptMoveCard(id, 'deck', targetZone);
        });
    }
    closePtModal();
    renderPtBoard();
}

function ptBatchDeckAction(action) {
    const items = document.querySelectorAll('#ptDeckViewGrid .pt-grid-item.selected');
    if (items.length === 0) { alert('カードを選択してください。'); return; }

    let selectedIds = [];
    items.forEach(item => {
        const ids = JSON.parse(item.getAttribute('data-selected-ids') || '[]');
        selectedIds = selectedIds.concat(ids);
    });

    let extracted = [];
    ptState.deck = ptState.deck.filter(c => {
        if (selectedIds.includes(c.id)) {
            extracted.push(c);
            return false;
        }
        return true;
    });

    if (action === 'top') {
        extracted.forEach(c => ptState.deck.push(c));
    } else if (action === 'bottom') {
        extracted.reverse().forEach(c => ptState.deck.unshift(c));
    } else if (action === 'shuffle_deck') {
        extracted.forEach(c => ptState.deck.push(c));
        ptShuffle(ptState.deck);
    }
    closePtModal();
    renderPtBoard();
}


function ptPlayGRAny() {
    if (ptState.gr.length === 0) return;
    let card = ptState.gr.pop();
    card.tapped = false;
    ptState.battle.push(card);
    renderPtBoard();
}

function ptRevealGRTop() {
    if (ptState.gr.length === 0) return;
    const card = ptState.gr[ptState.gr.length - 1];
    
    const modal = document.getElementById('ptListModal');
    const title = document.getElementById('ptModalTitle');
    const body = document.getElementById('ptModalBody');
    const footer = document.getElementById('ptModalFooter');

    title.innerText = "超GRの一番上 (表向き)";
    body.innerHTML = `
        <div style="text-align:center;">
            <img src="${card.src}" style="width:130px; height:182px; border-radius:6px; margin-bottom:15px;">
            <p>${card.name}</p>
        </div>
    `;

    footer.innerHTML = `
        <button class="btn-pt-primary" onclick="ptMoveCard('${card.id}', 'gr', 'battle'); closePtModal();">バトルゾーンに置く</button>
        <button class="btn-pt-secondary" onclick="closePtModal();">超GRの一番下に戻す</button>
    `;
    modal.style.display = 'flex';
}

function ptMoveGRCardTo(targetZone) {
    if (ptState.gr.length === 0) return;
    const card = ptState.gr.pop();
    card.tapped = false;
    ptState.gr.unshift(card);
    alert(`ルールにより、バトルゾーン以外の超GRのカードは一番下に戻されました。`);
    renderPtBoard();
}

function openManaToBattleMenu(id) {
    const modal = document.getElementById('ptListModal');
    const title = document.getElementById('ptModalTitle');
    const body = document.getElementById('ptModalBody');
    const footer = document.getElementById('ptModalFooter');

    title.innerText = "マナからバトルゾーンに出す";
    body.innerHTML = `
        <button class="btn-pt-primary" onclick="ptMoveCard('${id}', 'mana', 'battle'); closePtModal();" style="width:100%; margin-bottom:10px;">単体でバトルゾーンに置く</button>
        <button class="btn-pt-secondary" onclick="openStackFromManaSelector('${id}')" style="width:100%;">バトルゾーンにあるカードの下に重ねる</button>
    `;
    footer.innerHTML = `<button class="btn-pt-secondary" onclick="closePtModal()">閉じる</button>`;
    modal.style.display = 'flex';
}

function openStackFromManaSelector(id) {
    const title = document.getElementById('ptModalTitle');
    const body = document.getElementById('ptModalBody');
    const footer = document.getElementById('ptModalFooter');

    title.innerText = "重ねる対象の選択";
    body.innerHTML = `<p>マナのカードを重ねる、バトルゾーンのカードを選択してください。</p><div class="pt-grid" id="ptBattleTargetGrid"></div>`;
    
    const grid = document.getElementById('ptBattleTargetGrid');
    ptState.battle.forEach(bCard => {
        const itemEl = document.createElement('div');
        itemEl.className = 'pt-grid-item';
        itemEl.innerHTML = `<img src="${bCard.src}"><div class="card-qty">${bCard.name}</div>`;
        itemEl.onclick = () => {
            document.querySelectorAll('#ptBattleTargetGrid .pt-grid-item').forEach(el => el.classList.remove('selected'));
            itemEl.classList.add('selected');
        };
        itemEl.dataset.cardId = bCard.id;
        grid.appendChild(itemEl);
    });

    footer.innerHTML = `
        <button class="btn-pt-secondary" onclick="closePtModal()">キャンセル</button>
        <button class="btn-pt-primary" onclick="ptExecuteStackFromMana('${id}')">重ねてバトルゾーンに置く</button>
    `;
}

function ptExecuteStackFromMana(manaCardId) {
    const selected = document.querySelector('#ptBattleTargetGrid .pt-grid-item.selected');
    if (!selected) { alert('対象カードを選択してください。'); return; }
    
    const bCardId = selected.dataset.cardId;
    let bCard = ptState.battle.find(c => c.id === bCardId);
    let manaIndex = ptState.mana.findIndex(c => c.id === manaCardId);

    if (bCard && manaIndex !== -1) {
        let manaCard = ptState.mana.splice(manaIndex, 1)[0];
        manaCard.faceDown = true;
        bCard.underCards.push(manaCard);
    }
    closePtModal();
    renderPtBoard();
}

function openShieldToBattleMenu(id) {
    const modal = document.getElementById('ptListModal');
    const title = document.getElementById('ptModalTitle');
    const body = document.getElementById('ptModalBody');
    const footer = document.getElementById('ptModalFooter');

    title.innerText = "シールドをバトルゾーンに置く";
    body.innerHTML = `
        <button class="btn-pt-primary" onclick="ptMoveCard('${id}', 'shield', 'battle'); closePtModal();" style="width:100%; margin-bottom:10px;">そのまま置く</button>
        <button class="btn-pt-secondary" onclick="openShieldReplaceSelector('${id}')" style="width:100%;">バトルゾーンのカードと入れ替える</button>
    `;
    footer.innerHTML = `<button class="btn-pt-secondary" onclick="closePtModal()">閉じる</button>`;
    modal.style.display = 'flex';
}

function openShieldReplaceSelector(id) {
    const title = document.getElementById('ptModalTitle');
    const body = document.getElementById('ptModalBody');
    const footer = document.getElementById('ptModalFooter');

    title.innerText = "入れ替える対象を選択";
    body.innerHTML = `<p>シールドカードと入れ替えて墓地等に送る、バトルゾーンのカードを選択してください。</p><div class="pt-grid" id="ptReplaceGrid"></div>`;
    
    const grid = document.getElementById('ptReplaceGrid');
    ptState.battle.forEach(bCard => {
        const itemEl = document.createElement('div');
        itemEl.className = 'pt-grid-item';
        itemEl.innerHTML = `<img src="${bCard.src}"><div class="card-qty">${bCard.name}</div>`;
        itemEl.onclick = () => {
            document.querySelectorAll('#ptReplaceGrid .pt-grid-item').forEach(el => el.classList.remove('selected'));
            itemEl.classList.add('selected');
        };
        itemEl.dataset.cardId = bCard.id;
        grid.appendChild(itemEl);
    });

    footer.innerHTML = `
        <button class="btn-pt-secondary" onclick="closePtModal()">キャンセル</button>
        <button class="btn-pt-primary" onclick="ptExecuteShieldReplace('${id}')">入れ替えを実行</button>
    `;
}

function ptExecuteShieldReplace(shieldCardId) {
    const selected = document.querySelector('#ptReplaceGrid .pt-grid-item.selected');
    if (!selected) { alert('対象カードを選択してください。'); return; }

    const bCardId = selected.dataset.cardId;
    ptMoveCard(bCardId, 'battle', 'graveyard');
    ptMoveCard(shieldCardId, 'shield', 'battle');

    closePtModal();
    renderPtBoard();
}

function openManaToDeckMenu(id) {
    const modal = document.getElementById('ptListModal');
    const title = document.getElementById('ptModalTitle');
    const body = document.getElementById('ptModalBody');
    const footer = document.getElementById('ptModalFooter');

    title.innerText = "マナを山札へ移動";
    body.innerHTML = `
        <button class="btn-pt-primary" onclick="ptMoveCard('${id}', 'mana', 'deck'); closePtModal();" style="width:100%; margin-bottom:10px;">山札の一番上に置く</button>
        <button class="btn-pt-primary" onclick="ptMoveCard('${id}', 'mana', 'deck', {bottom:true}); closePtModal();" style="width:100%; margin-bottom:10px;">山札の一番下に置く</button>
        <button class="btn-pt-secondary" onclick="ptMoveCard('${id}', 'mana', 'deck'); ptShuffle(ptState.deck); closePtModal();" style="width:100%;">山札に加えてシャッフル</button>
    `;
    footer.innerHTML = `<button class="btn-pt-secondary" onclick="closePtModal()">閉じる</button>`;
    modal.style.display = 'flex';
}

function openShieldToDeckMenu(id) {
    const modal = document.getElementById('ptListModal');
    const title = document.getElementById('ptModalTitle');
    const body = document.getElementById('ptModalBody');
    const footer = document.getElementById('ptModalFooter');

    title.innerText = "シールドを山札へ移動";
    body.innerHTML = `
        <button class="btn-pt-primary" onclick="ptMoveCard('${id}', 'shield', 'deck', {bottom:true}); closePtModal();" style="width:100%; margin-bottom:10px;">山札の一番下に置く</button>
        <button class="btn-pt-secondary" onclick="ptMoveCard('${id}', 'shield', 'deck'); ptShuffle(ptState.deck); closePtModal();" style="width:100%;">山札に加えてシャッフル</button>
    `;
    footer.innerHTML = `<button class="btn-pt-secondary" onclick="closePtModal()">閉じる</button>`;
    modal.style.display = 'flex';
}

function openHandToDeckMenu(id) {
    const modal = document.getElementById('ptListModal');
    const title = document.getElementById('ptModalTitle');
    const body = document.getElementById('ptModalBody');
    const footer = document.getElementById('ptModalFooter');

    title.innerText = "手札を山札へ移動";
    body.innerHTML = `
        <button class="btn-pt-primary" onclick="ptMoveCard('${id}', 'hand', 'deck'); closePtModal();" style="width:100%; margin-bottom:10px;">山札の一番上に置く</button>
        <button class="btn-pt-primary" onclick="ptMoveCard('${id}', 'hand', 'deck', {bottom:true}); closePtModal();" style="width:100%; margin-bottom:10px;">山札の一番下に置く</button>
        <button class="btn-pt-secondary" onclick="ptMoveCard('${id}', 'hand', 'deck'); ptShuffle(ptState.deck); closePtModal();" style="width:100%;">山札に加えてシャッフル</button>
    `;
    footer.innerHTML = `<button class="btn-pt-secondary" onclick="closePtModal()">閉じる</button>`;
    modal.style.display = 'flex';
}

function openBattleToDeckMenu(id) {
    const modal = document.getElementById('ptListModal');
    const title = document.getElementById('ptModalTitle');
    const body = document.getElementById('ptModalBody');
    const footer = document.getElementById('ptModalFooter');

    title.innerText = "バトルゾーンを山札へ移動";
    body.innerHTML = `
        <button class="btn-pt-primary" onclick="ptMoveCard('${id}', 'battle', 'deck', {bottom:true}); closePtModal();" style="width:100%; margin-bottom:10px;">山札の一番下に置く</button>
        <button class="btn-pt-secondary" onclick="ptMoveCard('${id}', 'battle', 'deck'); ptShuffle(ptState.deck); closePtModal();" style="width:100%;">山札に加えてシャッフル</button>
    `;
    footer.innerHTML = `<button class="btn-pt-secondary" onclick="closePtModal()">閉じる</button>`;
    modal.style.display = 'flex';
}

function openBattleToShieldMenu(id) {
    const modal = document.getElementById('ptListModal');
    const title = document.getElementById('ptModalTitle');
    const body = document.getElementById('ptModalBody');
    const footer = document.getElementById('ptModalFooter');

    title.innerText = "バトルゾーンからシールドに置く";
    body.innerHTML = `
        <button class="btn-pt-primary" onclick="ptMoveCard('${id}', 'battle', 'shield', {faceDown: true}); closePtModal();" style="width:100%; margin-bottom:10px;">裏向きで置く</button>
        <button class="btn-pt-primary" onclick="ptMoveCard('${id}', 'battle', 'shield', {faceDown: false}); closePtModal();" style="width:100%; margin-bottom:10px;">表向きで置く</button>
    `;
    footer.innerHTML = `<button class="btn-pt-secondary" onclick="closePtModal()">閉じる</button>`;
    modal.style.display = 'flex';
}

function ptStackUnderCard(id) {
    openStackFromManaSelector(id); 
}

function ptStackOverCard(id) {
    const modal = document.getElementById('ptListModal');
    const title = document.getElementById('ptModalTitle');
    const body = document.getElementById('ptModalBody');
    const footer = document.getElementById('ptModalFooter');

    title.innerText = "上に重ねるカードを選択";
    body.innerHTML = `<p>カードの上に重ねて新しく一番上に置くカードを選択してください。</p><div class="pt-grid" id="ptOverStackGrid"></div>`;
    
    const grid = document.getElementById('ptOverStackGrid');
    let targets = [];

    ['hand', 'mana', 'graveyard'].forEach(zone => {
        ptState[zone].forEach(c => {
            targets.push({ card: c, zone: zone });
        });
    });

    targets.forEach(item => {
        const itemEl = document.createElement('div');
        itemEl.className = 'pt-grid-item';
        itemEl.innerHTML = `<img src="${item.card.src}"><div class="card-qty">${item.card.name} (${zoneJapaneseName(item.zone)})</div>`;
        itemEl.onclick = () => {
            document.querySelectorAll('#ptOverStackGrid .pt-grid-item').forEach(el => el.classList.remove('selected'));
            itemEl.classList.add('selected');
        };
        itemEl.dataset.cardId = item.card.id;
        itemEl.dataset.zone = item.zone;
        grid.appendChild(itemEl);
    });

    footer.innerHTML = `
        <button class="btn-pt-secondary" onclick="closePtModal()">キャンセル</button>
        <button class="btn-pt-primary" onclick="ptExecuteStackOver('${id}')">この上に重ねて置く</button>
    `;
    modal.style.display = 'flex';
}

function ptExecuteStackOver(baseCardId) {
    const selected = document.querySelector('#ptOverStackGrid .pt-grid-item.selected');
    if (!selected) { alert('重ねるカードを選択してください。'); return; }

    const newTopId = selected.dataset.cardId;
    const fromZone = selected.dataset.zone;

    let baseCardIndex = ptState.battle.findIndex(c => c.id === baseCardId);
    let newTopIndex = ptState[fromZone].findIndex(c => c.id === newTopId);

    if (baseCardIndex !== -1 && newTopIndex !== -1) {
        let baseCard = ptState.battle[baseCardIndex];
        let newTopCard = ptState[fromZone].splice(newTopIndex, 1)[0];

        newTopCard.underCards = [...baseCard.underCards];
        baseCard.underCards = [];
        baseCard.faceDown = true;
        newTopCard.underCards.push(baseCard);

        ptState.battle[baseCardIndex] = newTopCard;
    }

    closePtModal();
    renderPtBoard();
}

function openPtGraveyardViewer() {
    openGroupedViewer("墓地", ptState.graveyard, 'graveyard');
}

function openPtPsychicViewer() {
    openGroupedViewer("超次元ゾーン", ptState.psychic, 'psychic');
}

// --- app/Views/deck/playtest.php (openGroupedViewer関数を以下に差し替え) ---
function openGroupedViewer(titleText, cardArray, zoneKey) {
    const modal = document.getElementById('ptListModal');
    const title = document.getElementById('ptModalTitle');
    const body = document.getElementById('ptModalBody');
    const footer = document.getElementById('ptModalFooter');

    title.innerText = `${titleText}一覧`;
    body.innerHTML = `
        <p style="margin-bottom:12px;">カードをクリックして選択（クリックごとに枚数指定）し、移動先を選んでください。</p>
        <div class="pt-grid" id="ptViewerGrid"></div>
    `;

    const grid = document.getElementById('ptViewerGrid');
    
    // 山札と同じ判定ルール(getCardGroupKey)を適用してグループ分け
    const groups = {};
    cardArray.forEach(c => {
        const key = getCardGroupKey(c);
        if (!groups[key]) {
            groups[key] = { card: c, ids: [] };
        }
        groups[key].ids.push(c.id);
    });

    Object.keys(groups).forEach(key => {
        const group = groups[key];
        const itemEl = document.createElement('div');
        itemEl.className = 'pt-grid-item';
        itemEl.innerHTML = `
            <img src="${group.card.src}">
            <div class="badge-count">${group.ids.length}</div>
            <div class="card-qty">${group.card.name}</div>
        `;
        // クリックした数だけ1枚ずつ選択する動作をバインド
        itemEl.onclick = () => handleGroupItemClick(itemEl, group.ids);
        grid.appendChild(itemEl);
    });

    footer.innerHTML = `
        <button class="btn-pt-secondary" onclick="closePtModal()">閉じる</button>
        <button class="btn-pt-primary" onclick="ptBatchMoveFromViewer('${zoneKey}', 'hand')">手札に戻す</button>
        <button class="btn-pt-primary" onclick="ptBatchMoveFromViewer('${zoneKey}', 'battle')">バトルゾーンに置く</button>
        <button class="btn-pt-primary" onclick="ptBatchMoveFromViewer('${zoneKey}', 'mana')">マナに置く</button>
        <button class="btn-pt-primary" onclick="ptBatchMoveFromViewer('${zoneKey}', 'shield')">シールドに置く</button>
        <button class="btn-pt-primary" onclick="ptBatchMoveFromViewer('${zoneKey}', 'deck')">山札に置く...</button>
    `;
    modal.style.display = 'flex';
}

// --- app/Views/deck/playtest.php (ptBatchMoveFromViewer関数を以下に差し替え) ---
function ptBatchMoveFromViewer(fromZone, targetZone) {
    const selectedItems = document.querySelectorAll('#ptViewerGrid .pt-grid-item.selected');
    if (selectedItems.length === 0) { alert('カードを選択してください。'); return; }

    let allIds = [];
    selectedItems.forEach(item => {
        // data-selected-idsから、実際に選択（確定）された枚数分のIDだけを抽出
        const ids = JSON.parse(item.getAttribute('data-selected-ids') || '[]');
        allIds = allIds.concat(ids);
    });

    if (targetZone === 'deck') {
        const action = confirm("選択したカードを山札に加えて「シャッフル」しますか？\n(「キャンセル」でシャッフルして山札の『一番下』に置きます)");
        if (action) {
            allIds.forEach(id => ptMoveCard(id, fromZone, 'deck', {skipUnderCheck:true}));
            ptShuffle(ptState.deck);
        } else {
            allIds.forEach(id => ptMoveCard(id, fromZone, 'deck', {bottom:true, skipUnderCheck:true}));
            ptShuffle(ptState.deck);
        }
    } else {
        allIds.forEach(id => {
            ptMoveCard(id, fromZone, targetZone, {skipUnderCheck:true});
        });
    }

    closePtModal();
    renderPtBoard();
}

function openBatchMenu() {
    const modal = document.getElementById('ptListModal');
    const title = document.getElementById('ptModalTitle');
    const body = document.getElementById('ptModalBody');
    const footer = document.getElementById('ptModalFooter');

    title.innerText = "選択カードの一括操作";
    body.innerHTML = `
        <p>現在 ${selectedCards.size} 枚のカードが選択されています。移動先を選んでください。</p>
        <button class="btn-pt-primary" onclick="ptExecuteBatchMove('hand')" style="width:100%; margin-bottom:10px;">一括して手札に移動</button>
        <button class="btn-pt-primary" onclick="ptExecuteBatchMove('mana')" style="width:100%; margin-bottom:10px;">一括してマナに移動</button>
        <button class="btn-pt-primary" onclick="ptExecuteBatchMove('graveyard')" style="width:100%; margin-bottom:10px;">一括して墓地に移動</button>
        <button class="btn-pt-primary" onclick="ptExecuteBatchMove('battle')" style="width:100%; margin-bottom:10px;">一括してバトルゾーンに移動</button>
        <button class="btn-pt-primary" onclick="ptExecuteBatchMove('shield')" style="width:100%; margin-bottom:10px;">一括してシールドに移動</button>
        <button class="btn-pt-primary" onclick="ptExecuteBatchMove('deck')" style="width:100%;">一括して山札に戻す...</button>
    `;

    footer.innerHTML = `
        <button class="btn-pt-secondary" onclick="selectedCards.clear(); renderPtBoard(); closePtModal();">選択解除して閉じる</button>
    `;
    modal.style.display = 'flex';
}

function ptExecuteBatchMove(toZone) {
    if (toZone === 'deck') {
        const action = confirm("山札に加えて「シャッフル」しますか？\n(「キャンセル」でシャッフルして山札の『一番下』に置きます)");
        selectedCards.forEach(id => {
            const from = findCardZone(id);
            if (from) {
                if (action) {
                    ptMoveCard(id, from, 'deck');
                } else {
                    ptMoveCard(id, from, 'deck', {bottom: true});
                }
            }
        });
        ptShuffle(ptState.deck);
    } else {
        selectedCards.forEach(id => {
            const from = findCardZone(id);
            if (from) ptMoveCard(id, from, toZone);
        });
    }
    selectedCards.clear();
    closePtModal();
    renderPtBoard();
}

function zoneJapaneseName(key) {
    switch (key) {
        case 'hand': return '手札';
        case 'mana': return 'マナ';
        case 'battle': return 'バトルゾーン';
        case 'shield': return 'シールド';
        case 'graveyard': return '墓地';
        case 'psychic': return '超次元';
        case 'gr': return '超GR';
        default: return 'その他';
    }
}

function closePtModal() {
    document.getElementById('ptListModal').style.display = 'none';
}

// --- app/Views/deck/playtest.php (JS内へ追加) ---
function getCardGroupKey(card) {
    const isTwin = card.twinpact === '1' || card.twinpact === 1 || card.twinpact === true || card.twinpact === 'true';
    if (isTwin) {
        return card.combination_id ? 'twin_' + card.combination_id : 'twin_name_' + card.name;
    }
    return 'normal_' + card.name;
}
// --- app/Views/deck/playtest.php (handleGroupItemClick関数を以下に差し替え) ---
function handleGroupItemClick(itemEl, allIds) {
    const max = allIds.length;
    
    // 現在選択されているIDの配列を属性から取得（無ければ空配列）
    const currentSelectedIds = JSON.parse(itemEl.getAttribute('data-selected-ids') || '[]');
    const currentCount = currentSelectedIds.length;
    
    // クリックごとに選択枚数を1枚増やす
    let nextCount = currentCount + 1;
    
    // 最大枚数を超えたら選択解除（0枚）にする
    if (nextCount > max) {
        nextCount = 0; 
    }

    if (nextCount > 0) {
        // 指定枚数分だけのIDを抽出してセット
        const selectedIds = allIds.slice(0, nextCount);
        itemEl.classList.add('selected');
        itemEl.setAttribute('data-selected-ids', JSON.stringify(selectedIds));
        updateItemSelectionBadge(itemEl, nextCount);
    } else {
        // 0枚（解除）の場合
        itemEl.classList.remove('selected');
        itemEl.removeAttribute('data-selected-ids');
        updateItemSelectionBadge(itemEl, 0);
    }
}

function updateItemSelectionBadge(itemEl, selectedCount) {
    let selectBadge = itemEl.querySelector('.badge-selected-count');
    if (selectedCount > 0) {
        if (!selectBadge) {
            selectBadge = document.createElement('div');
            selectBadge.className = 'badge-selected-count';
            itemEl.appendChild(selectBadge);
        }
        selectBadge.innerText = `${selectedCount}枚選択`;
        selectBadge.style.display = 'flex';
    } else {
        if (selectBadge) selectBadge.style.display = 'none';
    }
}

// --- app/Views/deck/playtest.php (JS内へ新規追加する関数群) ---
// アンタップ選択モーダルを開く
function openUntapMenu() {
    const modal = document.getElementById('ptListModal');
    const title = document.getElementById('ptModalTitle');
    const body = document.getElementById('ptModalBody');
    const footer = document.getElementById('ptModalFooter');

    title.innerText = "カードのアンタップ";
    body.innerHTML = `
        <p style="margin-bottom:15px;">アンタップする対象を選択してください。</p>
        <button class="btn-pt-primary" onclick="ptExecuteUntap('all'); closePtModal();" style="width:100%; padding:10px; font-weight:bold; margin-bottom:10px;">すべてアンタップ (バトル ＆ マナ)</button>
        <button class="btn-pt-secondary" onclick="ptExecuteUntap('battle'); closePtModal();" style="width:100%; padding:8px; margin-bottom:10px;">バトルゾーンのみアンタップ</button>
        <button class="btn-pt-secondary" onclick="ptExecuteUntap('mana'); closePtModal();" style="width:100%; padding:8px;">マナゾーンのみアンタップ</button>
    `;
    footer.innerHTML = `<button class="btn-pt-secondary" onclick="closePtModal()">キャンセル</button>`;
    modal.style.display = 'flex';
}

// 指定されたゾーンのカードをアンタップ実行
function ptExecuteUntap(target) {
    let updated = false;

    // バトルゾーンのアンタップ
    if (target === 'battle' || target === 'all') {
        ptState.battle.forEach(card => {
            if (card.tapped) {
                card.tapped = false;
                updated = true;
            }
        });
    }

    // マナゾーンのアンタップ
    if (target === 'mana' || target === 'all') {
        ptState.mana.forEach(card => {
            if (card.tapped) {
                card.tapped = false;
                updated = true;
            }
        });
    }

    if (updated) {
        renderPtBoard();
    }
}

// --- app/Views/deck/playtest.php (JS内へ新規追加する関数) ---
function ptOpenShieldBreakModal(card) {
    const modal = document.getElementById('ptListModal');
    const title = document.getElementById('ptModalTitle');
    const body = document.getElementById('ptModalBody');
    const footer = document.getElementById('ptModalFooter');

    title.innerText = "手札に加えられるシールド";
    body.innerHTML = `
        <div style="text-align:center; padding: 10px 0;">
            <!-- 裏向きのシールドですが、回収時に表向きで確認するためcard.src(本来の画像)で表示します -->
            <img src="${card.src}" style="width:160px; height:224px; border-radius:6px; box-shadow:0 4px 15px rgba(0,0,0,0.6); margin-bottom:12px;">
            <div style="font-weight:bold; font-size:15px; color:#fff; margin-bottom:6px;">${card.name}</div>
            <p style="margin:0; font-size:12px; color:#aaa;">このカードの移動先を選択してください。</p>
        </div>
    `;

    footer.innerHTML = `
        <button class="btn-pt-primary" onclick="ptMoveCard('${card.id}', 'shield', 'hand'); closePtModal();" style="flex:1;">手札に加える</button>
        <button class="btn-pt-secondary" onclick="ptMoveCard('${card.id}', 'shield', 'graveyard'); closePtModal();" style="flex:1;">墓地に置く</button>
        <button class="btn-pt-secondary" onclick="ptMoveCard('${card.id}', 'shield', 'mana'); closePtModal();" style="flex:1;">マナに置く</button>
        <button class="btn-pt-secondary" onclick="closePtModal();">戻す</button>
    `;
    modal.style.display = 'flex';
}
function ptMoveDeckTopTo(targetZone) {
    if (ptState.deck.length === 0) {
        alert("山札にカードがありません。");
        return;
    }
    // 山札の1枚目（配列の末尾）を取得
    const topCard = ptState.deck[ptState.deck.length - 1];
    
    // 既存の汎用移動関数を呼び出し（自動的に裏表やタップ状態等のゾーン特性が適用されます）
    ptMoveCard(topCard.id, 'deck', targetZone);
}
// --- app/Views/deck/playtest.php (JS内へ新規追加する関数群) ---
// カードIDから現在存在しているゾーンのカード実体を探すヘルパー関数
function findCardById(id) {
    let found = null;
    ['hand','mana','battle','shield','graveyard','psychic','gr','deck'].forEach(z => {
        if (ptState[z]) {
            const c = ptState[z].find(item => item.id === id);
            if (c) found = c;
        }
    });
    return found;
}

// 拡大表示モーダルを開く
// --- app/Views/deck/playtest.php (ptShowCardDetailModalById関数を以下に差し替え) ---
function ptShowCardDetailModalById(id, zone) {
    const card = findCardById(id);
    if (!card) return;

    const modal = document.getElementById('ptListModal');
    const title = document.getElementById('ptModalTitle');
    const body = document.getElementById('ptModalBody');
    const footer = document.getElementById('ptModalFooter');

    title.innerText = "カード拡大表示";
    body.innerHTML = `
        <div style="text-align:center; padding: 10px 0;">
            <!-- 画像の最大幅を360pxまで拡大し、画面幅が狭い時は100%に縮小されるレスポンシブなサイズ設定にしています -->
            <img src="${card.src}" style="width:100%; max-width:360px; height:auto; aspect-ratio:5/7; border-radius:12px; box-shadow:0 8px 30px rgba(0,0,0,0.8); margin-bottom:12px;">
            <div style="font-weight:bold; font-size:18px; color:#fff; margin-bottom:6px;">${card.name}</div>
            <div style="font-size:13px; color:#aaa;">(現在地: ${zoneJapaneseName(zone)})</div>
        </div>
    `;

    footer.innerHTML = `
        <button class="btn-pt-primary" onclick="closePtModal();" style="width:120px; font-weight:bold; padding:8px 0;">閉じる</button>
    `;
    modal.style.display = 'flex';
}
</script>
</body>
</html>