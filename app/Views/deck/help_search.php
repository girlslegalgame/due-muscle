<!-- app/Views/deck/help_search.php -->
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>カード検索（ヘルプ）</title>
    <style>
        body { font-family: sans-serif; background: #f0f0f0; margin: 0; padding: 20px; }
        .search-container { max-width: 1000px; margin: 0 auto; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .input-group { margin-bottom: 15px; }
        .input-text { width: 100%; padding: 10px; font-size: 16px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        .checkbox-group { display: flex; gap: 15px; margin-bottom: 15px; flex-wrap: wrap; }
        .filter-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 10px; margin-bottom: 20px; }
        .filter-btn { padding: 10px; background: #eee; border: 1px solid #ccc; border-radius: 4px; cursor: pointer; text-align: center; font-size: 13px; font-weight: bold; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        
        /* 文明フィルターエリア */
        .civ-filter-box { border: 1px solid #ddd; padding: 10px; border-radius: 4px; background: #f9f9f9; margin-bottom: 15px; }
        .exclude-civs { display: flex; gap: 10px; margin-top: 10px; flex-wrap: wrap; border-top: 1px dashed #ccc; padding-top: 10px; }

        /* 横5列のカード表示グリッド */
        .card-grid { 
            display: flex !important; 
            flex-wrap: wrap; 
            gap: 12px; 
            margin-top: 20px; 
            justify-content: center; /* カード群を中央寄せにします（左寄せなら flex-start） */
        }
        
        .card-grid img { 
            height: 180px;       /* 縦幅（高さ）をすべて同じに固定します */
            width: auto;         /* 横幅は元画像の比率を自動維持させます */
            object-fit: contain; /* 画像を歪ませずに枠内に収めます */
            border-radius: 4px; 
            border: 1px solid #ddd; 
            cursor: pointer; 
            transition: transform 0.15s ease;
        }

        .card-grid img:hover {
            transform: scale(1.05); /* ホバー時に少し拡大する視覚効果 */
        }

        /* モーダル共通スタイル */
        .modal { display: none; position: fixed; z-index: 3000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); }
        .modal-content { background: white; margin: 5vh auto; padding: 20px; border-radius: 8px; width: 90%; max-width: 500px; position: relative; box-sizing: border-box; display: flex; flex-direction: column; max-height: 85vh; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #eee; padding-bottom: 10px; }
        .modal-body { flex: 1; overflow-y: auto; padding: 10px 0; }
        .modal-footer { border-top: 1px solid #eee; padding-top: 10px; text-align: right; }
        .close-btn { font-size: 24px; cursor: pointer; }
        
        .list-item { display: flex; align-items: center; justify-content: space-between; padding: 8px 5px; border-bottom: 1px solid #eee; cursor: pointer; }
        
        /* 詳細表示モーダル専用 */
        .detail-modal-content { max-width: 750px; }
        .detail-layout { display: flex; gap: 20px; }
        .detail-img { 
            width: 220px;        /* 横幅を220pxに設定 */
            height: auto;        /* 縦幅は元画像の比率に応じて自動調整します */
            object-fit: contain; /* 画像を歪ませずに綺麗に収めます */
            border-radius: 8px; 
            flex-shrink: 0; 
            box-shadow: 0 4px 10px rgba(0,0,0,0.15); /* 画像の立体感を出すシャドウ効果 */
        }
        
        .detail-info { flex: 1; overflow-y: auto; max-height: 60vh; }
        .info-row { margin-bottom: 10px; line-height: 1.4; }
        .info-label { font-weight: bold; color: #555; font-size: 12px; }
        .info-value { font-size: 14px; background: #f5f5f5; padding: 6px 10px; border-radius: 4px; white-space: pre-wrap; }
    </style>
</head>
<body>

<div class="search-container">
    <h2>カード検索（ヘルプ用・仮画面）</h2>
    
    <!-- 検索テキストボックス -->
    <div class="input-group">
        <input type="text" id="help-search-input" class="input-text" placeholder="キーワードを入力してください..." oninput="debounceSearch()">
    </div>

    <!-- 検索対象チェックボックス -->
    <div class="checkbox-group">
        <label><input type="checkbox" class="scope-check" value="name" checked> カード名</label>
        <label><input type="checkbox" class="scope-check" value="reading" checked> カード名の読み</label>
        <label><input type="checkbox" class="scope-check" value="race" checked> 種族</label>
        <label><input type="checkbox" class="scope-check" value="text" checked> テキスト</label>
    </div>

<!-- 修正対象：文明設定エリア -->
    <div class="civ-filter-box">
        <strong>文明設定</strong>
        <!-- 単色・多色選択 -->
        <div style="margin-top: 5px; display: flex; gap: 15px;">
            <label><input type="checkbox" id="civ-single" checked onchange="handleCivTypeChange()"> 単色</label>
            <label><input type="checkbox" id="civ-multi" checked onchange="handleCivTypeChange()"> 多色</label>
        </div>

        <!-- ★ 新設：対象の文明を複数選択するチェックボックス -->
        <div style="margin-top: 10px; border-top: 1px dashed #ccc; padding-top: 10px;">
            <span style="font-size: 13px; color: #666; width: 100%;"><strong>含む文明を選択（複数選択可）:</strong></span>
            <div style="margin-top: 5px; margin-bottom: 5px; display: flex; gap: 15px; font-size: 13px;">
                <label style="cursor: pointer;"><input type="radio" name="civ-match-type" value="include" checked onchange="triggerHelpSearch()"> 選択した文明を含む</label>
                <label style="cursor: pointer;"><input type="radio" name="civ-match-type" value="match" onchange="triggerHelpSearch()"> 選択した文明のみ持つ</label>
            </div>
            
            <div style="display: flex; gap: 10px; flex-wrap: wrap; margin-top: 5px;">
                <label><input type="checkbox" class="civ-include-check" value="1" onchange="triggerHelpSearch()"> 光</label>
                <label><input type="checkbox" class="civ-include-check" value="2" onchange="triggerHelpSearch()"> 水</label>
                <label><input type="checkbox" class="civ-include-check" value="3" onchange="triggerHelpSearch()"> 闇</label>
                <label><input type="checkbox" class="civ-include-check" value="4" onchange="triggerHelpSearch()"> 火</label>
                <label><input type="checkbox" class="civ-include-check" value="5" onchange="triggerHelpSearch()"> 自然</label>
                <label><input type="checkbox" class="civ-include-check" value="6" onchange="triggerHelpSearch()"> 無色</label>
            </div>
        </div>

        <!-- 多色がONの時のみ表示される「含まれない文明」選択 -->
        <div id="exclude-civ-area" class="exclude-civs">
            <span style="font-size: 13px; color: #666; width: 100%;"><strong>含まれない文明を指定（多色のみ適用）:</strong></span>
            <label><input type="checkbox" class="exclude-civ-check" value="1" onchange="triggerHelpSearch()"> 光を除外</label>
            <label><input type="checkbox" class="exclude-civ-check" value="2" onchange="triggerHelpSearch()"> 水を除外</label>
            <label><input type="checkbox" class="exclude-civ-check" value="3" onchange="triggerHelpSearch()"> 闇を除外</label>
            <label><input type="checkbox" class="exclude-civ-check" value="4" onchange="triggerHelpSearch()"> 火を除外</label>
            <label><input type="checkbox" class="exclude-civ-check" value="5" onchange="triggerHelpSearch()"> 自然を除外</label>
            <label><input type="checkbox" class="exclude-civ-check" value="6" onchange="triggerHelpSearch()"> 無色を除外</label>
        </div>
    </div>

    <!-- その他の絞り込みドロップダウン（トリガーボタン） -->
    <div class="filter-grid">
        <button id="trigger-race" class="filter-btn" onclick="openHelpModal('race')">種族を選択</button>
        <button id="trigger-ability" class="filter-btn" onclick="openHelpModal('ability')">特殊能力を選択</button>
        <button id="trigger-characteristic" class="filter-btn" onclick="openHelpModal('characteristic')">特殊タイプを選択</button>
        <button id="trigger-cardtype" class="filter-btn" onclick="openHelpModal('cardtype')">カードタイプを選択</button>
        <button id="trigger-goods" class="filter-btn" onclick="openHelpModal('goods')">収録商品を選択</button>
    </div>

    <!-- 検索結果グリッド (横5列) -->
    <div id="help-results-grid" class="card-grid"></div>

    <!-- ★ 追加：ページングコントロール -->
    <div id="help-pagination" style="display: flex; justify-content: center; align-items: center; gap: 15px; margin-top: 25px; padding-bottom: 40px;">
        <!-- 関数名を changeHelpPage に変更 -->
        <button type="button" id="btn-prev-page" onclick="changeHelpPage(-1)" style="padding: 8px 16px; background: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; opacity: 0.5;">◀ 前へ</button>
        <span id="page-display" style="font-weight: bold; font-size: 14px; color: #333;">ページ 1</span>
        <button type="button" id="btn-next-page" onclick="changeHelpPage(1)" style="padding: 8px 16px; background: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: bold;">次へ ▶</button>
    </div>
</div>

<!-- 複数選択用モーダルテンプレート -->
<div id="helpFilterModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="help-modal-title">選択</h3>
            <span class="close-btn" onclick="closeHelpModal()">&times;</span>
        </div>
        <!-- 検索テキストボックス（特殊タイプ、カードタイプ以外で表示） -->
        <div id="help-modal-search-wrapper" style="padding: 10px 0 0 0;">
            <input type="text" id="help-modal-search-input" class="input-text" placeholder="選択肢を検索...">
        </div>
        <div class="modal-body" id="help-modal-body-list"></div>
        <div class="modal-footer">
            <button onclick="clearHelpSelection()" style="padding: 8px 15px; background: #ccc; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; margin-right: 5px;">クリア</button>
            <button onclick="applyHelpFilter()" style="padding: 8px 15px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: bold;">決定</button>
        </div>
    </div>
</div>

<!-- カード詳細モーダル -->
<div id="helpDetailModal" class="modal">
    <div class="modal-content detail-modal-content">
        <div class="modal-header">
            <h3>カード詳細情報</h3>
            <span class="close-btn" onclick="closeHelpDetailModal()">&times;</span>
        </div>
        <div class="modal-body">
            <div class="detail-layout">
                <img id="detail-card-image" class="detail-img" src="/images/card/noimage.webp">
                <div class="detail-info">
                    <div class="info-row"><div class="info-label">カードID</div><div id="info-id" class="info-value"></div></div>
                    <div class="info-row"><div class="info-label">カード名</div><div id="info-name" class="info-value"></div></div>
                    <div class="info-row"><div class="info-label">カードの読み</div><div id="info-reading" class="info-value"></div></div>
                    <div class="info-row"><div class="info-label">文明</div><div id="info-civs" class="info-value"></div></div>
                    <div class="info-row"><div class="info-label">種族</div><div id="info-races" class="info-value"></div></div>
                    <div class="info-row"><div class="info-label">特殊能力</div><div id="info-abilities" class="info-value"></div></div>
                    <div class="info-row"><div class="info-label">パワー</div><div id="info-power" class="info-value"></div></div>
                    <div class="info-row"><div class="info-label">コスト</div><div id="info-cost" class="info-value"></div></div>
                    <div class="info-row"><div class="info-label">テキスト</div><div id="info-text" class="info-value"></div></div>
                    <div class="info-row"><div class="info-label">フレーバーテキスト</div><div id="info-flavor" class="info-value"></div></div>
                    <div class="info-row"><div class="info-label">収録商品</div><div id="info-goods" class="info-value"></div></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// --- A. グローバル状態管理 ---
let helpCurrentPage = 1;
const helpPageLimit = 100;
let searchTimeout = null;
let currentModalType = null;

let activeFilters = {
    race: [],
    ability: [],
    characteristic: [],
    cardtype: [],
    goods: []
};

// マスターデータ（キャッシュ用）
let masterData = {
    race: [],
    ability: [],
    characteristic: [],
    cardtype: [],
    goods: []
};

// ページ読み込み完了時の初期化
window.addEventListener('DOMContentLoaded', () => {
    handleCivTypeChange(); // 文明の表示切り替えと初期検索
    loadAllMasterData();   // 各種マスターデータのロード
});

/**
 * 文明の「単色/多色」の変更監視
 */
function handleCivTypeChange() {
    const isMulti = document.getElementById('civ-multi').checked;
    const excludeArea = document.getElementById('exclude-civ-area');
    // 多色がONの時のみ、含まれない文明の除外オプションを表示
    excludeArea.style.display = isMulti ? 'flex' : 'none';
    triggerHelpSearch(true);
}

/**
 * 検索入力のデバウンス（タイピング時の過剰アクセス防止）
 */
function debounceSearch() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        triggerHelpSearch(true);
    }, 350);
}

/**
 * ページ変更処理（競合・バブリング防止版）
 */
function changeHelpPage(diff) {
    if (window.event) {
        window.event.stopPropagation();
    }
    
    console.log("[Help Paging] ボタンクリック。移動前のページ:", helpCurrentPage, "方向:", diff);
    
    helpCurrentPage += diff;
    if (helpCurrentPage < 1) helpCurrentPage = 1;
    
    console.log("[Help Paging] 移動後の目標ページ:", helpCurrentPage);
    triggerHelpSearch(false); // falseを指定してページ数をリセットせずに検索
}

/**
 * 検索処理の実装（完全・デバッグログ付き）
 */
function triggerHelpSearch(resetPage) {
    // 明示的に false が渡された時以外は、1ページ目に強制リセットします
    if (resetPage !== false) {
        console.log("[Help Search] 検索条件が変更されました。ページ数を 1 にリセットします。");
        helpCurrentPage = 1; 
    }

    const q = document.getElementById('help-search-input').value.trim();
    const scopes = Array.from(document.querySelectorAll('.scope-check:checked')).map(el => el.value);
    
    const singleChecked = document.getElementById('civ-single').checked;
    const multiChecked = document.getElementById('civ-multi').checked;
    
    const includeCivs = Array.from(document.querySelectorAll('.civ-include-check:checked')).map(el => el.value);
    const excludeCivs = Array.from(document.querySelectorAll('.exclude-civ-check:checked')).map(el => el.value);

    const civMatchType = document.querySelector('input[name="civ-match-type"]:checked').value;

    // offset位置（読み飛ばす件数）の計算
    const offset = (helpCurrentPage - 1) * helpPageLimit;

    console.log(`[Help API Request] 実行中... ページ: ${helpCurrentPage}, Limit: ${helpPageLimit}, Offset: ${offset}`);

    const params = new URLSearchParams();
    if (q) {
        params.append('q', q);
        params.append('scope', scopes.join(','));
    }
    
    // 単色・多色
    if (singleChecked && !multiChecked) params.append('civ_type', 'single');
    else if (!singleChecked && multiChecked) params.append('civ_type', 'multi');
    else if (!singleChecked && !multiChecked) params.append('civ_type', 'none');

    // 含む文明
    if (includeCivs.length > 0) {
        params.append('civs', includeCivs.join(','));
        params.append('civ_match_type', civMatchType);
    }

    // 除外する文明
    if (excludeCivs.length > 0) {
        params.append('exclude_civs', excludeCivs.join(','));
    }

    // 各種モーダルフィルター
    if (activeFilters.race.length) params.append('races', activeFilters.race.join(','));
    if (activeFilters.ability.length) params.append('abilities', activeFilters.ability.join(','));
    if (activeFilters.characteristic.length) params.append('characteristics', activeFilters.characteristic.join(','));
    if (activeFilters.cardtype.length) params.append('cardtypes', activeFilters.cardtype.join(','));
    if (activeFilters.goods.length) params.append('goods', activeFilters.goods.join(','));

    // ページングパラメータを追加
    params.append('limit', helpPageLimit);
    params.append('offset', offset);

    // APIへリクエスト送信
    fetch('/api/cards/help-search?' + params.toString())
        .then(res => res.json())
        .then(cards => {
            console.log(`[Help API Response] 正常受信。取得件数: ${cards.length} 件`);
            
            renderSearchResults(cards);
            
            // ページ表示を更新
            document.getElementById('page-display').innerText = `ページ ${helpCurrentPage}`;
            
            // 「前へ」ボタンの制御
            const prevBtn = document.getElementById('btn-prev-page');
            if (helpCurrentPage > 1) {
                prevBtn.disabled = false;
                prevBtn.style.opacity = "1";
            } else {
                prevBtn.disabled = true;
                prevBtn.style.opacity = "0.5";
            }

            // 「次へ」ボタンの制御
            const nextBtn = document.getElementById('btn-next-page');
            if (cards.length < helpPageLimit) {
                console.log(`[Help Paging Control] 取得数が上限(${helpPageLimit})未満のため、「次へ」を無効化`);
                nextBtn.disabled = true;
                nextBtn.style.opacity = "0.5";
            } else {
                nextBtn.disabled = false;
                nextBtn.style.opacity = "1";
            }
        })
        .catch(err => console.error("[Help API Response Error] データの取得に失敗しました:", err));
}

/**
 * 検索結果（カード画像）の描画
 */
function renderSearchResults(cards) {
    const grid = document.getElementById('help-results-grid');
    grid.innerHTML = '';
    
    if (cards.length === 0) {
        grid.innerHTML = '<div style="grid-column: 1/-1; text-align: center; color: #999; padding: 40px 0;">条件に合うカードが見つかりません。</div>';
        return;
    }

    cards.forEach(card => {
        const img = document.createElement('img');
        const path = card.imagepath.startsWith('/') ? card.imagepath : '/' + card.imagepath;
        img.src = '/images/card' + path;
        img.alt = card.card_name;
        img.onclick = () => openHelpDetail(card.card_id);
        img.onerror = () => { img.src = '/images/card/noimage.webp'; img.onerror = null; };
        grid.appendChild(img);
    });
}

/**
 * マスターデータの初期ロード（安全・一括取得版）
 */
function loadAllMasterData() {
    // 種族と特殊能力をロード
    fetch('/api/master-data')
        .then(res => res.json())
        .then(data => {
            masterData.race = data.races || [];
            masterData.ability = data.abilities || [];
        })
        .catch(err => console.error("種族・能力マスタ取得エラー:", err));

    // カードタイプ、特殊タイプ、収録商品を一括ロード
    fetch('/api/master-data-extended')
        .then(res => res.json())
        .then(data => {
            // カードタイプ：ID昇順でソートしてキャッシュ
            if (data.cardtypes) {
                masterData.cardtype = data.cardtypes.sort((a, b) => a.cardtype_id - b.cardtype_id);
            }
            // 特殊タイプ：ID昇順でソートしてキャッシュ
            if (data.characteristics) {
                masterData.characteristic = data.characteristics.sort((a, b) => a.characteristics_id - b.characteristics_id);
            }
            // 収録商品：ID降順でソートしてキャッシュ
            if (data.goods) {
                masterData.goods = data.goods.sort((a, b) => b.goods_id - a.goods_id);
            }
        })
        .catch(err => console.error("拡張マスタデータ取得エラー:", err));
}

/**
 * 絞り込みモーダルを開く
 */
function openHelpModal(type) {
    currentModalType = type;
    const modal = document.getElementById('helpFilterModal');
    const title = document.getElementById('help-modal-title');
    const searchWrapper = document.getElementById('help-modal-search-wrapper');
    const searchInput = document.getElementById('help-modal-search-input');
    
    searchInput.value = '';
    
    // タイトルと検索窓の表示制御
    if (type === 'race') { title.innerText = '種族選択'; searchWrapper.style.display = 'block'; }
    else if (type === 'ability') { title.innerText = '特殊能力選択'; searchWrapper.style.display = 'block'; }
    else if (type === 'characteristic') { title.innerText = '特殊タイプ選択'; searchWrapper.style.display = 'none'; }
    else if (type === 'cardtype') { title.innerText = 'カードタイプ選択'; searchWrapper.style.display = 'none'; }
    else if (type === 'goods') { title.innerText = '収録商品選択'; searchWrapper.style.display = 'block'; }

    renderModalList();

    // モーダル内の文字検索
    searchInput.oninput = () => {
        const q = searchInput.value.toLowerCase();
        document.querySelectorAll('#help-modal-body-list .list-item').forEach(el => {
            const name = el.dataset.name.toLowerCase();
            el.style.display = name.includes(q) ? 'flex' : 'none';
        });
    };

    modal.style.display = 'block';
}

/**
 * モーダル内リストの描画
 */
function renderModalList() {
    const container = document.getElementById('help-modal-body-list');
    container.innerHTML = '';
    
    const items = masterData[currentModalType] || [];
    items.forEach(item => {
        const id = item.race_id || item.ability_id || item.characteristics_id || item.cardtype_id || item.goods_id || item.id;
        const name = item.race_name || item.ability_name || item.characteristics_name || item.cardtype_name || item.goods_name || item.name;
        
        const isChecked = activeFilters[currentModalType].includes(id);

        const div = document.createElement('label');
        div.className = 'list-item';
        div.dataset.name = name;
        div.innerHTML = `
            <span>${name}</span>
            <input type="checkbox" class="help-modal-check" value="${id}" data-name="${name}" ${isChecked ? 'checked' : ''}>
        `;
        container.appendChild(div);
    });
}

function closeHelpModal() {
    document.getElementById('helpFilterModal').style.display = 'none';
}

function clearHelpSelection() {
    document.querySelectorAll('.help-modal-check').forEach(el => el.checked = false);
}

/**
 * フィルター適用
 */
function applyHelpFilter() {
    const checkedBoxes = Array.from(document.querySelectorAll('.help-modal-check:checked'));
    activeFilters[currentModalType] = checkedBoxes.map(el => parseInt(el.value, 10));
    
    const triggerBtn = document.getElementById(`trigger-${currentModalType}`);
    if (activeFilters[currentModalType].length > 0) {
        triggerBtn.innerText = checkedBoxes.map(el => el.dataset.name).join(', ');
        triggerBtn.style.background = '#007bff';
        triggerBtn.style.color = '#fff';
    } else {
        const labels = { race: '種族を選択', ability: '特殊能力を選択', characteristic: '特殊タイプを選択', cardtype: 'カードタイプを選択', goods: '収録商品を選択' };
        triggerBtn.innerText = labels[currentModalType];
        triggerBtn.style.background = '#eee';
        triggerBtn.style.color = '#000';
    }

    closeHelpModal();
    triggerHelpSearch(true); // フィルターが変わったら1ページ目に戻す
}

/**
 * カード詳細表示モーダルを開く
 */
function openHelpDetail(cardId) {
    fetch('/api/cards/help-detail?card_id=' + cardId)
        .then(res => res.json())
        .then(card => {
            const path = card.imagepath.startsWith('/') ? card.imagepath : '/' + card.imagepath;
            document.getElementById('detail-card-image').src = '/images/card' + path;
            document.getElementById('info-id').innerText = card.card_id || 'なし';            document.getElementById('info-name').innerText = card.card_name || 'なし';
            document.getElementById('info-reading').innerText = card.reading || 'なし';
            document.getElementById('info-civs').innerText = card.civilizations || 'なし';
            document.getElementById('info-races').innerText = card.races || 'なし';
            document.getElementById('info-abilities').innerText = card.abilities || 'なし';
            document.getElementById('info-power').innerText = (card.pow === 2147483647 ? '無限' : card.pow) || 'なし';
            document.getElementById('info-cost').innerText = (card.cost === 2147483647 ? '無限' : card.cost) || 'なし';
            document.getElementById('info-text').innerText = card.text ? card.text.replace(/\\n/g, '\n') : 'なし';
            document.getElementById('info-flavor').innerText = card.flavortext ? card.flavortext.replace(/\\n/g, '\n') : 'なし';
            document.getElementById('info-goods').innerText = card.goods_name || 'なし';

            document.getElementById('helpDetailModal').style.display = 'block';
        })
        .catch(err => console.error(err));
}

function closeHelpDetailModal() {
    document.getElementById('helpDetailModal').style.display = 'none';
}
</script>
</body>
</html>