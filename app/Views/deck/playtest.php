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
            cursor: pointer;
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
        
        /* ゾーン別の高さ・幅の定義 */
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
            background-color: #2a2a2a; 
            border: 1px solid #444;
            overflow: hidden;
        }
        .playtest-card:active { cursor: grabbing; }
        .playtest-card img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            border-radius: 4px;
            pointer-events: none;
        }

        /* 状態クラス */
        .playtest-card.tapped { transform: rotate(90deg); }
        .playtest-card.tapped-left { transform: rotate(-90deg); }
        .playtest-card.inverted { transform: rotate(180deg); }
        .playtest-card.inverted.tapped { transform: rotate(270deg); }
        .playtest-card.inverted.tapped-left { transform: rotate(90deg); }
        .playtest-card.face-down { z-index: 50; }
        .playtest-card.dragging { opacity: 0.5; }
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

        /* カード内に共通テキスト（数字）が配置される場合の中央寄せ指定 */
        .playtest-card .playtest-zone-center-text {
            top: 50%; left: 50%;
            transform: translate(-50%, -50%);
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
            width: 80px; height: 112px; object-fit: contain; border-radius: 2px;
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
            <button class="btn-exit" style="background:#fd7e14; margin-right:8px;" onclick="ptPassTurnAndStart()">ターン終了</button>
            <button class="btn-exit" style="background:#17a2b8; margin-right:8px;" onclick="openUntapMenu()">カードをアンタップ</button>
            <button class="btn-exit" style="background:#28a745; margin-right:8px;" onclick="toggleSelectMode()">一括選択モード切替</button>
            <button class="btn-exit" onclick="ptExitGame()">終了する</button>
        </div>
    </div>

    <div class="playtest-board" id="playtestBoard">
        <!-- 1段目：バトルゾーン -->
        <div class="playtest-row">
            <div class="playtest-zone zone-battle" id="pt-zone-battle" ondragover="allowDrop(event)" ondrop="handleDrop(event, 'battle')">
                <div class="playtest-zone-title">バトルゾーン</div>
            </div>
        </div>

        <!-- 2段目：シールドゾーン ＆ 固定ゾーン -->
        <div class="playtest-row">
            <div class="playtest-zone zone-shield" id="pt-zone-shield" ondragover="allowDrop(event)" ondrop="handleDrop(event, 'shield')">
                <div class="playtest-zone-title">シールドゾーン</div>
            </div>

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
// PHPからの初期データを展開
const initialCardsRaw = <?php echo json_encode($cards ?? []); ?>;
const initialCards = Array.isArray(initialCardsRaw) ? initialCardsRaw : [];

let ptState = {
    deck: [], hand: [], mana: [], graveyard: [], battle: [], shield: [], psychic: [], gr: []
};
let ptDraggedCardId = null;
let selectedCards = new Set(); 
let isSelectMode = false;
let ptLookingCards = []; 
let ptReturnOrder = [];  

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

// ==========================================
// ゲーム初期化処理 (ドルマゲドンX ＆ 零龍 登録対応版)
// ==========================================
function initPlaytestGame() {
    ptState = { deck: [], hand: [], mana: [], graveyard: [], battle: [], shield: [], psychic: [], gr: [] };
    selectedCards.clear();
    
    let mainCards = [];
    let hasKindan = false;
    let hasForbiddenStar = false;

    // 零龍チェック用
    function checkZeron(name) {
        return [
            '滅亡の起源 零無', '零龍',
            '手札の儀', '墓地の儀', '破壊の儀', '復活の儀'
        ].includes(name);
    }

    function checkKindan(name) {
        return name === '禁断 ～封印されしX～';
    }

    function checkForbiddenStar(name) {
        return name === 'FORBIDDEN STAR ～世界最後の日～' || name === '終焉の禁断 ドルマゲドンX';
    }

    let hasZeronSystem = false;
    let zeronMainCardData = null;
    let zeronBackSrc = null;

    // 事前に零龍カードの有無を走査
    initialCards.forEach(card => {
        if (!card) return;
        const name = card.card_name || '';
        if (checkZeron(name)) {
            hasZeronSystem = true;
            if (name === '滅亡の起源 零無') {
                zeronMainCardData = card;
            }
            if (name === '零龍') {
                zeronBackSrc = getCardImageUrl(card.imagepath);
            }
        }
    });

    initialCards.forEach((card, idx) => {
        if (!card) return;
        const qty = parseInt(card.quantity || 1);
        const src = getCardImageUrl(card.imagepath);
        const cardName = card.card_name || 'カード';

        // 零龍関連のカード（儀式含む）は通常の山札や超次元などから除外
        if (checkZeron(cardName)) {
            return;
        }

        const cardtypeIdsRaw = card.cardtype_ids || card.cardtype_id || card.card_cardtype || '';
        const characteristicsIdsRaw = card.characteristics_ids || card.char_ids || card.characteristic_ids || card.characteristics_id || '';
        const abilityIdsRaw = card.ability_ids || card.ability_id || card.ability_ids_list || '';

        const cardtypeIds = ptNormalizeIds(cardtypeIdsRaw);
        const characteristicsIds = ptNormalizeIds(characteristicsIdsRaw);
        const abilityIds = ptNormalizeIds(abilityIdsRaw);

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
                is_multicolor: parseInt(card.civ_count || 1) >= 2, 
                cardtype_ids: cardtypeIds,               
                characteristics_ids: characteristicsIds, 
                ability_ids: abilityIds,
                underCards: [], tapped: false, inverted: false, faceDown: false, flipped: false
            };

            if (instance.name === '終焉の禁断 ドルマゲドンX') {
                instance.name = 'FORBIDDEN STAR ～世界最後の日～';
                if (card.combination_imagepath) {
                    const partnerImg = getCardImageUrl(card.combination_imagepath);
                    instance.src = partnerImg;
                    instance.original_src = partnerImg;
                    instance.creature_src = src;
                }
            }
            if (checkKindan(instance.name)) hasKindan = true;
            if (checkForbiddenStar(instance.name)) hasForbiddenStar = true;

            const isStartSettingCard = checkKindan(instance.name) || checkForbiddenStar(instance.name);

            if (isStartSettingCard) {
                mainCards.push(instance);
            } else if (instance.type === 'gr') {
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
        // 裏面画像を gr_backimage.webp に変更
        document.getElementById('pt-zone-gr').innerHTML = `<div class="playtest-zone-title">超GR</div><img src="/images/card/gr_backimage.webp" style="width:100%; height:100%; object-fit:fill;">`;
    }

    ptShuffle(mainCards);

    let startCard = null;
    let seals = 0;

    mainCards = mainCards.filter(c => {
        if (checkKindan(c.name)) { startCard = c; seals = 6; return false; }
        if (checkForbiddenStar(c.name)) { startCard = c; seals = 4; return false; }
        return true;
    });

    ptState.deck = mainCards.filter(c => c.type === 'main');

    // 零龍システム初期化
    if (hasZeronSystem) {
        const sampleCard = initialCards.find(c => c && checkZeron(c.card_name));
        if (sampleCard) {
            // APIから同じ combination_id を持つすべての面（カード）を取得
            fetch(`/api/cards/combination?card_id=${sampleCard.card_id}`)
                .then(res => res.json())
                .then(data => {
                    // 「滅亡の起源 零無」と「零龍」のデータを確実に抽出
                    const zeronData = data.find(c => c.card_name === '滅亡の起源 零無');
                    const zeronBackData = data.find(c => c.card_name === '零龍');
                    
                    const finalZeronData = zeronData || zeronMainCardData || sampleCard;
                    const zeronSrc = getCardImageUrl(finalZeronData.imagepath);
                    const backSrc = zeronBackData ? getCardImageUrl(zeronBackData.imagepath) : (zeronBackSrc || zeronSrc);

                    let zeronInstance = {
                        id: 'pt_zeron_' + Date.now(),
                        card_id: finalZeronData.card_id || '0',
                        name: '滅亡の起源 零無',
                        src: zeronSrc,
                        original_src: zeronSrc,
                        creature_src: backSrc,
                        type: 'main',
                        twinpact: '0',
                        combination_id: finalZeronData.combination_id || null,
                        is_multicolor: false,
                        cardtype_ids: [],
                        characteristics_ids: [],
                        ability_ids: [],
                        underCards: [], tapped: false, inverted: false, faceDown: false, flipped: false,
                        is_zeron: true,
                        zeron_links: [],
                        zeron_count: 0,
                        zeron_back_src: backSrc
                    };
                    
                    // バトルゾーンに配置して再描画
                    ptState.battle.push(zeronInstance);
                    renderPtBoard();
                })
                .catch(err => {
                    console.error("Zeron API fetch error:", err);
                    // 通信エラー時のフォールバック
                    fallbackZeronInit(sampleCard, zeronMainCardData, zeronBackSrc);
                });
        }
    }

    function fallbackZeronInit(sampleCard, mainData, backSrc) {
        const target = mainData || sampleCard;
        const zeronSrc = getCardImageUrl(target.imagepath);
        let zeronInstance = {
            id: 'pt_zeron_' + Date.now(),
            card_id: target.card_id || '0',
            name: '滅亡の起源 零無',
            src: zeronSrc,
            original_src: zeronSrc,
            creature_src: backSrc || zeronSrc,
            type: 'main',
            twinpact: '0',
            combination_id: target.combination_id || null,
            is_multicolor: false,
            cardtype_ids: [],
            characteristics_ids: [],
            ability_ids: [],
            underCards: [], tapped: false, inverted: false, faceDown: false, flipped: false,
            is_zeron: true,
            zeron_links: [],
            zeron_count: 0,
            zeron_back_src: backSrc || zeronSrc
        };
        ptState.battle.push(zeronInstance);
        renderPtBoard();
    }
    if (startCard) {
        const cardtypeIds = startCard.cardtype_ids || [];
        if (cardtypeIds.some(id => [6, 7, 8].includes(id))) {
            startCard.tapped = true;
        }

        if (seals > 0 && ptState.deck.length >= seals) {
            let pulledSeals = [];
            for (let i = 0; i < seals; i++) {
                let seal = ptState.deck.pop();
                seal.faceDown = true;
                pulledSeals.push(seal);
            }

            let parentSeal = pulledSeals.shift();
            pulledSeals.forEach(s => {
                parentSeal.underCards.push(s);
            });
            startCard.faceDown = false;
            parentSeal.underCards.push(startCard);
            ptState.battle.push(parentSeal);
        } else {
            ptState.battle.push(startCard);
        }
    }

    if (ptState.deck.length >= 10) {
        for(let i=0; i<5; i++) {
            let shield = ptState.deck.pop();
            shield.faceDown = true;
            ptState.shield.push(shield);
        }
        for(let i=0; i<5; i++) {
            let handCard = ptState.deck.pop();
            handCard.faceDown = false;
            ptState.hand.push(handCard);
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

function ptNormalizeIds(raw) {
    if (raw === null || raw === undefined || raw === '') {
        return [];
    }
    if (Array.isArray(raw)) {
        return raw.map(Number);
    }
    if (typeof raw === 'string') {
        return raw.split(',')
                  .map(s => parseInt(s.trim(), 10))
                  .filter(n => !isNaN(n));
    }
    if (typeof raw === 'number') {
        return [raw];
    }
    return [];
}

function updatePsychicHeaderImage() {
    const pz = document.getElementById('pt-zone-psychic');
    if (ptState.psychic.length > 0) {
        // カードID順などの条件で一番上のカードを1枚特定 (NaN対策を追加)
        const minCard = ptState.psychic.reduce((prev, curr) => 
            (parseInt(prev.card_id || 0, 10) < parseInt(curr.card_id || 0, 10)) ? prev : curr
        );
        pz.innerHTML = `
            <div class="playtest-zone-title">超次元</div>
            <img src="${minCard.src}" style="width:100%; height:100%; object-fit:fill;" draggable="true" ondragstart="ptDragPsychic(event, '${minCard.id}')">
        `;
    } else {
        pz.innerHTML = `<div class="playtest-zone-title">超次元</div><div class="playtest-zone-center-text">0</div>`;
    }
}

// 超次元ゾーン専用のドラッグ開始ハンドラ
function ptDragPsychic(e, cardId) {
    ptDraggedCardId = cardId;
    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData('text/plain', cardId);
}

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

function renderPtBoard() {
    try {
        document.getElementById('pt-deck-count').innerText = ptState.deck.length;
        
        // 墓地要素の更新時に ID属性（id="pt-graveyard-count"）を保持するように修正
        const grave = document.getElementById('pt-zone-graveyard');
        if (ptState.graveyard.length > 0) {
            const last = ptState.graveyard[ptState.graveyard.length - 1];
            grave.style.backgroundImage = `url('${last.src}')`;
            grave.style.backgroundSize = 'cover';
            grave.innerHTML = `<div class="playtest-zone-title">墓地</div><div class="playtest-zone-center-text" id="pt-graveyard-count">${ptState.graveyard.length}</div>`;
        } else {
            grave.style.backgroundImage = 'none';
            grave.innerHTML = `<div class="playtest-zone-title">墓地</div><div class="playtest-zone-center-text" id="pt-graveyard-count">0</div>`;
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

function renderZone(zoneId, arr, name) {
    const zone = document.getElementById(zoneId);
    zone.innerHTML = `<div class="playtest-zone-title">${name}</div>`;
    
    // 手札グルーピング表示
    if (zoneId === 'pt-zone-hand') {
        const groups = {};
        arr.forEach(card => {
            const key = getCardGroupKey(card);
            if (!groups[key]) groups[key] = [];
            groups[key].push(card);
        });

        Object.keys(groups).forEach(key => {
            const groupCards = groups[key];
            const card = groupCards[0]; 
            const count = groupCards.length;

            const el = document.createElement('div');
            el.className = 'playtest-card';
            el.id = card.id;
            el.draggable = true;
            
            if (card.tapped) el.classList.add('tapped');
            if (card.inverted) el.classList.add('inverted');
            if (card.faceDown) el.classList.add('face-down');
            if (selectedCards.has(card.id)) el.classList.add('selected');

            // GRカードかどうかで裏面を分岐
            const isGR = card.characteristics_ids && card.characteristics_ids.includes(10);
            const backPath = isGR ? '/images/card/gr_backimage.webp' : '/images/card/backimage.webp';
            const displaySrc = card.faceDown ? backPath : card.src;
            el.innerHTML = `<img src="${displaySrc}">`;
            
            if (count > 1) {
                const bCount = document.createElement('div');
                bCount.className = 'playtest-under-count';
                bCount.style.top = 'auto'; bCount.style.bottom = '-4px'; 
                bCount.style.background = '#007bff';
                bCount.innerText = count;
                el.appendChild(bCount);
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

    // 手札以外の個別表示
    arr.forEach(card => {
        const el = document.createElement('div');
        el.className = 'playtest-card';
        el.id = card.id;
        el.draggable = true;
        
        if (card.tapped) {
            const isLeftTap = card.cardtype_ids && card.cardtype_ids.some(id => [6, 7, 8].includes(id));
            if (isLeftTap) {
                el.classList.add('tapped-left');
            } else {
                el.classList.add('tapped');
            }
        }
        if (card.inverted) el.classList.add('inverted');
        if (card.faceDown) el.classList.add('face-down');
        if (selectedCards.has(card.id)) el.classList.add('selected');

        // GRカードかどうかで裏面を分岐
        const isGR = card.characteristics_ids && card.characteristics_ids.includes(10);
        const backPath = isGR ? '/images/card/gr_backimage.webp' : '/images/card/backimage.webp';
        const displaySrc = card.faceDown ? backPath : card.src;
        el.innerHTML = `<img src="${displaySrc}">`;
        
        // 零龍カウンターの表示
        if (card.is_zeron && !card.flipped) {
            const counter = document.createElement('div');
            counter.className = 'playtest-zone-center-text'; 
            counter.innerText = card.zeron_count || 0;
            el.appendChild(counter);
        }

        const seals = card.underCards ? card.underCards.filter(uc => uc.faceDown) : [];
        if (seals.length > 0) {
            seals.forEach((seal, idx) => {
                const sealImg = document.createElement('img');
                const isGR = seal.characteristics_ids && seal.characteristics_ids.includes(10);
                sealImg.src = isGR ? '/images/card/gr_backimage.webp' : '/images/card/backimage.webp';
                sealImg.className = 'playtest-seal-card';
                // 複数封印時に枚数がわかるよう、わずかに斜め下（各6px）にずらして一番上に重ねる
                sealImg.style.top = `${idx * 6}px`;
                sealImg.style.left = `${idx * 6}px`;
                el.appendChild(sealImg);
            });
        }        
        // === 【追加・オレガオーラの重ね合わせ個別描画】 ===
        const auras = card.underCards ? card.underCards.filter(uc => uc.cardtype_ids && uc.cardtype_ids.includes(8) && !uc.faceDown) : [];
        if (auras.length > 0) {
            el.classList.add('has-aura');
            auras.forEach((aura, idx) => {
                const auraImg = document.createElement('img');
                auraImg.src = aura.src;
                auraImg.className = 'playtest-aura-card';
                auraImg.style.top = `-${32 + (idx * 16)}px`; 
                el.appendChild(auraImg);
            });
        }

        // === 【修正】オーラとして描画されなかったカード（裏向き封印状態のオーラ、および進化元や通常の封印等）をカウント ===
        const regularUnders = card.underCards ? card.underCards.filter(uc => !(uc.cardtype_ids && uc.cardtype_ids.includes(8) && !uc.faceDown)) : [];
        if (regularUnders.length > 0) {
            const b = document.createElement('div');
            b.className = 'playtest-under-count';
            b.innerText = regularUnders.length;
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
                
                if ((card.name === '禁断 ～封印されしX～' || card.name === 'FORBIDDEN STAR ～世界最後の日～') && !card.flipped) {
                    ptFlipCardFace(card.id);
                    return;
                }
                
                const isTappableType = (
                    (card.cardtype_ids && card.cardtype_ids.some(id => [1, 9, 10].includes(id))) || 
                    (isDoubleSidedCard(card) && card.flipped) ||
                    ((card.name === 'FORBIDDEN STAR ～世界最後の日～' || card.name === '禁断 ～封印されしX～') && card.flipped)
                );
                
                if (isTappableType) {
                    card.tapped = !card.tapped;
                }
            } else if (simpleZone === 'mana') {
                card.tapped = !card.tapped;
            } else if (simpleZone === 'shield') {
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

let ptSelectedDropShieldTargetId = null; // シールド重ね合わせ時の対象ID保持用

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
        ptCheckEvolutionAndPlay(id, fromZone); // 進化チェックへ送る
        ptDraggedCardId = null;
        return;
    }

    // === 【追加】ドロップ先がシールドゾーンの場合の選択処理への割り込み ===
    if (targetZone === 'shield') {
        ptOpenShieldDropSelectModal(id, fromZone);
        ptDraggedCardId = null;
        return;
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

// 1. ptMoveCard 関数の修正 (重ね合わせカードの移動分岐を追加)
function ptMoveCard(cardId, from, to, opts = {}) {
    let index = ptState[from].findIndex(c => c.id === cardId);
    if (index === -1) return false;
    
    let card = ptState[from][index];

    // 複数面カードの選択モーダルを開く場合は false を返して処理を保留する
    if (to === 'battle' && isDoubleSidedCard(card) && !opts.skipDoubleSidedCheck) {
        ptOpenDoubleSidedSelectModal(card, from);
        return false; 
    }

    // バトルゾーンから移動し、重ねられたカードがある場合は個別選択モーダルを開く
    if (from === 'battle' && card.underCards && card.underCards.length > 0 && !opts.skipUnderCheck) {
        ptOpenUnderCardSelectModal(card, to, opts);
        return false; 
    }

    // シールドゾーンから移動する際、複数枚構成であれば個別選択モーダルへ割り込み
    if (from === 'shield' && card.underCards && card.underCards.length > 0 && !opts.skipUnderCheck) {
        ptOpenShieldUnderCardSelectModal(card, to, opts);
        return false; 
    }

    // 切り出し処理 (バトルゾーンの裏向きカード等)
    if (from === 'battle' && ptState.battle[index].faceDown && ptState.battle[index].underCards && ptState.battle[index].underCards.length > 0) {
        card = ptState.battle[index]; 
        
        let underCards = [...card.underCards];
        let nextTopCard = underCards.shift(); 
        
        nextTopCard.underCards = underCards;   
        ptState.battle.splice(index, 1, nextTopCard);
        card.underCards = [];
    } else {
        card = ptState[from].splice(index, 1)[0];
    }

    // バトルゾーン以外への移動時、最初の面（表面）に自動リセット
    if (to !== 'battle') {
        card.flipped = false;
        if (card.original_src) {
            card.src = card.original_src;
        }
    }

    // === 【修正】GR/サイキックの特別帰還判定を共通化 ===
    if (ptCheckAndReturnSpecialCard(card, to)) {
        renderPtBoard();
        return true;
    }

    if (to === 'battle' && card.cardtype_ids.some(id => [6, 7, 8].includes(id))) {
        card.tapped = true;
    } else if (to === 'mana') {
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
        if (card.is_multicolor) {
            card.tapped = true;
        }
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
    return true;
}

// 2. 新規追加：バトルゾーンの構成カードから移動するカードを選択するモーダル
function ptOpenUnderCardSelectModal(parentCard, toZone, opts = {}) {
    const modal = document.getElementById('ptListModal');
    const title = document.getElementById('ptModalTitle');
    const body = document.getElementById('ptModalBody');
    const footer = document.getElementById('ptModalFooter');

    title.innerText = "移動するカードの選択";
    body.innerHTML = `
        <p style="margin-bottom:12px; font-size:13px; color:#aaa;">移動させるカードを選択してください。選ばなかったカードはそのままバトルゾーンに残ります。</p>
        <div class="pt-grid" id="ptUnderSelectGrid"></div>
    `;

    const grid = document.getElementById('ptUnderSelectGrid');
    
    // 一番上のカード（親）と、下にあるカードをフラットな配列にする
    const allComponents = [parentCard, ...parentCard.underCards];

    allComponents.forEach((card, idx) => {
        const itemEl = document.createElement('div');
        itemEl.className = 'pt-grid-item';
        
        // 裏向きのカードは裏向き画像のまま選ぶようにする
        const displaySrc = card.faceDown ? '/images/card/backimage.webp' : card.src;
        const displayName = card.faceDown ? '（裏向きのカード）' : card.name;

        itemEl.innerHTML = `
            <img src="${displaySrc}">
            <div class="card-qty">${displayName}</div>
        `;
        
        itemEl.onclick = () => {
            itemEl.classList.toggle('selected');
        };
        itemEl.dataset.componentIndex = idx;
        grid.appendChild(itemEl);
    });

    // --- 【修正ここから】HTML文字列展開によるエスケープ崩れを防ぐため、DOM操作で安全にイベント登録 ---
    footer.innerHTML = ''; // 初期化

    const cancelBtn = document.createElement('button');
    cancelBtn.className = 'btn-pt-secondary';
    cancelBtn.innerText = 'キャンセル';
    cancelBtn.onclick = function() {
        closePtModal();
    };

    const confirmBtn = document.createElement('button');
    confirmBtn.className = 'btn-pt-primary';
    confirmBtn.innerText = '選択したカードを移動';
    confirmBtn.onclick = function() {
        ptExecuteUnderCardSelectMove(parentCard.id, toZone, opts);
    };

    footer.appendChild(cancelBtn);
    footer.appendChild(confirmBtn);
    // --- 【修正ここまで】 ---

    modal.style.display = 'flex';
}

// 3. 新規追加：選択されたカードの移動と、残されたカードのバトルゾーン再構成処理
function ptExecuteUnderCardSelectMove(parentId, toZone, opts = {}) {
    const selectedItems = document.querySelectorAll('#ptUnderSelectGrid .pt-grid-item.selected');
    if (selectedItems.length === 0) {
        alert("移動するカードを1枚以上選択してください。");
        return;
    }

    const battleIdx = ptState.battle.findIndex(c => c.id === parentId);
    if (battleIdx === -1) {
        closePtModal();
        return;
    }

    const parentCard = ptState.battle[battleIdx];
    const allComponents = [parentCard, ...parentCard.underCards];

    const toMoveIndexes = [];
    selectedItems.forEach(item => {
        toMoveIndexes.push(parseInt(item.dataset.componentIndex));
    });

    const moveCards = [];
    const remainCards = [];

    allComponents.forEach((card, idx) => {
        if (toMoveIndexes.includes(idx)) {
            moveCards.push(card);
        } else {
            remainCards.push(card);
        }
    });

    // 元の束を一旦バトルゾーンから削除
    ptState.battle.splice(battleIdx, 1);

    // 残ったカードがある場合、新しい構成にしてバトルゾーンに配置
    if (remainCards.length > 0) {
        const newParent = remainCards[0];
        newParent.underCards = remainCards.slice(1);
        ptState.battle.splice(battleIdx, 0, newParent);
    }

    // 選択されたカードをそれぞれ目的のゾーンへ移動
    moveCards.forEach(card => {
        card.underCards = [];

        // === 【追加】バトルゾーン以外へ行く場合、最初の面（表面）に自動リセット ===
        if (toZone !== 'battle') {
            card.flipped = false;
            if (card.original_src) {
                card.src = card.original_src;
            }
        }

        const charIds = card.characteristics_ids || [];
        const cardtypeIds = card.cardtype_ids || [];

        const hasGR = charIds.includes(10);
        const hasPsychic = charIds.some(id => [3, 6].includes(id));
        if (toZone !== 'battle' && toZone !== 'psychic' && toZone !== 'gr') {
            if (hasGR) {
                card.tapped = false; card.inverted = false; card.faceDown = false;
                ptState.gr.unshift(card);
                return;
            }
            if (hasPsychic) {
                card.tapped = false; card.inverted = false; card.faceDown = false;
                ptState.psychic.push(card);
                return;
            }
        }

        if (toZone === 'battle') {
            if (cardtypeIds.some(id => [6, 7, 8].includes(id))) {
                card.tapped = true;
            } else {
                card.tapped = false;
            }
            card.inverted = false;
            card.faceDown = (opts.faceDown !== undefined) ? opts.faceDown : false;
            ptState.battle.push(card);
        } else if (toZone === 'mana') {
            card.inverted = true;
            card.faceDown = (opts.faceDown !== undefined) ? opts.faceDown : false;
            card.tapped = card.is_multicolor;
            ptState.mana.push(card);
        } else if (toZone === 'shield') {
            card.tapped = false;
            card.inverted = false;
            card.faceDown = (opts.faceDown !== undefined) ? opts.faceDown : true;
            ptState.shield.push(card);
        } else if (toZone === 'hand') {
            card.tapped = false;
            card.inverted = false;
            card.faceDown = false;
            ptState.hand.push(card);
        } else if (toZone === 'graveyard') {
            card.tapped = false;
            card.inverted = false;
            card.faceDown = false;
            ptState.graveyard.push(card);
        } else if (toZone === 'deck') {
            card.tapped = false;
            card.inverted = false;
            card.faceDown = false;
            if (opts.bottom) {
                ptState.deck.unshift(card);
            } else {
                ptState.deck.push(card);
            }
        } else {
            ptState[toZone].push(card);
        }
    });

    closePtModal();
    renderPtBoard();
}

function ptDrawCard() {
    if (ptState.deck.length === 0) return;
    let card = ptState.deck.pop();
    card.faceDown = false; // 手札に入るので表向きにする
    ptState.hand.push(card);
    renderPtBoard();
}

function openCardMenu(e, card, zone) {
    const menu = document.getElementById('ptContextMenu');
    menu.innerHTML = '';

    // 零龍カード専用メニュー分岐
    if (zone === 'battle' && card.is_zeron) {
        let menuHtml = `
            <li onclick="ptShowCardDetailModalById('${card.id}', '${zone}')">🔎 拡大表示</li>
            <hr>
        `;
        if (!card.flipped) {
            if (!card.zeron_links.includes('hand')) {
                menuHtml += `<li onclick="ptLinkZeron('${card.id}', 'hand')">《手札の儀》とリンク</li>`;
            }
            if (!card.zeron_links.includes('grave')) {
                menuHtml += `<li onclick="ptLinkZeron('${card.id}', 'grave')">《墓地の儀》とリンク</li>`;
            }
            if (!card.zeron_links.includes('destroy')) {
                menuHtml += `<li onclick="ptLinkZeron('${card.id}', 'destroy')">《破壊の儀》とリンク</li>`;
            }
            if (!card.zeron_links.includes('revive')) {
                menuHtml += `<li onclick="ptLinkZeron('${card.id}', 'revive')">《復活の儀》とリンク</li>`;
            }
            menuHtml += `
                <hr>
                <li onclick="ptFlipZeron('${card.id}')">裏返す (零龍へ)</li>
            `;
        } else {
            menuHtml += `
                <li onclick="ptFlipZeron('${card.id}')">裏返す (滅亡の起源 零無へ)</li>
            `;
        }
        menu.innerHTML = menuHtml;
        menu.style.display = 'block';
        adjustMenuPosition(e, menu);
        return;
    }

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
            const canFlip = isDoubleSidedCard(card) || card.name === '禁断 ～封印されしX～' || card.name === 'FORBIDDEN STAR ～世界最後の日～';
            if (canFlip) {
                menuHtml += `
                    <li onclick="ptFlipCardFace('${card.id}')">裏返す</li>
                    <hr>
                `;
            }

            // === 【追加】山札から封印を追加するメニュー項目 ===
            menuHtml += `
                <li onclick="ptSealCardPrompt('${card.id}')">🔒 山札の上から封印を付ける...</li>
                <hr>
            `;

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
            <hr>
            <li onclick="openShieldStackSelector('${card.id}', 'under')">🔽 このシールドの下に重ねる...</li>
            <li onclick="openShieldStackSelector('${card.id}', 'over')">🔼 このシールドの上に重ねる...</li>
            <li onclick="ptStackDeckTopToShield('${card.id}', 'under')">🔽 山札の1枚目を下に重ねる</li>
            <li onclick="ptStackDeckTopToShield('${card.id}', 'over')">🔼 山札の1枚目を上に重ねる</li>
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
        <li onclick="ptLookAtGRTopPrompt()">超GRの上から複数枚見る</li>
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
    ptCheckEvolutionAndPlay(id, 'hand');
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
            // 【修正】シールド以外から重ねる場合は元の向きを維持します
            if (fromZone === 'shield') {
                underC.faceDown = true;
            } else {
                underC.faceDown = underC.faceDown;
            }
            handCard.underCards.push(underC);
        }
    });

    closePtModal();
    renderPtBoard();
}

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
        const ids = JSON.parse(item.getAttribute('data-selected-ids') || '[]');
        allIds = allIds.concat(ids);
    });

    let anyModalOpened = false;

    if (targetZone === 'shield_select') {
        const isFaceup = confirm("シールドに「表向き」で置きますか？\n(「キャンセル」で裏向きに置きます)");
        allIds.forEach(id => {
            const success = ptMoveCard(id, 'deck', 'shield', { faceDown: !isFaceup });
            if (!success) anyModalOpened = true;
        });
    } else {
        allIds.forEach(id => {
            const success = ptMoveCard(id, 'deck', targetZone);
            if (!success) anyModalOpened = true;
        });
    }

    if (!anyModalOpened) {
        closePtModal();
    }
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
    ptCheckEvolutionAndPlay(id, 'mana');
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
        // 【修正】裏マナの場合は裏向き、表マナの場合は表向きの状態をそのまま維持して重ねます
        manaCard.faceDown = manaCard.faceDown;
        bCard.underCards.push(manaCard);
    }
    closePtModal();
    renderPtBoard();
}

function openShieldToBattleMenu(id) {
    ptCheckEvolutionAndPlay(id, 'shield');
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
        // 【修正】強制裏向き(true)を廃止し、元の状態（表向きなら表のまま）を維持します
        baseCard.faceDown = baseCard.faceDown; 
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

function openGroupedViewer(titleText, cardArray, zoneKey) {
    const modal = document.getElementById('ptListModal');
    const title = document.getElementById('ptModalTitle');
    const body = document.getElementById('ptModalBody');
    const footer = document.getElementById('ptModalFooter');

    title.innerText = `${titleText}一覧`;
    body.innerHTML = `
        <p style="margin-bottom:12px;">カードをクリックして選択し、移動先を選んでください。</p>
        <div class="pt-grid" id="ptViewerGrid"></div>
    `;

    const grid = document.getElementById('ptViewerGrid');
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

function ptBatchMoveFromViewer(fromZone, targetZone) {
    const selectedItems = document.querySelectorAll('#ptViewerGrid .pt-grid-item.selected');
    if (selectedItems.length === 0) { alert('カードを選択してください。'); return; }

    let allIds = [];
    selectedItems.forEach(item => {
        const ids = JSON.parse(item.getAttribute('data-selected-ids') || '[]');
        allIds = allIds.concat(ids);
    });

    let anyModalOpened = false;

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
            const success = ptMoveCard(id, fromZone, targetZone, {skipUnderCheck:true});
            if (!success) {
                anyModalOpened = true; 
            }
        });
    }

    if (!anyModalOpened) {
        closePtModal();
    }
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
        <button class="btn-pt-primary" onclick="ptExecuteBatchMove('shield')" style="width:100%; margin-bottom:10px;">一括してシールドに個別移動</button>
        <hr>
        <button class="btn-pt-primary" onclick="ptExecuteBatchShieldStack(true)" style="width:100%; margin-bottom:10px; background:#28a745;">🛡 新しいシールドとして重ねて置く (裏向き)</button>
        <button class="btn-pt-primary" onclick="ptExecuteBatchShieldStack(false)" style="width:100%; margin-bottom:10px; background:#28a745;">🛡 新しいシールドとして重ねて置く (表向き)</button>
        <hr>
        <button class="btn-pt-primary" onclick="ptExecuteBatchMove('deck')" style="width:100%;">一括して山札に戻す...</button>
    `;

    footer.innerHTML = `
        <button class="btn-pt-secondary" onclick="selectedCards.clear(); renderPtBoard(); closePtModal();">選択解除して閉じる</button>
    `;
    modal.style.display = 'flex';
}

function ptExecuteBatchMove(toZone) {
    let anyModalOpened = false;

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
            if (from) {
                const success = ptMoveCard(id, from, toZone);
                if (!success) {
                    anyModalOpened = true; // 選択モーダルが開かれた
                }
            }
        });
    }
    selectedCards.clear();

    // 新たに選択モーダルが開かれなかった場合のみ閉じる
    if (!anyModalOpened) {
        closePtModal();
    }
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

function getCardGroupKey(card) {
    const isTwin = card.twinpact === '1' || card.twinpact === 1 || card.twinpact === true || card.twinpact === 'true';
    if (isTwin) {
        return card.combination_id ? 'twin_' + card.combination_id : 'twin_name_' + card.name;
    }
    return 'normal_' + card.name;
}

function handleGroupItemClick(itemEl, allIds) {
    const max = allIds.length;
    const currentSelectedIds = JSON.parse(itemEl.getAttribute('data-selected-ids') || '[]');
    const currentCount = currentSelectedIds.length;
    
    let nextCount = currentCount + 1;
    if (nextCount > max) {
        nextCount = 0; 
    }

    if (nextCount > 0) {
        const selectedIds = allIds.slice(0, nextCount);
        itemEl.classList.add('selected');
        itemEl.setAttribute('data-selected-ids', JSON.stringify(selectedIds));
        updateItemSelectionBadge(itemEl, nextCount);
    } else {
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

function ptExecuteUntap(target) {
    let updated = false;

    if (target === 'battle' || target === 'all') {
        ptState.battle.forEach(card => {
            if (card.tapped) {
                card.tapped = false;
                updated = true;
            }
        });
    }

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

function ptOpenShieldBreakModal(parentCard) {
    const modal = document.getElementById('ptListModal');
    const title = document.getElementById('ptModalTitle');
    const body = document.getElementById('ptModalBody');
    const footer = document.getElementById('ptModalFooter');

    title.innerText = "シールド回収";
    
    // 重ねられているすべてのカード（代表カード + 下敷き配列）
    const allShieldCards = [parentCard, ...parentCard.underCards];

    if (allShieldCards.length === 1) {
        // 重ねられていない通常シールドの場合
        body.innerHTML = `
            <div style="text-align:center; padding: 10px 0;">
                <img src="${parentCard.src}" style="width:160px; height:224px; border-radius:6px; box-shadow:0 4px 15px rgba(0,0,0,0.6); margin-bottom:12px;">
                <div style="font-weight:bold; font-size:15px; color:#fff; margin-bottom:6px;">${parentCard.name}</div>
                <p style="margin:0; font-size:12px; color:#aaa;">このカードの移動先を選択してください。</p>
            </div>
        `;
        footer.innerHTML = `
            <button class="btn-pt-primary" onclick="ptMoveCard('${parentCard.id}', 'shield', 'hand'); closePtModal();" style="flex:1;">手札に加える</button>
            <button class="btn-pt-secondary" onclick="ptMoveCard('${parentCard.id}', 'shield', 'mana'); closePtModal();" style="flex:1; background:#28a745;">マナに置く</button>
            <button class="btn-pt-secondary" onclick="ptMoveCard('${parentCard.id}', 'shield', 'graveyard'); closePtModal();" style="flex:1; background:#dc3545;">墓地に置く</button>
            <button class="btn-pt-secondary" onclick="closePtModal();">キャンセル</button>
        `;
    } else {
        // 複数枚重ねられているシールドの場合
        body.innerHTML = `
            <p style="margin-bottom:12px; font-size:13px; color:#aaa;">重ねられているシールドカードの一覧です。カード個別の移動先ボタンを選択してください。</p>
            <div class="pt-grid" id="ptShieldBreakGrid"></div>
        `;

        const grid = document.getElementById('ptShieldBreakGrid');
        allShieldCards.forEach(card => {
            const itemEl = document.createElement('div');
            itemEl.className = 'pt-grid-item';
            
            // シールド回収モーダルでは表面を表示（非公開領域から公開領域に移るため）
            itemEl.innerHTML = `
                <img src="${card.src}">
                <div class="card-qty">${card.name}</div>
                <div style="margin-top:8px; display:flex; gap:4px; justify-content:center; width:100%;">
                    <button class="btn-pt-primary" style="padding:4px 6px; font-size:11px; flex:1;" onclick="event.stopPropagation(); ptMoveShieldComponent('${parentCard.id}', '${card.id}', 'hand')">🖐 手札</button>
                    <button class="btn-pt-primary" style="padding:4px 6px; font-size:11px; flex:1; background:#28a745;" onclick="event.stopPropagation(); ptMoveShieldComponent('${parentCard.id}', '${card.id}', 'mana')">🟢 マナ</button>
                    <button class="btn-pt-secondary" style="padding:4px 6px; font-size:11px; flex:1; background:#dc3545; color:white; border:none;" onclick="event.stopPropagation(); ptMoveShieldComponent('${parentCard.id}', '${card.id}', 'graveyard')">💥 墓地</button>
                </div>
            `;
            grid.appendChild(itemEl);
        });

        // 下部には一括移動用のショートカットを配置
        footer.innerHTML = `
            <button class="btn-pt-primary" onclick="ptMoveAllShieldComponents('${parentCard.id}', 'hand'); closePtModal();" style="background:#007bff;">🖐 すべて手札に加える</button>
            <button class="btn-pt-primary" onclick="ptMoveAllShieldComponents('${parentCard.id}', 'mana'); closePtModal();" style="background:#28a745;">🟢 すべてマナに置く</button>
            <button class="btn-pt-secondary" onclick="closePtModal();">戻す</button>
        `;
    }
    
    modal.style.display = 'flex';
}

function ptMoveDeckTopTo(targetZone) {
    if (ptState.deck.length === 0) {
        alert("山札にカードがありません。");
        return;
    }
    const topCard = ptState.deck[ptState.deck.length - 1];
    ptMoveCard(topCard.id, 'deck', targetZone);
}

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

function ptPassTurnAndStart() {
    let updated = false;

    ptState.battle.forEach(card => {
        if (card.tapped) {
            card.tapped = false;
            updated = true;
        }
    });

    ptState.mana.forEach(card => {
        if (card.tapped) {
            card.tapped = false;
            updated = true;
        }
    });

    if (ptState.deck.length > 0) {
        let card = ptState.deck.pop();
        card.faceDown = false; // 手札に入るので表向きにする
        ptState.hand.push(card);
        updated = true;
    } else {
        alert("山札が0枚のためドローできませんでした（ライブラリアウト）。");
    }

    if (updated) {
        renderPtBoard();
    }
}

function isDoubleSidedCard(card) {
    const hasCombination = card.combination_id !== null && card.combination_id !== undefined && card.combination_id !== '';
    const isTwin = card.twinpact === '1' || card.twinpact === 1 || card.twinpact === true || card.twinpact === 'true';
    return hasCombination && !isTwin;
}

function ptOpenDoubleSidedSelectModal(card, fromZone) {
    const modal = document.getElementById('ptListModal');
    const title = document.getElementById('ptModalTitle');
    const body = document.getElementById('ptModalBody');
    const footer = document.getElementById('ptModalFooter');

    title.innerText = "バトルゾーンに出す面を選択";
    body.innerHTML = `
        <p style="margin-bottom:15px; text-align:center; font-size:13px; color:#aaa;">どちらの面でバトルゾーンに出しますか？</p>
        <div style="display:flex; gap:24px; justify-content:center; align-items:center;">
            <div style="text-align:center; cursor:pointer;" onclick="ptExecuteDoubleSidedMove('${card.id}', '${fromZone}', 'original')">
                <img src="${card.original_src}" style="width:120px; height:168px; border-radius:6px; box-shadow:0 4px 10px rgba(0,0,0,0.5); margin-bottom:8px;">
                <div style="font-size:12px; font-weight:bold;">表面 (ウエポン/覚醒前)</div>
            </div>
            <div style="text-align:center; cursor:pointer;" onclick="ptExecuteDoubleSidedMove('${card.id}', '${fromZone}', 'creature')">
                <img src="${card.creature_src}" style="width:120px; height:168px; border-radius:6px; box-shadow:0 4px 10px rgba(0,0,0,0.5); margin-bottom:8px;">
                <div style="font-size:12px; font-weight:bold;">裏面 (クリーチャー/覚醒後)</div>
            </div>
        </div>
    `;
    footer.innerHTML = `<button class="btn-pt-secondary" onclick="closePtModal()">キャンセル</button>`;
    modal.style.display = 'flex';
}

function ptExecuteDoubleSidedMove(cardId, fromZone, side) {
    const index = ptState[fromZone].findIndex(c => c.id === cardId);
    if (index === -1) { closePtModal(); return; }
    
    const card = ptState[fromZone][index];
    
    if (side === 'creature') {
        card.src = card.creature_src;
        card.flipped = true;
    } else {
        card.src = card.original_src;
        card.flipped = false;
    }

    ptMoveCard(cardId, fromZone, 'battle', { skipDoubleSidedCheck: true });
    closePtModal();
}

// ==========================================
// カードの裏返り・龍解処理 (直通URLオートマッピング版)
// ==========================================
function ptFlipCardFace(id) {
    const card = ptState.battle.find(c => c.id === id);
    if (!card) return;

    if (card.combination_id) {
        // 主要なAPIエンドポイント候補に絞り込んでリクエスト
        const urls = [
            `/api/cards/combination?card_id=${card.card_id}`,
            `/cardCombinationApi?card_id=${card.card_id}`
        ];

        function fetchWithFallback(index) {
            if (index >= urls.length) {
                // API全滅時のフォールバック（通常のトグル裏返し）
                executeNormalFlip(card);
                return;
            }

            fetch(urls[index])
                .then(res => {
                    if (!res.ok) throw new Error(`HTTP error ${res.status}`);
                    return res.json();
                })
                .then(data => {
                    if (Array.isArray(data) && data.length >= 3) {
                        ptOpen3DDoubleSidedSelectModal(card, data);
                    } else {
                        executeNormalFlip(card);
                    }
                })
                .catch(err => {
                    console.warn(`Endpoint failed: ${urls[index]}, trying next...`);
                    fetchWithFallback(index + 1);
                });
        }
        fetchWithFallback(0);
    } else {
        executeNormalFlip(card);
    }
}

function executeNormalFlip(card) {
    if (!card.flipped) {
        card.src = card.creature_src;
        card.flipped = true;
    } else {
        card.src = card.original_src;
        card.flipped = false;
        // === 【追加】クリーチャー以外の面に戻ったら強制的にアンタップします ===
        card.tapped = false;
    }
    renderPtBoard();
}

function ptOpen3DDoubleSidedSelectModal(card, combinationCards) {
    const modal = document.getElementById('ptListModal');
    const title = document.getElementById('ptModalTitle');
    const body = document.getElementById('ptModalBody');
    const footer = document.getElementById('ptModalFooter');

    title.innerText = "3D龍解・面の選択";
    body.innerHTML = `
        <p style="margin-bottom:15px; text-align:center; font-size:13px; color:#aaa;">どの面に裏返しますか？</p>
        <div class="pt-grid" id="pt3DSelectGrid"></div>
    `;

    const grid = document.getElementById('pt3DSelectGrid');
    combinationCards.forEach(cData => {
        const itemEl = document.createElement('div');
        itemEl.className = 'pt-grid-item';
        const imgPath = getCardImageUrl(cData.imagepath);
        
        itemEl.innerHTML = `
            <img src="${imgPath}">
            <div class="card-qty">${cData.card_name}</div>
        `;
        
        // === 【修正】cData.cardtype_ids を引き渡すように修正 ===
        itemEl.onclick = () => {
            ptExecute3DFlip(card.id, imgPath, cData.card_name, cData.cardtype_ids);
        };
        grid.appendChild(itemEl);
    });

    footer.innerHTML = `<button class="btn-pt-secondary" onclick="closePtModal()">キャンセル</button>`;
    modal.style.display = 'flex';
}

// 3D龍解の実行処理 (新規追加)
function ptExecute3DFlip(cardId, imgPath, cardName, rawCardtypeIds) {
    const card = ptState.battle.find(c => c.id === cardId);
    if (card) {
        card.src = imgPath;
        
        // カードタイプを配列に正規化
        const cardtypeIds = ptNormalizeIds(rawCardtypeIds);
        
        // 選択した面がクリーチャー（cardtype_id: 1）であるかを判定
        const isCreatureSide = cardtypeIds.includes(1);
        
        if (isCreatureSide) {
            card.flipped = true; // クリーチャー化したのでタップ可能に
        } else {
            card.flipped = false; // クリーチャー以外の面（ウエポン等）に戻ったためタップ不可に
            card.tapped = false;  // 自動的にアンタップ
        }
        
        renderPtBoard();
    }
    closePtModal();
}

// 山札の上から見る(Look At Top)関連処理
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

function ptLookAtDeckTop(count) {
    ptLookingCards = [];
    ptReturnOrder = [];
    
    const actualCount = Math.min(count, ptState.deck.length);
    if (actualCount === 0) {
        alert("山札にカードがありません。");
        return;
    }

    for (let i = 0; i < actualCount; i++) {
        let card = ptState.deck.pop();
        card.faceDown = false; 
        ptLookingCards.push(card);
    }
    
    renderLookingCardsModal();
}

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

function selectReturnOrder(cardId) {
    const idx = ptReturnOrder.indexOf(cardId);
    if (idx !== -1) {
        ptReturnOrder.splice(idx, 1); 
    } else {
        ptReturnOrder.push(cardId);    
    }
    updateReturnOrderUI();
}

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

function ptMoveLookingCardDirectly(cardId, targetZone) {
    const idx = ptLookingCards.findIndex(c => c.id === cardId);
    if (idx === -1) return;
    
    let card = ptLookingCards.splice(idx, 1)[0];
    
    const oIdx = ptReturnOrder.indexOf(cardId);
    if (oIdx !== -1) ptReturnOrder.splice(oIdx, 1);
    
    ptState.deck.push(card);
    ptMoveCard(cardId, 'deck', targetZone);
    
    if (ptLookingCards.length === 0) {
        closePtModal();
    } else {
        renderLookingCardsModal();
    }
}

function ptReturnLookingCards(action) {
    if (action === 'ordered_bottom') {
        if (ptReturnOrder.length !== ptLookingCards.length) {
            alert("すべてのカードの順番を指定してください。");
            return;
        }
        ptReturnOrder.forEach(cardId => {
            const card = ptLookingCards.find(c => c.id === cardId);
            if (card) {
                card.faceDown = true;
                ptState.deck.unshift(card); 
            }
        });
    } else if (action === 'shuffle_all') {
        ptLookingCards.forEach(card => {
            card.faceDown = true;
            ptState.deck.push(card);
        });
        ptShuffle(ptState.deck);
    } else if (action === 'shuffle_bottom') {
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

// 封印剥がし用関数
function ptMoveSeal(cardId, targetZone, opts = {}) {
    let index = ptState.battle.findIndex(c => c.id === cardId);
    if (index === -1) return;

    let parentCard = ptState.battle[index];
    if (!parentCard.underCards || parentCard.underCards.length === 0) return;

    let sealIndex = parentCard.underCards.findIndex(u => u.faceDown);
    if (sealIndex === -1) return;

    let sealCard = parentCard.underCards.splice(sealIndex, 1)[0];
    
    // 山札へ置いた状態から各指定ゾーンへ再移動
    ptState.deck.push(sealCard);
    ptMoveCard(sealCard.id, 'deck', targetZone, opts);
}
let ptEvoSelectedIds = [];      // 順番指定用の選択ID配列
let ptEvoDeckTop3Cards = [];    // ability_id: 319 で山札から一時退避させる配列

// 特性とability_idをチェックし、進化演出プロセスへ分岐させる
function ptCheckEvolutionAndPlay(cardId, fromZone) {
    const card = ptState[fromZone].find(c => c.id === cardId);
    if (!card) return;

    const charIds = card.characteristics_ids || [];
    const abIds = card.ability_ids || [];

    const isAura = card.cardtype_ids && card.cardtype_ids.includes(8);
    if (isAura) {
        ptOpenAuraPlaySelector(cardId, fromZone);
        return;
    }    
    // 進化/重ね合わせ対象の判定（2: 進化クリーチャー, 9: 特殊進化V等, 16: 究極進化等）
    const isEvo = charIds.some(id => [2, 9, 16].includes(id));

    // 通常カード（進化特性を持たない）はそのままバトルゾーンへ
    if (!isEvo) {
        ptMoveCard(cardId, fromZone, 'battle');
        return;
    }

    // ability_idのグループ定義
    const abZoneBattle = [18, 27, 93, 94, 95, 142, 143, 145, 159, 185, 186];
    const abZoneMana = [269, 270, 318];
    const abZoneGrave = [188, 189, 245, 246, 247, 248];
    const abZoneShield = [126];
    const abZoneHand = [190, 191];
    const abZoneDeckTop = [193, 194];
    const abZoneDeckTop3 = [319];
    const abZoneMulti = [144, 187];

    let mode = null;
    if (abIds.some(id => abZoneBattle.includes(id))) {
        mode = 'battle';
    } else if (abIds.some(id => abZoneMana.includes(id))) {
        mode = 'mana';
    } else if (abIds.some(id => abZoneGrave.includes(id))) {
        mode = 'graveyard';
    } else if (abIds.some(id => abZoneShield.includes(id))) {
        mode = 'shield';
    } else if (abIds.some(id => abZoneHand.includes(id))) {
        mode = 'hand';
    } else if (abIds.some(id => abZoneDeckTop.includes(id))) {
        mode = 'deck_top';
    } else if (abIds.some(id => abZoneDeckTop3.includes(id))) {
        mode = 'deck_top3';
    } else if (abIds.some(id => abZoneMulti.includes(id))) {
        mode = 'multi';
    }

    // 重ねる条件が特定できない進化の場合はそのまま配置
    if (!mode) {
        ptMoveCard(cardId, fromZone, 'battle');
        return;
    }

    // === 【修正】中間選択肢は完全にスキップし、ダイレクトに各種選択モーダルを開きます ===
    if (mode === 'deck_top') {
        openEvoDeckTopModal(cardId, fromZone);
    } else if (mode === 'deck_top3') {
        openEvoDeckTop3Modal(cardId, fromZone);
    } else {
        openEvoTargetSelector(cardId, fromZone, mode);
    }
}

// 単体で出すか、重ねるかの意思決定画面
function openEvolutionChoiceModal(cardId, fromZone, mode) {
    const modal = document.getElementById('ptListModal');
    const title = document.getElementById('ptModalTitle');
    const body = document.getElementById('ptModalBody');
    const footer = document.getElementById('ptModalFooter');

    title.innerText = "バトルゾーンへの出し方選択";
    body.innerHTML = `
        <p style="margin-bottom:15px; font-size:13px; color:#aaa;">出し方を選択してください。</p>
        <button class="btn-pt-primary" onclick="ptMoveCard('${cardId}', '${fromZone}', 'battle', {skipUnderCheck: true}); closePtModal();" style="width:100%; margin-bottom:10px; padding: 10px; font-weight:bold;">単体でバトルゾーンに置く</button>
        <button class="btn-pt-secondary" onclick="openEvoTargetSelector('${cardId}', '${fromZone}', '${mode}')" style="width:100%; padding: 10px; font-weight:bold;">他カードの上に重ねて置く</button>
    `;
    footer.innerHTML = `<button class="btn-pt-secondary" onclick="closePtModal()">閉じる</button>`;
    modal.style.display = 'flex';
}

// 重ねる対象（進化元）の選択モーダル（マルチ・ゾーン別・山札分岐）
function openEvoTargetSelector(cardId, fromZone, mode) {
    ptEvoSelectedIds = [];
    
    if (mode === 'deck_top') {
        openEvoDeckTopModal(cardId, fromZone);
        return;
    }
    if (mode === 'deck_top3') {
        openEvoDeckTop3Modal(cardId, fromZone);
        return;
    }

    const modal = document.getElementById('ptListModal');
    const title = document.getElementById('ptModalTitle');
    const body = document.getElementById('ptModalBody');
    const footer = document.getElementById('ptModalFooter');

    title.innerText = "重ねる進化元カードの選択";
    body.innerHTML = `
        <p style="margin-bottom:12px; font-size:13px; color:#aaa;">重ねたいカードをクリックして「重ねる順番（1番目が一番下）」を指定してください。複数枚重ねられます。</p>
        <div class="pt-grid" id="ptEvoGrid"></div>
    `;

    const grid = document.getElementById('ptEvoGrid');
    let list = [];

    // 移動させる自分自身以外のカードを対象にする
    if (mode === 'battle') {
        ptState.battle.forEach(c => {
            if (!c.faceDown && c.id !== cardId) list.push({ card: c, zone: 'battle' });
        });
    } else if (mode === 'mana') {
        ptState.mana.forEach(c => {
            if (c.id !== cardId) list.push({ card: c, zone: 'mana' });
        });
    } else if (mode === 'graveyard') {
        ptState.graveyard.forEach(c => {
            if (c.id !== cardId) list.push({ card: c, zone: 'graveyard' });
        });
    } else if (mode === 'shield') {
        ptState.shield.forEach(c => {
            if (c.id !== cardId) list.push({ card: c, zone: 'shield' });
        });
    } else if (mode === 'hand') {
        ptState.hand.forEach(c => {
            if (c.id !== cardId) list.push({ card: c, zone: 'hand' });
        });
    } else if (mode === 'multi') {
        ptState.graveyard.forEach(c => {
            if (c.id !== cardId) list.push({ card: c, zone: 'graveyard' });
        });
        ptState.mana.forEach(c => {
            if (c.id !== cardId) list.push({ card: c, zone: 'mana' });
        });
        ptState.battle.forEach(c => {
            if (!c.faceDown && c.id !== cardId) list.push({ card: c, zone: 'battle' });
        });
    }

    list.forEach(item => {
        const itemEl = document.createElement('div');
        itemEl.className = 'pt-grid-item';
        itemEl.dataset.cardId = item.card.id;
        
        const displaySrc = item.card.faceDown ? '/images/card/backimage.webp' : item.card.src;
        const displayName = item.card.faceDown ? '（裏向きのカード）' : item.card.name;

        itemEl.innerHTML = `
            <img src="${displaySrc}">
            <div class="card-qty">${displayName} (${zoneJapaneseName(item.zone)})</div>
        `;
        itemEl.onclick = () => handleEvoSelectClick(itemEl, item.card.id);
        grid.appendChild(itemEl);
    });

    // === 【修正】characteristics_id: 9 または 16 の有無によってボタンを動的に分岐 ===
    const targetCard = ptState[fromZone].find(c => c.id === cardId);
    const targetCharIds = targetCard ? (targetCard.characteristics_ids || []) : [];
    const canPlayWithoutStack = targetCharIds.some(id => [9, 16].includes(id));

    let footerHtml = `
        <button class="btn-pt-secondary" onclick="closePtModal()">キャンセル</button>
    `;

    if (canPlayWithoutStack) {
        // 重ねずに単体で出す（1人回し上の妥協配置）を可能にするボタンを配置
        footerHtml += `
            <button class="btn-pt-secondary" style="background:#fd7e14; color:white; border:none;" onclick="ptMoveCard('${cardId}', '${fromZone}', 'battle', {skipUnderCheck: true}); closePtModal();">重ねずに単体で出す</button>
        `;
    }

    footerHtml += `
        <button class="btn-pt-primary" onclick="ptExecuteEvolutionPlay('${cardId}', '${fromZone}')">決定してバトルゾーンに出す</button>
    `;

    footer.innerHTML = footerHtml;
    modal.style.display = 'flex';
    updateEvoSelectUI();
}

// 順番指定のクリックハンドラ
function handleEvoSelectClick(itemEl, cardId) {
    const idx = ptEvoSelectedIds.indexOf(cardId);
    if (idx !== -1) {
        ptEvoSelectedIds.splice(idx, 1);
    } else {
        ptEvoSelectedIds.push(cardId);
    }
    updateEvoSelectUI();
}

// 順番指定のバッジUI表示更新
function updateEvoSelectUI() {
    const items = document.querySelectorAll('#ptEvoGrid .pt-grid-item');
    items.forEach(item => {
        const cardId = item.dataset.cardId;
        const oIdx = ptEvoSelectedIds.indexOf(cardId);
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
}

// 決定処理 (指定順に underCards に重ねていく)
function ptExecuteEvolutionPlay(cardId, fromZone) {
    if (ptEvoSelectedIds.length === 0) {
        alert("重ねるカードを1枚以上選択してください。");
        return;
    }

    let card = ptState[fromZone].find(c => c.id === cardId);
    if (!card) return;

    // 進化クリーチャーを先にバトルゾーンへ
    ptMoveCard(cardId, fromZone, 'battle', { skipUnderCheck: true });

    const reversedIds = [...ptEvoSelectedIds].reverse();
    reversedIds.forEach(id => {
        let foundZone = findCardZone(id);
        if (foundZone) {
            let idx = ptState[foundZone].findIndex(c => c.id === id);
            if (idx !== -1) {
                let underCard = ptState[foundZone].splice(idx, 1)[0];
                // 【修正】強制裏向きにせず、シールドから重ねる場合を除き、元の表裏状態を維持します
                if (foundZone === 'shield') {
                    underCard.faceDown = true; 
                } else {
                    underCard.faceDown = underCard.faceDown;
                }
                
                let childUnderCards = underCard.underCards || [];
                underCard.underCards = [];
                
                card.underCards.push(underCard);
                childUnderCards.forEach(cu => {
                    card.underCards.push(cu);
                });
            }
        }
    });

    ptEvoSelectedIds = [];
    closePtModal();
    renderPtBoard();
}

let ptEvoDeckTopTempCards = [];


// 山札の上のカードを重ねる (ability_id: 193, 194)
function openEvoDeckTopModal(cardId, fromZone) {
    const modal = document.getElementById('ptListModal');
    const title = document.getElementById('ptModalTitle');
    const body = document.getElementById('ptModalBody');
    const footer = document.getElementById('ptModalFooter');

    title.innerText = "山札の上から重ねる";
    body.innerHTML = `
        <div style="padding:10px 0;">
            <p>重ねる枚数を指定してください（最大: ${ptState.deck.length}枚）：</p>
            <input type="number" id="evoDeckTopCount" min="1" max="${ptState.deck.length}" value="1" style="width:80px; padding:6px; background:#333; color:white; border:1px solid #555; border-radius:4px; font-size:16px;">
            <p style="margin-top:15px;">重ねるカードの表示状態：</p>
            <label style="margin-right:15px; cursor:pointer;"><input type="radio" name="evoDeckFace" value="faceDown" checked> 裏向きのまま</label>
            <label style="cursor:pointer;"><input type="radio" name="evoDeckFace" value="faceUp"> 表向きにする</label>
        </div>
    `;

    footer.innerHTML = `
        <button class="btn-pt-secondary" onclick="closePtModal()">キャンセル</button>
        <button class="btn-pt-primary" onclick="ptExecuteEvolutionDeckTop('${cardId}', '${fromZone}')">決定して出す</button>
    `;
    modal.style.display = 'flex';
}

// 山札の上のカードを重ねる処理の実行
function ptExecuteEvolutionDeckTop(cardId, fromZone) {
    const countInput = document.getElementById('evoDeckTopCount');
    const count = parseInt(countInput.value);
    if (isNaN(count) || count < 1 || count > ptState.deck.length) {
        alert("有効な枚数を指定してください。");
        return;
    }

    const isFaceDown = document.querySelector('input[name="evoDeckFace"]:checked').value === 'faceDown';

    if (isFaceDown) {
        // 裏向きのまま重ねる場合は、確認を挟まずに即時実行
        ptMoveCard(cardId, fromZone, 'battle', { skipUnderCheck: true });
        let card = ptState.battle.find(c => c.id === cardId);

        for (let i = 0; i < count; i++) {
            let deckCard = ptState.deck.pop();
            if (deckCard) {
                deckCard.faceDown = true;
                card.underCards.push(deckCard);
            }
        }
        closePtModal();
        renderPtBoard();
    } else {
        // 表向きにする場合：一旦カードを抜き出して確認モーダルを提示
        ptEvoDeckTopTempCards = [];
        for (let i = 0; i < count; i++) {
            let deckCard = ptState.deck.pop();
            if (deckCard) {
                deckCard.faceDown = false; // 一時的に表向きにする
                ptEvoDeckTopTempCards.push(deckCard);
            }
        }
        openEvoDeckTopConfirmModal(cardId, fromZone);
    }
}

function openEvoDeckTopConfirmModal(cardId, fromZone) {
    const modal = document.getElementById('ptListModal');
    const title = document.getElementById('ptModalTitle');
    const body = document.getElementById('ptModalBody');
    const footer = document.getElementById('ptModalFooter');

    title.innerText = "山札の上の確認（表向き）";
    body.innerHTML = `
        <p style="margin-bottom:12px; font-size:13px; color:#aaa;">山札の上から表向きにしたカードです。これらを進化元として下に重ねて出しますか？</p>
        <div class="pt-grid" id="ptEvoDeckTopConfirmGrid"></div>
    `;

    const grid = document.getElementById('ptEvoDeckTopConfirmGrid');
    ptEvoDeckTopTempCards.forEach(card => {
        const itemEl = document.createElement('div');
        itemEl.className = 'pt-grid-item';
        itemEl.innerHTML = `
            <img src="${card.src}">
            <div class="card-qty">${card.name}</div>
        `;
        grid.appendChild(itemEl);
    });

    footer.innerHTML = `
        <button class="btn-pt-secondary" onclick="ptCancelEvoDeckTop('${cardId}', '${fromZone}')">出さずに山札の上に戻す</button>
        <button class="btn-pt-primary" onclick="ptConfirmEvoDeckTop('${cardId}', '${fromZone}')">進化元にしてバトルゾーンに出す</button>
    `;
    modal.style.display = 'flex';
}

function ptConfirmEvoDeckTop(cardId, fromZone) {
    ptMoveCard(cardId, fromZone, 'battle', { skipUnderCheck: true });
    let card = ptState.battle.find(c => c.id === cardId);

    // 引いたカードをそのまま下に重ねる
    ptEvoDeckTopTempCards.forEach(deckCard => {
        deckCard.faceDown = false; // 表向きのまま重ねる
        card.underCards.push(deckCard);
    });

    ptEvoDeckTopTempCards = [];
    closePtModal();
    renderPtBoard();
}

function ptCancelEvoDeckTop(cardId, fromZone) {
    // 抜き出した時と「全く同じ順番」に戻すため、逆順にしてから push して山札に戻す
    ptEvoDeckTopTempCards.reverse().forEach(deckCard => {
        deckCard.faceDown = true; // 山札の上に戻るので裏向きに戻す
        ptState.deck.push(deckCard);
    });

    ptEvoDeckTopTempCards = [];
    closePtModal();
    renderPtBoard(); // 元の位置に留まるよう再描画
}

// 山札の上から3枚を表向きにして選択し、重ねる (ability_id: 319)
function openEvoDeckTop3Modal(cardId, fromZone) {
    ptEvoSelectedIds = [];
    ptEvoDeckTop3Cards = [];

    const modal = document.getElementById('ptListModal'); // 取得宣言を追加しエラーを解消
    const title = document.getElementById('ptModalTitle');
    const body = document.getElementById('ptModalBody');
    const footer = document.getElementById('ptModalFooter');

    title.innerText = "山札の上から3枚から選択";
    body.innerHTML = `
        <p style="margin-bottom:12px; font-size:13px; color:#aaa;">重ねるカードをクリックして順番（1番目が一番下）を指定してください。選ばなかったカードは墓地に置かれます。</p>
        <div class="pt-grid" id="ptEvoGrid"></div>
    `;

    const grid = document.getElementById('ptEvoGrid');
    const count = Math.min(3, ptState.deck.length);

    for (let i = 0; i < count; i++) {
        let c = ptState.deck.pop();
        c.faceDown = false; // 表向きにする
        ptEvoDeckTop3Cards.push(c);
    }

    ptEvoDeckTop3Cards.forEach(card => {
        const itemEl = document.createElement('div');
        itemEl.className = 'pt-grid-item';
        itemEl.dataset.cardId = card.id;
        itemEl.innerHTML = `
            <img src="${card.src}">
            <div class="card-qty">${card.name}</div>
        `;
        itemEl.onclick = () => handleEvoSelectClick(itemEl, card.id);
        grid.appendChild(itemEl);
    });

    footer.innerHTML = `
        <button class="btn-pt-secondary" onclick="ptCancelEvoDeckTop3()">キャンセル（山札に戻す）</button>
        <button class="btn-pt-primary" onclick="ptExecuteEvolutionDeckTop3('${cardId}', '${fromZone}')">決定して出す</button>
    `;
    modal.style.display = 'flex';
    updateEvoSelectUI();
}

// 3枚選択のキャンセル処理
function ptCancelEvoDeckTop3() {
    while (ptEvoDeckTop3Cards.length > 0) {
        let c = ptEvoDeckTop3Cards.pop();
        c.faceDown = false; // 墓地へ移るため表向きにします
        c.tapped = false;
        c.inverted = false;
        ptState.graveyard.push(c);
    }
    closePtModal();
    renderPtBoard();
}

// 3枚選択の決定処理
function ptExecuteEvolutionDeckTop3(cardId, fromZone) {
    let card = ptState[fromZone].find(c => c.id === cardId);
    if (!card) return;

    ptMoveCard(cardId, fromZone, 'battle', { skipUnderCheck: true });

    // 選択された順番通りに重ねる
    ptEvoSelectedIds.forEach(id => {
        const idx = ptEvoDeckTop3Cards.findIndex(c => c.id === id);
        if (idx !== -1) {
            let underCard = ptEvoDeckTop3Cards.splice(idx, 1)[0];
            // === 【修正】裏向き(true)にせず、表向き(false)の状態で下に重ねます ===
            underCard.faceDown = false; 
            card.underCards.push(underCard);
        }
    });

    // 選ばれなかった残りのカードはすべて表向きで墓地へ置く
    ptEvoDeckTop3Cards.forEach(remainCard => {
        remainCard.faceDown = false;
        ptState.graveyard.push(remainCard);
    });

    ptEvoDeckTop3Cards = [];
    ptEvoSelectedIds = [];
    closePtModal();
    renderPtBoard();
}
// ==========================================
// 超GRの上から見る(Look At GR Top) 関連処理
// ==========================================
let ptLookingGRCards = [];

function ptLookAtGRTopPrompt() {
    let input = prompt("超GRの上から何枚見ますか？", "3");
    if (input === null) return;
    let count = parseInt(input);
    if (isNaN(count) || count < 1) {
        alert("1以上の数値を入力してください。");
        return;
    }
    ptLookAtGRTop(count);
}

function ptLookAtGRTop(count) {
    ptLookingGRCards = [];
    const actualCount = Math.min(count, ptState.gr.length);
    if (actualCount === 0) {
        alert("超GRにカードがありません。");
        return;
    }

    // 上から指定枚数取り出す
    for (let i = 0; i < actualCount; i++) {
        let card = ptState.gr.pop();
        card.faceDown = false; 
        ptLookingGRCards.push(card);
    }
    
    renderLookingGRCardsModal();
}

function renderLookingGRCardsModal() {
    const modal = document.getElementById('ptListModal');
    const title = document.getElementById('ptModalTitle');
    const body = document.getElementById('ptModalBody');
    const footer = document.getElementById('ptModalFooter');

    title.innerText = `超GRの上から閲覧中`;
    body.innerHTML = `
        <p style="margin-bottom:12px; font-size:13px; color:#aaa;">表向きにした超GRカードです。各カードの下にあるボタンから直接移動させることもできます。</p>
        <div class="pt-grid" id="ptLookingGRGrid"></div>
    `;

    const grid = document.getElementById('ptLookingGRGrid');
    ptLookingGRCards.forEach(card => {
        const itemEl = document.createElement('div');
        itemEl.className = 'pt-grid-item';
        itemEl.dataset.cardId = card.id;
        itemEl.innerHTML = `
            <img src="${card.src}">
            <div class="card-qty">${card.name}</div>
            <div style="margin-top:6px; display:flex; gap:3px; justify-content:center; flex-wrap:wrap;">
                <button class="btn-pt-primary" style="padding:2px 6px; font-size:10px;" onclick="event.stopPropagation(); ptMoveLookingGRCardDirectly('${card.id}', 'battle')">出す</button>
                <button class="btn-pt-primary" style="padding:2px 6px; font-size:10px;" onclick="event.stopPropagation(); ptMoveLookingGRCardDirectly('${card.id}', 'mana')">マナ</button>
                <button class="btn-pt-primary" style="padding:2px 6px; font-size:10px;" onclick="event.stopPropagation(); ptMoveLookingGRCardDirectly('${card.id}', 'graveyard')">墓地</button>
            </div>
        `;
        grid.appendChild(itemEl);
    });

    footer.innerHTML = `
        <button class="btn-pt-secondary" onclick="ptReturnLookingGRCards('keep')">そのまま元に戻す</button>
        <button class="btn-pt-secondary" onclick="ptReturnLookingGRCards('shuffle_all')">すべて超GRに加えてシャッフル</button>
        <button class="btn-pt-secondary" onclick="ptReturnLookingGRCards('shuffle_bottom')">シャッフルして超GRの下に置く</button>
    `;

    modal.style.display = 'flex';
}

function ptMoveLookingGRCardDirectly(cardId, targetZone) {
    const idx = ptLookingGRCards.findIndex(c => c.id === cardId);
    if (idx === -1) return;
    
    let card = ptLookingGRCards.splice(idx, 1)[0];
    
    // 超GRカードの挙動制御（バトルゾーン以外に移動すると自動的に超GRに戻る仕様）に対応するため、
    // 一時的にgr配列に格納した上で、ptMoveCard関数を呼び出して移動処理を委譲します
    ptState.gr.push(card);
    ptMoveCard(cardId, 'gr', targetZone);
    
    if (ptLookingGRCards.length === 0) {
        closePtModal();
    } else {
        renderLookingGRCardsModal();
    }
}

function ptReturnLookingGRCards(action) {
    if (action === 'keep') {
        // 取り出したのと逆順にpushし、元の位置・重なり順を完全に維持します
        ptLookingGRCards.reverse().forEach(card => {
            card.faceDown = false;
            ptState.gr.push(card);
        });
    } else if (action === 'shuffle_all') {
        ptLookingGRCards.forEach(card => {
            card.faceDown = false;
            ptState.gr.push(card);
        });
        ptShuffle(ptState.gr);
    } else if (action === 'shuffle_bottom') {
        ptShuffle(ptLookingGRCards);
        ptLookingGRCards.forEach(card => {
            card.faceDown = false;
            ptState.gr.unshift(card); // 配列の先頭（＝超GRの一番下・底）に追加
        });
    }

    ptLookingGRCards = [];
    closePtModal();
    renderPtBoard();
}
// ==========================================
// 零龍システム制御処理
// ==========================================
function ptLinkZeron(cardId, ritual) {
    const card = ptState.battle.find(c => c.id === cardId);
    if (!card) return;

    if (!card.zeron_links.includes(ritual)) {
        card.zeron_links.push(ritual);
        card.zeron_count = card.zeron_links.length;
        renderPtBoard();
    }
}

function ptFlipZeron(cardId) {
    const card = ptState.battle.find(c => c.id === cardId);
    if (!card) return;

    if (!card.flipped) {
        // 《零龍》へ裏返す
        if (card.zeron_back_src) {
            card.src = card.zeron_back_src;
            card.flipped = true;
            renderPtBoard();
        } else {
            // combination_id から《零龍》の画像をAPIで取得補完
            fetch(`/api/cards/combination?card_id=${card.card_id}`)
                .then(res => res.json())
                .then(data => {
                    const zeronCard = data.find(c => c.card_name === '零龍');
                    if (zeronCard) {
                        card.zeron_back_src = getCardImageUrl(zeronCard.imagepath);
                        card.src = card.zeron_back_src;
                        card.flipped = true;
                        renderPtBoard();
                    } else {
                        executeNormalFlip(card);
                    }
                })
                .catch(() => {
                    executeNormalFlip(card);
                });
        }
    } else {
        // 《滅亡の起源 零無》へ戻す
        card.src = card.original_src;
        card.flipped = false;
        renderPtBoard();
    }
}
// ==========================================
// シールド重ね合わせ制御関連の新規処理群
// ==========================================

// 他のゾーンから既存のシールドの上に、または下に重ねるためのセレクターを表示
function openShieldStackSelector(baseCardId, position) {
    const modal = document.getElementById('ptListModal');
    const title = document.getElementById('ptModalTitle');
    const body = document.getElementById('ptModalBody');
    const footer = document.getElementById('ptModalFooter');

    title.innerText = (position === 'under' ? "シールドの下に重ねるカード選択" : "シールドの上に重ねるカード選択");
    body.innerHTML = `
        <p style="margin-bottom:12px; font-size:13px; color:#aaa;">重ねたいカードを複数選択できます。選んだカードが指定のシールドに重ねられます。</p>
        <div class="pt-grid" id="ptShieldStackGrid"></div>
    `;

    const grid = document.getElementById('ptShieldStackGrid');
    let list = [];

    // 移動可能な全てのゾーン（自身を除く）からカードを列挙
    ['hand', 'mana', 'battle', 'graveyard', 'psychic'].forEach(zone => {
        ptState[zone].forEach(c => {
            if (c.id !== baseCardId) {
                // === 【修正】バトルゾーンの重ね合わせ（束）は親と子（下敷き）をフラットに分解して個別に選択可能にする ===
                if (zone === 'battle' && c.underCards && c.underCards.length > 0) {
                    // 親カード (インデックス 0)
                    list.push({ card: c, zone: zone, parentId: c.id, index: 0 });
                    // 子カード群 (インデックス 1〜)
                    c.underCards.forEach((child, idx) => {
                        list.push({ card: child, zone: zone, parentId: c.id, index: idx + 1 });
                    });
                } else {
                    list.push({ card: c, zone: zone });
                }
            }
        });
    });

    list.forEach(item => {
        const itemEl = document.createElement('div');
        itemEl.className = 'pt-grid-item';
        itemEl.dataset.cardId = item.card.id;
        itemEl.dataset.zone = item.zone;
        
        // バトルゾーンの重ね合わせ（束）から個別に引き抜くためのメタデータ設定
        if (item.parentId !== undefined) {
            itemEl.dataset.parentId = item.parentId;
            itemEl.dataset.componentIndex = item.index;
        }

        // === 【修正】裏向きのカードはカード情報を「（裏向きのカード）」として匿名化する ===
        const displaySrc = item.card.faceDown ? '/images/card/backimage.webp' : item.card.src;
        const displayName = item.card.faceDown ? '（裏向きのカード）' : item.card.name;

        itemEl.innerHTML = `
            <img src="${displaySrc}">
            <div class="card-qty">${displayName} (${zoneJapaneseName(item.zone)})</div>
        `;
        itemEl.onclick = () => itemEl.classList.toggle('selected');
        grid.appendChild(itemEl);
    });

    footer.innerHTML = `
        <button class="btn-pt-secondary" onclick="closePtModal()">キャンセル</button>
        <button class="btn-pt-primary" onclick="ptExecuteShieldStack('${baseCardId}', '${position}', true)">裏向きで重ねる</button>
        <button class="btn-pt-primary" onclick="ptExecuteShieldStack('${baseCardId}', '${position}', false)">表向きで重ねる</button>
    `;
    modal.style.display = 'flex';
}

// 【修正箇所】既存シールドへの重ね合わせ移動を実行
function ptExecuteShieldStack(baseCardId, position, isFaceDown) {
    const selectedItems = document.querySelectorAll('#ptShieldStackGrid .pt-grid-item.selected');
    if (selectedItems.length === 0) {
        alert("カードを1枚以上選択してください。");
        return;
    }

    let baseCard = ptState.shield.find(c => c.id === baseCardId);
    if (!baseCard) {
        closePtModal();
        return;
    }

    // 重ね合わせからの一部引き抜きを判定するためのバッファ
    const battleExtractions = {};
    const normalMoves = [];

    selectedItems.forEach(item => {
        const id = item.dataset.cardId;
        const fromZone = item.dataset.zone;
        const parentId = item.dataset.parentId;
        const compIdx = item.dataset.componentIndex;

        if (fromZone === 'battle' && parentId !== undefined) {
            if (!battleExtractions[parentId]) {
                battleExtractions[parentId] = [];
            }
            battleExtractions[parentId].push(parseInt(compIdx));
        } else {
            normalMoves.push({ id: id, zone: fromZone });
        }
    });

    const cardsToMove = [];

    // A. バトルゾーンの重ね合わせ束から、選択されたカードのみを正確に抽出・再編成する
    Object.keys(battleExtractions).forEach(parentId => {
        const extractedIndices = battleExtractions[parentId];
        const battleIdx = ptState.battle.findIndex(c => c.id === parentId);
        if (battleIdx !== -1) {
            const parentCard = ptState.battle[battleIdx];
            const allComponents = [parentCard, ...parentCard.underCards];

            const moveThese = [];
            const keepThese = [];

            allComponents.forEach((card, idx) => {
                if (extractedIndices.includes(idx)) {
                    moveThese.push(card);
                } else {
                    keepThese.push(card);
                }
            });

            // バトルゾーンから一旦既存の束を削除
            ptState.battle.splice(battleIdx, 1);

            // 選ばれなかったカード（残存分）で新しくバトルゾーンの束を再構築
            if (keepThese.length > 0) {
                const newParent = keepThese[0];
                newParent.underCards = keepThese.slice(1);
                ptState.battle.splice(battleIdx, 0, newParent);
            }

            // 移動対象バッファに選択分を格納
            moveThese.forEach(c => {
                c.underCards = []; // 下敷き連動を個別にクリア
                cardsToMove.push(c);
            });
        }
    });

    // B. 重ね合わせのない通常カード（手札、マナ、墓地等）の移動処理
    normalMoves.forEach(move => {
        const idx = ptState[move.zone].findIndex(c => c.id === move.id);
        if (idx !== -1) {
            let card = ptState[move.zone].splice(idx, 1)[0];
            let childUnderCards = card.underCards || [];
            card.underCards = [];

            cardsToMove.push(card);
            childUnderCards.forEach(cu => cardsToMove.push(cu));
        }
    });

    // C. 抽出されたすべてのカードをシールドへ配置（上または下）
    cardsToMove.forEach(card => {
        card.faceDown = isFaceDown;
        card.tapped = false;
        card.inverted = false;

        if (position === 'under') {
            // シールドの下に重ねる
            baseCard.underCards.push(card);
        } else {
            // シールドの上に重ねる (新しく重ねられたカードを新代表に差し替え)
            card.underCards = [baseCard, ...baseCard.underCards];
            baseCard.underCards = [];
            
            const baseIdx = ptState.shield.findIndex(c => c.id === baseCard.id);
            if (baseIdx !== -1) {
                ptState.shield[baseIdx] = card;
                baseCard = card; // 連続して重ねる場合の親情報を同期
            }
        }
    });

    closePtModal();
    renderPtBoard();
}

// 山札の上の1枚を既存のシールドの上または下に重ねる
function ptStackDeckTopToShield(baseCardId, position) {
    if (ptState.deck.length === 0) {
        alert("山札にカードがありません。");
        return;
    }
    const isFaceDown = confirm("シールドに重ねるカードを「裏向き」にしますか？\n（「キャンセル」を選択すると表向きに重ねます）");
    let baseCard = ptState.shield.find(c => c.id === baseCardId);
    if (!baseCard) return;

    let deckCard = ptState.deck.pop();
    deckCard.faceDown = isFaceDown;

    if (position === 'under') {
        baseCard.underCards.push(deckCard);
    } else {
        deckCard.underCards = [baseCard, ...baseCard.underCards];
        baseCard.underCards = [];
        const baseIdx = ptState.shield.findIndex(c => c.id === baseCardId);
        if (baseIdx !== -1) {
            ptState.shield[baseIdx] = deckCard;
        }
    }
    renderPtBoard();
}

// 一括選択した複数枚を「1つの新規シールド束」として盤面にセット
function ptExecuteBatchShieldStack(isFaceDown) {
    if (selectedCards.size === 0) return;
    
    let collectedCards = [];
    selectedCards.forEach(id => {
        const from = findCardZone(id);
        if (from) {
            const idx = ptState[from].findIndex(c => c.id === id);
            if (idx !== -1) {
                let card = ptState[from].splice(idx, 1)[0];
                card.faceDown = isFaceDown;
                card.tapped = false;
                card.inverted = false;
                
                let childUnderCards = card.underCards || [];
                card.underCards = [];
                
                collectedCards.push(card);
                childUnderCards.forEach(cu => {
                    cu.faceDown = isFaceDown;
                    collectedCards.push(cu);
                });
            }
        }
    });

    if (collectedCards.length > 0) {
        // 先頭（1枚目）を代表親シールドとし、残りを underCards にスタック
        let parentCard = collectedCards.shift();
        parentCard.underCards = collectedCards;
        ptState.shield.push(parentCard);
    }

    selectedCards.clear();
    closePtModal();
    renderPtBoard();
}

// ==========================================
// 重ねられたシールドの個別・一括移動処理
// ==========================================

// 重ねられたシールド束から「特定の1枚」を引き抜いて別のゾーンへ移動させる処理
function ptMoveShieldComponent(parentId, cardId, toZone) {
    const shieldIdx = ptState.shield.findIndex(c => c.id === parentId);
    if (shieldIdx === -1) return;

    const parentCard = ptState.shield[shieldIdx];
    const allComponents = [parentCard, ...parentCard.underCards];

    // 対象となるカードを特定
    const targetIdx = allComponents.findIndex(c => c.id === cardId);
    if (targetIdx === -1) return;

    let targetCard = allComponents.splice(targetIdx, 1)[0];

    // 既存のシールド束を盤面から一旦取り除く
    ptState.shield.splice(shieldIdx, 1);

    // 残されたカードがあれば、新たな「代表親シールド」をセットし再構築して盤面に戻す
    if (allComponents.length > 0) {
        const newParent = allComponents[0];
        newParent.underCards = allComponents.slice(1);
        ptState.shield.splice(shieldIdx, 0, newParent);
    }

    // 移動するカードの状態を初期化（下敷き関係等はクリア）
    targetCard.underCards = [];
    targetCard.faceDown = false; // シールドを離れるため表向きに
    targetCard.tapped = false;
    targetCard.inverted = false;

    // 指定ゾーンへ格納
    if (toZone === 'hand') {
        ptState.hand.push(targetCard);
    } else if (toZone === 'mana') {
        targetCard.inverted = true;
        targetCard.tapped = targetCard.is_multicolor; // 多色タップ
        ptState.mana.push(targetCard);
    } else if (toZone === 'graveyard') {
        ptState.graveyard.push(targetCard);
    }

    renderPtBoard();

    // モーダル表示を更新
    if (allComponents.length === 0) {
        closePtModal();
    } else {
        // 残った構成の中で新しい親カードを基準にして回収モーダルを再描画
        const updatedParent = ptState.shield.find(c => c.id === allComponents[0].id);
        if (updatedParent) {
            ptOpenShieldBreakModal(updatedParent);
        } else {
            closePtModal();
        }
    }
}

// 重ねられたシールド束の「すべて」をまとめて別のゾーンへ一括移動させる処理
function ptMoveAllShieldComponents(parentId, toZone) {
    const shieldIdx = ptState.shield.findIndex(c => c.id === parentId);
    if (shieldIdx === -1) return;

    const parentCard = ptState.shield[shieldIdx];
    const allComponents = [parentCard, ...parentCard.underCards];

    // 盤面のシールドから束ごと削除
    ptState.shield.splice(shieldIdx, 1);

    allComponents.forEach(card => {
        card.underCards = [];
        card.faceDown = false;
        card.tapped = false;
        card.inverted = false;

        if (toZone === 'hand') {
            ptState.hand.push(card);
        } else if (toZone === 'mana') {
            card.inverted = true;
            card.tapped = card.is_multicolor;
            ptState.mana.push(card);
        }
    });

    renderPtBoard();
}

// ==========================================
// 封印付与（山札からカードを裏向きに重ねる）処理
// ==========================================

// 封印を装着するための枚数指定プロンプト
function ptSealCardPrompt(cardId) {
    const card = ptState.battle.find(c => c.id === cardId);
    if (!card) return;

    const input = prompt(`「${card.name}」に山札の上から何枚封印を付けますか？`, "1");
    if (input === null) return; // キャンセルされた場合

    const count = parseInt(input, 10);
    if (isNaN(count) || count < 1) {
        alert("1以上の有効な数値を入力してください。");
        return;
    }

    if (ptState.deck.length < count) {
        alert("山札の残り枚数が足りません。");
        return;
    }

    ptExecuteSeal(cardId, count);
}

// 山札から指定枚数ポップし、裏向きにして対象カードの下敷き（封印）にする
function ptExecuteSeal(cardId, count) {
    const card = ptState.battle.find(c => c.id === cardId);
    if (!card) return;

    for (let i = 0; i < count; i++) {
        let deckCard = ptState.deck.pop();
        if (deckCard) {
            deckCard.faceDown = true; // 封印なので裏向きにする
            deckCard.tapped = false;
            deckCard.inverted = false;
            card.underCards.push(deckCard);
        }
    }

    renderPtBoard();
}

/**
 * シールドドロップ時の詳細選択モーダルを展開
 */
function ptOpenShieldDropSelectModal(cardId, fromZone) {
    const card = ptState[fromZone].find(c => c.id === cardId);
    if (!card) return;

    const hasUnder = (fromZone === 'battle' && card.underCards && card.underCards.length > 0);
    const existingShields = ptState.shield;

    const modal = document.getElementById('ptListModal');
    const title = document.getElementById('ptModalTitle');
    const body = document.getElementById('ptModalBody');
    const footer = document.getElementById('ptModalFooter');

    title.innerText = "シールドゾーンへの配置設定";
    ptSelectedDropShieldTargetId = null;

    let html = `<div style="padding: 10px 0;">`;

    // 1. バトルゾーンの複数枚構成カードを移動させる場合
    if (hasUnder) {
        html += `
            <p style="font-weight: bold; color: #ffc107; margin-bottom: 12px; font-size: 13px;">
                ⚠️ 複数枚（計 ${card.underCards.length + 1} 枚）で構成されているカードを移動しようとしています。
            </p>
            <div style="background: rgba(255,255,255,0.05); padding: 10px; border-radius: 4px; margin-bottom: 15px;">
                <p style="margin: 0 0 8px 0; font-size: 12px; font-weight: bold; color: #aaa;">【複数構成カードの扱い】</p>
                <label style="margin-right: 15px; cursor: pointer; font-size: 13px;">
                    <input type="radio" name="ptShieldBundleMode" value="bundle" checked> 1つの束（重ねたままで移動）
                </label>
                <label style="cursor: pointer; font-size: 13px;">
                    <input type="radio" name="ptShieldBundleMode" value="scatter"> 分解（すべて個別のシールドとして追加）
                </label>
            </div>
        `;
    }

    // 2. 表向き・裏向きの選択
    html += `
        <p style="margin: 0 0 8px 0; font-size: 12px; font-weight: bold; color: #aaa;">【シールドの表示向き】</p>
        <div style="background: rgba(255,255,255,0.05); padding: 10px; border-radius: 4px; margin-bottom: 15px;">
            <label style="margin-right: 20px; cursor: pointer; font-size: 13px;">
                <input type="radio" name="ptShieldFace" value="faceDown" checked> 裏向きで配置
            </label>
            <label style="cursor: pointer; font-size: 13px;">
                <input type="radio" name="ptShieldFace" value="faceUp"> 表向きで配置
            </label>
        </div>
    `;

    // 3. 配置アクションの選択
    html += `
        <p style="margin: 0 0 8px 0; font-size: 12px; font-weight: bold; color: #aaa;">【配置先の選択】</p>
        <button class="btn-pt-primary" onclick="ptExecuteShieldDrop('${cardId}', '${fromZone}', 'new')" style="width: 100%; padding: 10px; font-weight: bold; margin-bottom: 15px;">
            🛡️ 新しいシールドとして追加
        </button>
    `;

    if (existingShields.length > 0) {
        html += `
            <p style="margin: 12px 0 6px 0; font-size: 12px; color: #bbb; border-top: 1px solid #333; padding-top: 10px;">
                既存シールドの上に重ねて配置（対象シールドを以下から選択してください）：
            </p>
            <div class="pt-grid" id="ptDropShieldTargetGrid" style="max-height: 160px; overflow-y: auto; padding: 5px; background: rgba(0,0,0,0.2); border-radius: 4px;">
        `;
        existingShields.forEach(sCard => {
            const displaySrc = sCard.faceDown ? '/images/card/backimage.webp' : sCard.src;
            const displayName = sCard.faceDown ? '（裏向きシールド）' : sCard.name;
            html += `
                <div class="pt-grid-item" data-shield-id="${sCard.id}" onclick="ptSelectDropShieldTarget(this)" style="padding: 4px; border: 1px solid #444;">
                    <img src="${displaySrc}" style="width: 55px; height: 77px;">
                    <div class="card-qty" style="font-size: 9px; line-height: 1.1; margin-top: 4px;">${displayName}</div>
                </div>
            `;
        });
        html += `</div>`;
    }

    html += `</div>`;
    body.innerHTML = html;

    // フッターのボタン設定
    let footerHtml = `<button class="btn-pt-secondary" onclick="closePtModal()">キャンセル</button>`;
    if (existingShields.length > 0) {
        footerHtml += `
            <button class="btn-pt-primary" id="btnPtShieldDropStack" onclick="ptExecuteShieldDrop('${cardId}', '${fromZone}', 'stack')" disabled style="opacity: 0.5;">
                選択したシールドの上に重ねる
            </button>
        `;
    }
    footer.innerHTML = footerHtml;

    modal.style.display = 'flex';
}

/**
 * 重ねる対象シールドをクリック選択した際のアクティブ表示切替
 */
function ptSelectDropShieldTarget(element) {
    document.querySelectorAll('#ptDropShieldTargetGrid .pt-grid-item').forEach(el => el.classList.remove('selected'));
    element.classList.add('selected');
    ptSelectedDropShieldTargetId = element.dataset.shieldId;
    
    const btn = document.getElementById('btnPtShieldDropStack');
    if (btn) {
        btn.disabled = false;
        btn.style.opacity = '1';
    }
}

/**
 * 配置オプションに則ってシールドゾーンへの移動を実行
 */
function ptExecuteShieldDrop(cardId, fromZone, placementType) {
    const card = ptState[fromZone].find(c => c.id === cardId);
    if (!card) return;

    const faceRadio = document.querySelector('input[name="ptShieldFace"]:checked');
    const isFaceDown = faceRadio ? (faceRadio.value === 'faceDown') : true;

    const bundleRadio = document.querySelector('input[name="ptShieldBundleMode"]:checked');
    const isScatter = bundleRadio ? (bundleRadio.value === 'scatter') : false;

    let cardIdx = ptState[fromZone].findIndex(c => c.id === cardId);
    if (cardIdx === -1) return;

    let targetCards = [];

    // 移動元データを抽出し配列化
    if (fromZone === 'battle' && card.underCards && card.underCards.length > 0) {
        if (isScatter) {
            let removedCard = ptState[fromZone].splice(cardIdx, 1)[0];
            targetCards.push(removedCard);
            removedCard.underCards.forEach(uc => {
                targetCards.push(uc);
            });
            removedCard.underCards = [];
        } else {
            let removedCard = ptState[fromZone].splice(cardIdx, 1)[0];
            targetCards.push(removedCard);
        }
    } else {
        let removedCard = ptState[fromZone].splice(cardIdx, 1)[0];
        targetCards.push(removedCard);
    }

    if (placementType === 'new') {
        // 新規シールドとして配置
        targetCards.forEach(c => {
            // === 【修正】表表向き指定（isFaceDown）を第3引数で渡すように修正 ===
            if (ptCheckAndReturnSpecialCard(c, 'shield', { faceDown: isFaceDown })) {
                return;
            }
            c.faceDown = isFaceDown;
            c.tapped = false;
            c.inverted = false;
            if (isScatter) {
                c.underCards = [];
            } else {
                c.underCards.forEach(uc => {
                    uc.faceDown = isFaceDown;
                });
            }
            ptState.shield.push(c);
        });
    } else if (placementType === 'stack') {
        // 既存シールドの上に重ね合わせ
        if (!ptSelectedDropShieldTargetId) {
            alert("重ねる対象のシールドを選択してください。");
            return;
        }

        let baseCard = ptState.shield.find(c => c.id === ptSelectedDropShieldTargetId);
        if (baseCard) {
            targetCards.forEach(c => {
                // === 【修正】表向き指定（isFaceDown）を第3引数で渡すように修正 ===
                if (ptCheckAndReturnSpecialCard(c, 'shield', { faceDown: isFaceDown })) {
                    return;
                }
                c.faceDown = isFaceDown;
                c.tapped = false;
                c.inverted = false;

                if (isScatter) {
                    c.underCards = [];
                    c.underCards = [baseCard, ...baseCard.underCards];
                    baseCard.underCards = [];
                    
                    const baseIdx = ptState.shield.findIndex(sh => sh.id === baseCard.id);
                    if (baseIdx !== -1) {
                        ptState.shield[baseIdx] = c;
                        baseCard = c;
                    }
                } else {
                    c.underCards.forEach(uc => {
                        uc.faceDown = isFaceDown;
                    });
                    const originalUnderOfC = [...c.underCards];
                    c.underCards = [...originalUnderOfC, baseCard, ...baseCard.underCards];
                    baseCard.underCards = [];
                    
                    const baseIdx = ptState.shield.findIndex(sh => sh.id === baseCard.id);
                    if (baseIdx !== -1) {
                        ptState.shield[baseIdx] = c;
                        baseCard = c;
                    }
                }
            });
        }
    }

    ptSelectedDropShieldTargetId = null;
    closePtModal();
    renderPtBoard();
}

/**
 * 複数枚構成シールドから移動するカードを選択するモーダル
 */
function ptOpenShieldUnderCardSelectModal(parentCard, toZone, opts = {}) {
    const modal = document.getElementById('ptListModal');
    const title = document.getElementById('ptModalTitle');
    const body = document.getElementById('ptModalBody');
    const footer = document.getElementById('ptModalFooter');

    title.innerText = "移動するシールドカードの選択";
    body.innerHTML = `
        <p style="margin-bottom:12px; font-size:13px; color:#aaa;">
            移動させるカードを選択してください。選ばなかったカードはシールドゾーンに残ります。
        </p>
        <div class="pt-grid" id="ptShieldUnderSelectGrid"></div>
    `;

    const grid = document.getElementById('ptShieldUnderSelectGrid');
    const allComponents = [parentCard, ...parentCard.underCards];

    allComponents.forEach((card, idx) => {
        const itemEl = document.createElement('div');
        itemEl.className = 'pt-grid-item';
        
        // === 【修正】カードの faceDown 状態を尊重し、裏向きのカードは裏面画像を表示します ===
        const isGR = card.characteristics_ids && card.characteristics_ids.includes(10);
        const backPath = isGR ? '/images/card/gr_backimage.webp' : '/images/card/backimage.webp';
        
        const displaySrc = card.faceDown ? backPath : card.src;
        const displayName = card.faceDown ? '（裏向きのカード）' : card.name;

        itemEl.innerHTML = `
            <img src="${displaySrc}">
            <div class="card-qty">${displayName}</div>
        `;
        
        itemEl.onclick = () => {
            itemEl.classList.toggle('selected');
        };
        itemEl.dataset.componentIndex = idx;
        grid.appendChild(itemEl);
    });

    footer.innerHTML = '';

    const cancelBtn = document.createElement('button');
    cancelBtn.className = 'btn-pt-secondary';
    cancelBtn.innerText = 'キャンセル';
    cancelBtn.onclick = function() {
        closePtModal();
    };

    const confirmBtn = document.createElement('button');
    confirmBtn.className = 'btn-pt-primary';
    confirmBtn.innerText = '選択したカードを移動';
    confirmBtn.onclick = function() {
        ptExecuteShieldUnderCardSelectMove(parentCard.id, toZone, opts);
    };

    footer.appendChild(cancelBtn);
    footer.appendChild(confirmBtn);

    modal.style.display = 'flex';
}
/**
 * 選択されたシールドカードの移動と、残されたシールドカードの再構成処理
 */
function ptExecuteShieldUnderCardSelectMove(parentId, toZone, opts = {}) {
    const selectedItems = document.querySelectorAll('#ptShieldUnderSelectGrid .pt-grid-item.selected');
    if (selectedItems.length === 0) {
        alert("移動するカードを1枚以上選択してください。");
        return;
    }

    const shieldIdx = ptState.shield.findIndex(c => c.id === parentId);
    if (shieldIdx === -1) {
        closePtModal();
        return;
    }

    const parentCard = ptState.shield[shieldIdx];
    const allComponents = [parentCard, ...parentCard.underCards];

    const toMoveIndexes = [];
    selectedItems.forEach(item => {
        toMoveIndexes.push(parseInt(item.dataset.componentIndex));
    });

    const moveCards = [];
    const remainCards = [];

    allComponents.forEach((card, idx) => {
        if (toMoveIndexes.includes(idx)) {
            moveCards.push(card);
        } else {
            remainCards.push(card);
        }
    });

    // 元の束をシールドゾーンから一旦削除
    ptState.shield.splice(shieldIdx, 1);

    // 残されたカードがある場合、新しい代表親にしてシールドを再編成
    if (remainCards.length > 0) {
        const newParent = remainCards[0];
        newParent.underCards = remainCards.slice(1);
        ptState.shield.splice(shieldIdx, 0, newParent);
    }

    // 選択されたカードを指定のゾーンへ移動
    moveCards.forEach(card => {
        // === 【修正】GR/サイキックカードの特別帰還チェック ===
        if (ptCheckAndReturnSpecialCard(card, toZone)) {
            return;
        }

        card.underCards = [];
        card.faceDown = false; // シールドゾーンを離れるため表向きにリセット
        card.tapped = false;
        card.inverted = false;

        const cardtypeIds = card.cardtype_ids || [];

        if (toZone === 'battle') {
            if (cardtypeIds.some(id => [6, 7, 8].includes(id))) {
                card.tapped = true;
            }
            ptState.battle.push(card);
        } else if (toZone === 'mana') {
            card.inverted = true;
            card.tapped = card.is_multicolor;
            ptState.mana.push(card);
        } else if (toZone === 'shield') {
            card.faceDown = (opts.faceDown !== undefined) ? opts.faceDown : true;
            ptState.shield.push(card);
        } else if (toZone === 'hand') {
            ptState.hand.push(card);
        } else if (toZone === 'graveyard') {
            ptState.graveyard.push(card);
        } else if (toZone === 'deck') {
            if (opts.bottom) {
                ptState.deck.unshift(card);
            } else {
                ptState.deck.push(card);
            }
        } else {
            ptState[toZone].push(card);
        }
    });

    closePtModal();
    renderPtBoard();
}

/**
 * オーラ用スタイルシートの動的適用
 */
if (!document.getElementById('ptAuraStyle')) {
    const style = document.createElement('style');
    style.id = 'ptAuraStyle';
    style.innerHTML = `
        .playtest-card.has-aura {
            overflow: visible !important;
        }
        .playtest-aura-card {
            position: absolute;
            width: 90px;
            height: 126px;
            left: 0;
            transform: rotate(-90deg);
            transform-origin: center center;
            pointer-events: none;
            z-index: 10;
            box-shadow: 0 -2px 6px rgba(0,0,0,0.5);
            border-radius: 4px;
        }
        .playtest-seal-card {
            position: absolute;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 18; /* オーラより前面に表示 */
            box-shadow: 0 2px 6px rgba(0,0,0,0.6);
            border-radius: 4px;
        }
    `;
    document.head.appendChild(style);
}

/**
 * オレガ・オーラの出し方選択モーダル
 */
function ptOpenAuraPlaySelector(cardId, fromZone) {
    const auraCard = ptState[fromZone].find(c => c.id === cardId);
    if (!auraCard) return;

    const modal = document.getElementById('ptListModal');
    const title = document.getElementById('ptModalTitle');
    const body = document.getElementById('ptModalBody');
    const footer = document.getElementById('ptModalFooter');

    const charIds = auraCard.characteristics_ids || [];
    const isDoubleGR = charIds.includes(12); // W・GR召喚オーラ

    // バトルゾーン上の既存のGRクリーチャー (characteristics_id: 10) を取得
    const existingGRs = ptState.battle.filter(c => c.characteristics_ids && c.characteristics_ids.includes(10));

    title.innerText = isDoubleGR ? "W・オレガ・オーラの使用" : "オレガ・オーラの使用";
    
    let html = `
        <div style="padding: 10px 0; text-align: center;">
            <img src="${auraCard.src}" style="width: 120px; height: 168px; border-radius: 6px; margin-bottom: 15px; box-shadow: 0 4px 10px rgba(0,0,0,0.5);">
            <p style="text-align: left; font-size: 13px; color: #aaa; margin-bottom: 15px;">
                このオーラカードの装着（プレイ）方法を選択してください。
            </p>
    `;

    if (isDoubleGR) {
        // === ケース1: 特性12 (W・GRオーラ) の選択肢 ===
        html += `
            <button class="btn-pt-primary" onclick="ptExecuteDoubleGRAuraPlay('${cardId}', '${fromZone}', 'double_gr')" style="width: 100%; padding: 12px; font-weight: bold; margin-bottom: 12px;">
                🤖 超GRの上から2枚出し、どちらか1枚にこのオーラを重ねる
            </button>
        `;
        if (existingGRs.length > 0) {
            html += `
                <button class="btn-pt-primary" onclick="ptOpenAuraTargetSelector('${cardId}', '${fromZone}', 'existing_and_gr')" style="width: 100%; padding: 12px; font-weight: bold; background: #28a745;">
                    ⚔️ 既存のGRにこのオーラを重ね、さらに超GRから1枚出す
                </button>
            `;
        } else {
            html += `<p style="font-size: 11px; color: #555;">(既存のGRクリーチャーがいないため、既存重ねは選べません)</p>`;
        }
    } else {
        // === ケース2: 通常のオーラ の選択肢 ===
        html += `
            <button class="btn-pt-primary" onclick="ptExecuteSingleGRAuraPlay('${cardId}', '${fromZone}')" style="width: 100%; padding: 12px; font-weight: bold; margin-bottom: 12px;">
                🤖 超GRの一番上をバトルゾーンに出し、その上に重ねる
            </button>
        `;
        if (existingGRs.length > 0) {
            html += `
                <button class="btn-pt-primary" onclick="ptOpenAuraTargetSelector('${cardId}', '${fromZone}', 'existing_only')" style="width: 100%; padding: 12px; font-weight: bold; background: #28a745;">
                    ⚔️ バトルゾーンの既存GRクリーチャーの上に重ねる
                </button>
            `;
        } else {
            html += `<p style="font-size: 11px; color: #555;">(既存のGRクリーチャーがいないため、既存重ねは選べません)</p>`;
        }
    }

    html += `</div>`;
    body.innerHTML = html;
    footer.innerHTML = `<button class="btn-pt-secondary" onclick="closePtModal()">キャンセル</button>`;
    
    modal.style.display = 'flex';
}

/**
 * 既存のGRクリーチャーを選択させる画面
 */
function ptOpenAuraTargetSelector(cardId, fromZone, mode) {
    const modal = document.getElementById('ptListModal');
    const title = document.getElementById('ptModalTitle');
    const body = document.getElementById('ptModalBody');
    const footer = document.getElementById('ptModalFooter');

    title.innerText = "装着対象GRクリーチャーの選択";
    body.innerHTML = `
        <p style="margin-bottom:12px; font-size:13px; color:#aaa;">オーラを重ねて装着させるGRクリーチャーを選択してください。</p>
        <div class="pt-grid" id="ptAuraTargetGrid"></div>
    `;

    const grid = document.getElementById('ptAuraTargetGrid');
    const grList = ptState.battle.filter(c => c.characteristics_ids && c.characteristics_ids.includes(10));

    grList.forEach(gr => {
        const itemEl = document.createElement('div');
        itemEl.className = 'pt-grid-item';
        itemEl.innerHTML = `
            <img src="${gr.src}">
            <div class="card-qty">${gr.name}</div>
        `;
        itemEl.onclick = () => {
            ptExecuteAuraExistingPlay(cardId, fromZone, gr.id, mode);
        };
        grid.appendChild(itemEl);
    });

    footer.innerHTML = `<button class="btn-pt-secondary" onclick="ptOpenAuraPlaySelector('${cardId}', '${fromZone}')">戻る</button>`;
}

/**
 * 既存のGRの上に重ねる動作の実装
 */
function ptExecuteAuraExistingPlay(auraId, fromZone, targetGrId, mode) {
    let auraIdx = ptState[fromZone].findIndex(c => c.id === auraId);
    if (auraIdx === -1) return;
    let aura = ptState[fromZone].splice(auraIdx, 1)[0];

    let targetGr = ptState.battle.find(c => c.id === targetGrId);
    if (targetGr) {
        // 重ねる（underCardsにpushする）
        targetGr.underCards.push(aura);
    }

    // W・GR召喚モードの場合は、追加で超GRから1枚出す
    if (mode === 'existing_and_gr') {
        if (ptState.gr.length > 0) {
            let extraGr = ptState.gr.pop();
            extraGr.tapped = false;
            extraGr.faceDown = false;
            ptState.battle.push(extraGr);
        } else {
            alert("超GRのカードが足りないため、追加GR召喚を行えませんでした。");
        }
    }

    closePtModal();
    renderPtBoard();
}

/**
 * 超GRから1枚出して、その上にこのオーラを重ねる処理（通常プレイ）
 */
function ptExecuteSingleGRAuraPlay(auraId, fromZone) {
    if (ptState.gr.length === 0) {
        alert("超GRにカードがありません。");
        return;
    }

    let auraIdx = ptState[fromZone].findIndex(c => c.id === auraId);
    if (auraIdx === -1) return;
    let aura = ptState[fromZone].splice(auraIdx, 1)[0];

    // GR召喚
    let grCard = ptState.gr.pop();
    grCard.tapped = false;
    grCard.faceDown = false;

    // GRの上にオーラを重ねる
    grCard.underCards.push(aura);
    ptState.battle.push(grCard);

    closePtModal();
    renderPtBoard();
}

/**
 * 特性12 (W・GRオーラ) で超GRの上から2枚を出す処理
 */
function ptExecuteDoubleGRAuraPlay(auraId, fromZone) {
    if (ptState.gr.length < 2) {
        alert("超GRに2枚以上のカードがありません。");
        return;
    }

    let auraIdx = ptState[fromZone].findIndex(c => c.id === auraId);
    if (auraIdx === -1) return;
    let aura = ptState[fromZone].splice(auraIdx, 1)[0];

    // 2枚ポップしてバトルゾーンに格納
    let grCard1 = ptState.gr.pop();
    grCard1.tapped = false;
    grCard1.faceDown = false;

    let grCard2 = ptState.gr.pop();
    grCard2.tapped = false;
    grCard2.faceDown = false;

    ptState.battle.push(grCard1);
    ptState.battle.push(grCard2);

    // どちらにオーラを重ねるか選択させる画面を開く
    const modal = document.getElementById('ptListModal');
    const title = document.getElementById('ptModalTitle');
    const body = document.getElementById('ptModalBody');
    const footer = document.getElementById('ptModalFooter');

    title.innerText = "オーラ重ね合わせ対象の指定（W・GR召喚）";
    body.innerHTML = `
        <p style="margin-bottom:15px; font-size:13px; color:#aaa; text-align:center;">
            超GRから2枚のクリーチャーを召喚しました。<br>オレガ・オーラ「${aura.name}」をどちらの上に重ねて装着させますか？
        </p>
        <div style="display:flex; gap:20px; justify-content:center; align-items:center;">
            <div style="text-align:center; cursor:pointer;" onclick="ptAttachAuraToSpecific('${aura.id}', '${grCard1.id}'); closePtModal();">
                <img src="${grCard1.src}" style="width:110px; height:154px; border-radius:6px; box-shadow:0 4px 10px rgba(0,0,0,0.5); margin-bottom:8px;">
                <div style="font-size:11px; font-weight:bold;">${grCard1.name}の上に重ねる</div>
            </div>
            <div style="text-align:center; cursor:pointer;" onclick="ptAttachAuraToSpecific('${aura.id}', '${grCard2.id}'); closePtModal();">
                <img src="${grCard2.src}" style="width:110px; height:154px; border-radius:6px; box-shadow:0 4px 10px rgba(0,0,0,0.5); margin-bottom:8px;">
                <div style="font-size:11px; font-weight:bold;">${grCard2.name}の上に重ねる</div>
            </div>
        </div>
    `;

    // 完了時に一時データを残さないため、オーラ情報を一時グローバル等に預けず関数引数経由にする
    window.ptTempAura = aura;

    footer.innerHTML = ``; // 選択必須のため、キャンセルは不可（戻す場合は手動回収）
    modal.style.display = 'flex';
}

/**
 * 2つのGRクリーチャーのどちらかにオーラを重ね合わせ適用
 */
function ptAttachAuraToSpecific(auraId, targetGrId) {
    const aura = window.ptTempAura;
    if (!aura) return;

    let targetGr = ptState.battle.find(c => c.id === targetGrId);
    if (targetGr) {
        targetGr.underCards.push(aura);
    }

    delete window.ptTempAura;
    renderPtBoard();
}
function ptCheckAndReturnSpecialCard(card, toZone, opts = {}) {
    if (toZone === 'battle' || toZone === 'gr' || toZone === 'psychic') {
        return false;
    }
    const hasGR = card.characteristics_ids && card.characteristics_ids.includes(10);
    const hasPsychic = card.characteristics_ids && card.characteristics_ids.some(id => [3, 6].includes(id));

    if (hasGR || hasPsychic) {
        // 重ねられていたオーラや封印、進化元などを切り離して救出
        if (card.underCards && card.underCards.length > 0) {
            const attachedCards = [...card.underCards];
            card.underCards = []; // GRクリーチャー本体の下敷きからクリア

            // 切り離されたカード群を、本来の目的地（toZone）へ移動配置
            attachedCards.forEach(attached => {
                attached.underCards = [];
                attached.tapped = false;
                attached.inverted = false;

                if (toZone === 'shield') {
                    // === 【修正】親カードのシールド表示向き設定（表向き/裏向き）をオーラにも連動して適用 ===
                    attached.faceDown = (opts.faceDown !== undefined) ? opts.faceDown : true;
                    ptState.shield.push(attached);
                } else if (toZone === 'hand') {
                    attached.faceDown = false;
                    ptState.hand.push(attached);
                } else if (toZone === 'graveyard') {
                    attached.faceDown = false;
                    ptState.graveyard.push(attached);
                } else if (toZone === 'mana') {
                    attached.faceDown = false;
                    attached.inverted = true;
                    attached.tapped = attached.is_multicolor;
                    ptState.mana.push(attached);
                } else if (toZone === 'deck') {
                    attached.faceDown = true;
                    ptState.deck.push(attached);
                } else {
                    ptState[toZone].push(attached);
                }
            });
        }

        // GR/サイキック本体の帰還処理
        if (hasGR) {
            card.tapped = false; card.inverted = false; card.faceDown = false;
            ptState.gr.unshift(card);
            alert(`ルールにより、超GRクリーチャー「${card.name}」は超GRの一番下に戻されました。付随していたオーラ等は${zoneJapaneseName(toZone)}に移動しました。`);
        } else if (hasPsychic) {
            card.tapped = false; card.inverted = false; card.faceDown = false;
            ptState.psychic.push(card);
            alert(`ルールにより、サイキック・クリーチャー「${card.name}」は超次元に戻されました。付随していたオーラ等は${zoneJapaneseName(toZone)}に移動しました。`);
        }
        return true;
    }
    return false;
}

function ptExitGame() {
    // 1. まずタブのクローズを試みる
    window.close();
    
    // 2. ブラウザ制限等で閉じられなかった場合のフォールバック遷移
    setTimeout(() => {
        window.location.href = '/mydecks';
    }, 150);
}
</script>
</body>
</html>