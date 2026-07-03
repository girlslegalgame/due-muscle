<!-- app/Views/deck/deck_detail_modal.php -->
<!-- 共通デッキ詳細モーダルのスタイル -->
<style>
    /* --- モーダル設定 --- */
    #deckModal {
        display: none;
        position: fixed;
        z-index: 9999;
        left: 0; top: 0;
        width: 100%; height: 100%;
        background-color: rgba(0,0,0,0.8);
    }
    .modal-content {
        background-color: #fff;
        margin: 2vh auto;
        padding: 20px;
        width: 90%;
        max-width: 950px;
        height: 90vh;
        border-radius: 12px;
        position: relative;
        display: flex;
        flex-direction: column;
        box-sizing: border-box;
    }
    .close-btn {
        position: absolute;
        right: 20px; top: 15px;
        font-size: 30px;
        cursor: pointer;
        color: #aaa;
        z-index: 10;
    }

    /* --- タブメニュー --- */
    .tab-menu { 
        display: flex; 
        border-bottom: 2px solid #ddd; 
        margin-top: 10px;
        flex-shrink: 0;
    }
    .tab-item { 
        padding: 10px 20px; cursor: pointer; 
        border-bottom: 3px solid transparent; 
        font-weight: bold; color: #666;
    }
    .tab-item.active { border-bottom: 3px solid #007bff; color: #007bff; }
    
    .scroll-area {
        flex: 1;
        overflow-y: auto;
        padding: 15px 5px;
    }

    .tab-content { display: none; }
    .tab-content.active { display: block; }

    /* --- カード画像グリッド --- */
    .image-grid {
        display: grid !important;
        grid-template-columns: repeat(8, 1fr);
        grid-template-rows: repeat(5, auto);
        gap: 4px;
        width: 100%;
        max-width: 800px;
        margin: 0 auto;
        box-sizing: border-box;
    }
    .image-grid img {
        width: 100%;
        height: auto;
        aspect-ratio: 110/154;
        object-fit: fill;
        border: 1px solid #ddd;
        border-radius: 4px;
        cursor: pointer !important;
    }

    /* --- 分析エリア --- */
    .analysis-grid { 
        display: flex !important; 
        flex-direction: row !important;
        flex-wrap: nowrap !important;
        gap: 12px; 
        justify-content: center;
        width: 100%;
    }
    .chart-box { 
        flex: 1; 
        min-width: 0 !important;
        max-width: 450px;
        background: #fff; 
        padding: 10px; 
        border: 1px solid #ddd;
        border-radius: 8px;
        display: flex;
        flex-direction: column;
        align-items: center;
        box-sizing: border-box;
    }
    .canvas-container { 
        width: 100%; 
        height: 140px;
        position: relative; 
        margin-bottom: 10px; 
    }
    .analysis-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.7rem;
    }
    .analysis-table th, .analysis-table td { 
        border: 1px solid #ddd; 
        padding: 4px 1px; 
        text-align: center; 
    }
    .analysis-table th { background-color: #f8f9fa; }
    .row-label { 
        background-color: #f8f9fa; 
        font-weight: bold; 
        width: 35px;
    }

    /* 文明カラーバー */
    .civ-fire { border-bottom: 4px solid #ff3344 !important; }
    .civ-water { border-bottom: 4px solid #3399ff !important; }
    .civ-light { border-bottom: 4px solid #ffcc00 !important; }
    .civ-dark { border-bottom: 4px solid #666666 !important; }
    .civ-nature { border-bottom: 4px solid #22aa55 !important; }
    .civ-zero { border-bottom: 4px solid #999999 !important; }

    /* 超GR / 超次元 / 特殊 */
    .grid-gr, .grid-dim {
        display: grid !important;
        grid-template-columns: repeat(4, 1fr); 
        gap: 4px;
        width: 100%;
        max-width: 320px;
        justify-content: center;
    }
    .grid-special {
        display: grid !important;
        grid-template-columns: repeat(2, 1fr);
        gap: 4px;
        width: 100%;
        max-width: 160px;
        justify-content: center;
    }
    .extra-split-container {
        display: flex !important;
        flex-direction: row !important;
        flex-wrap: nowrap !important;
        min-height: auto;
        align-items: flex-start;
        width: 100%;
        gap: 5px;
    }
    .extra-side {
        flex: 1;
        display: flex;
        flex-direction: column;
        align-items: center;
        min-width: 0;
    }
    .vertical-divider {
        width: 1px;
        background-color: #ddd;
        align-self: stretch;
        margin: 0 10px;
        flex-shrink: 0;
    }
    .horizontal-divider {
        width: 100%;
        height: 1px;
        background-color: #ddd;
        margin: 10px 0;
        flex-shrink: 0;
    }
    .zone-title {
        font-size: 0.85rem;
        margin: 0 0 5px 0 !important;
        white-space: nowrap;
    }
    .grid-gr img, .grid-dim img, .grid-special img {
        width: 100% !important;
        height: auto !important;
        aspect-ratio: 110/154 !important;
        object-fit: fill;
        border: 1px solid #ddd;
        border-radius: 4px;
        cursor: pointer !important;
    }

    /* --- 初手ドロー / 初動チェック用のローカル追加スタイル（ID重複回避） --- */
    .modal-draw-zone {
        margin-bottom: 20px;
    }
    .modal-draw-zone-title {
        font-weight: bold;
        font-size: 14px;
        margin-bottom: 8px;
        color: #333;
        border-left: 4px solid #007bff;
        padding-left: 8px;
    }
    .modal-draw-card-list {
        display: flex;
        gap: 8px;
        overflow-x: auto;
        padding: 8px;
        background: #f8f9fa;
        border: 1px solid #e9ecef;
        border-radius: 8px;
        min-height: 125px;
        align-items: center;
        box-sizing: border-box;
    }
    .modal-draw-card-list::-webkit-scrollbar {
        height: 6px;
    }
    .modal-draw-card-list::-webkit-scrollbar-thumb {
        background: #ccc;
        border-radius: 3px;
    }
    .modal-draw-card-list img {
        height: 105px;
        width: auto;
        aspect-ratio: 110/154;
        object-fit: contain;
        border-radius: 4px;
        box-shadow: 1px 1px 4px rgba(0,0,0,0.15);
        flex-shrink: 0;
    }
    .modal-draw-actions {
        display: flex;
        gap: 15px;
        justify-content: center;
        margin-top: 25px;
        margin-bottom: 10px;
    }
    .modal-btn-draw-action {
        padding: 10px 24px;
        font-weight: bold;
        border-radius: 6px;
        border: none;
        cursor: pointer;
        font-size: 14px;
        transition: background 0.2s;
    }
    .modal-btn-draw-reload { background: #6c757d; color: white; }
    .modal-btn-draw-reload:hover { background: #5a6268; }
    .modal-btn-draw-add { background: #007bff; color: white; }
    .modal-btn-draw-add:hover { background: #0069d9; }

    .modal-fa-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        border-bottom: 2px solid #ddd;
        padding-bottom: 10px;
    }
    .modal-fa-title {
        font-size: 16px;
        font-weight: bold;
        color: #333;
    }
    .modal-btn-fa-reset {
        padding: 6px 15px;
        background: #dc3545;
        color: white;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-weight: bold;
        font-size: 13px;
        transition: background 0.2s;
    }
    .modal-btn-fa-reset:hover { background: #c82333; }
    
    .modal-fa-setup-area {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 40px 10px;
        background: #f8f9fa;
        border: 2px dashed #ccc;
        border-radius: 8px;
        margin-bottom: 20px;
    }
    .modal-fa-setup-text {
        font-size: 14px;
        color: #666;
        margin-bottom: 15px;
    }
    .modal-btn-fa-setup {
        padding: 10px 30px;
        background: #007bff;
        color: white;
        border: none;
        border-radius: 6px;
        font-weight: bold;
        cursor: pointer;
        font-size: 14px;
        transition: background 0.2s;
    }
    .modal-btn-fa-setup:hover { background: #0069d9; }

    .modal-fa-selected-cards {
        display: grid;
        grid-template-columns: repeat(6, 1fr);
        gap: 8px;
        margin-bottom: 20px;
    }
    .modal-fa-selected-cards img {
        width: 100%;
        height: auto;
        aspect-ratio: 110/154;
        object-fit: contain;
        border-radius: 4px;
        border: 1px solid #ddd;
        box-shadow: 1px 1px 4px rgba(0,0,0,0.1);
    }
    .modal-fa-result-box {
        background: #fff;
        border: 1px solid #ddd;
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 20px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }
    .modal-fa-result-item {
        display: flex;
        justify-content: space-between;
        font-size: 15px;
        margin-bottom: 10px;
        padding-bottom: 10px;
        border-bottom: 1px solid #eee;
    }
    .modal-fa-result-item:last-child {
        margin-bottom: 0;
        padding-bottom: 0;
        border-bottom: none;
    }
    .modal-fa-result-label {
        font-weight: bold;
        color: #555;
    }
    .modal-fa-result-val {
        font-weight: bold;
        color: #28a745;
        font-size: 17px;
    }
    .modal-fa-control-hand {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 20px;
        background: #f1f3f5;
        padding: 12px;
        border-radius: 8px;
    }
    .modal-btn-hand-qty {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        border: 1px solid #ccc;
        background: white;
        font-size: 18px;
        font-weight: bold;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: background 0.2s;
    }
    .modal-btn-hand-qty:hover { background: #e2e6ea; }

/* --- 追加：サブモーダル基本スタイル (全画面暗幕と中央配置用) --- */
    .sub-modal { 
        display: none; 
        position: fixed; 
        z-index: 10000; 
        left: 0; top: 0; 
        width: 100%; height: 100%; 
        background: rgba(0,0,0,0.8); 
        align-items: center;
        justify-content: center;
    }
    /* モーダル表示（display: block）時に自動で flex 中央揃えにする設定 */
    .sub-modal[style*="display: block"] {
        display: flex !important;
    }

    /* 設定用ポップアップのカードレイアウト */
    .setup-modal-grid {
        display: grid;
        grid-template-columns: repeat(6, 1fr);
        gap: 8px;
        max-height: 45vh;
        overflow-y: auto;
        padding: 10px 5px;
    }
    .setup-card-item {
        position: relative;
        cursor: pointer;
        border-radius: 4px;
        overflow: hidden;
        border: 3.5px solid transparent;
        transition: all 0.2s;
        box-sizing: border-box;
    }
    .setup-card-item img {
        width: 100%;
        height: auto;
        aspect-ratio: 110/154;
        object-fit: contain;
        display: block;
    }
    .setup-card-item.selected {
        border-color: #007bff;
        box-shadow: 0 0 8px rgba(0,123,255,0.5);
    }
    .setup-card-qty-badge {
        position: absolute;
        bottom: 2px;
        right: 2px;
        background: rgba(0,0,0,0.85);
        color: white;
        font-size: 11px;
        padding: 1px 5px;
        border-radius: 3px;
        font-weight: bold;
    }    
    @media (max-width: 768px) {
        .modal-draw-card-list img { height: 85px; }
        .modal-draw-card-list { min-height: 105px; }
        .modal-fa-selected-cards { grid-template-columns: repeat(4, 1fr); }
    }
</style>

<!-- 共通デッキ詳細モーダルのHTML -->
<div id="deckModal">
    <div class="modal-content">
        <span class="close-btn" onclick="closeModal()">&times;</span>
        <h2 id="modal-deck-title" style="margin:0; font-size: 1.2rem;">デッキ内容</h2>
        <div class="tab-menu">
            <div id="tab-main" class="tab-item active" onclick="switchTab('main')">メイン</div>
            <div id="tab-extra" class="tab-item" onclick="switchTab('extra')">GR / 超次元 / 特殊</div>
            <div id="tab-analysis" class="tab-item" onclick="switchTab('analysis')">分析</div>
        </div>

        <div class="scroll-area">
            <!-- メイン -->
            <div id="content-main" class="tab-content active"><div id="modal-main-list" class="image-grid"></div></div>

            <!-- GR / 超次元 / 特殊 統合エリア -->
            <div id="content-extra" class="tab-content">
                <div class="extra-split-container">
                    <div class="extra-side">
                        <h4 class="zone-title" style="margin:0 0 10px 0;">超GRゾーン</h4>
                        <div id="modal-gr-list" class="grid-gr"></div>
                    </div>
                    <div class="vertical-divider"></div>
                    <div class="extra-side" style="display: flex; flex-direction: column; width: 100%;">
                        <div style="width: 100%; display: flex; flex-direction: column; align-items: center;">
                            <h4 class="zone-title" style="margin:0 0 10px 0;">超次元ゾーン</h4>
                            <div id="modal-dim-list" class="grid-dim"></div>
                        </div>
                        <div class="horizontal-divider"></div>
                        <div style="width: 100%; display: flex; flex-direction: column; align-items: center;">
                            <h4 class="zone-title" style="margin:0 0 10px 0;">特殊カード</h4>
                            <div id="modal-special-list" class="grid-special"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 分析 -->
            <div id="content-analysis" class="tab-content">
                <!-- 左上の切り替えドロップダウン -->
                <div style="margin-bottom: 15px; border-bottom: 1px solid #ddd; padding-bottom: 12px; display: flex; justify-content: flex-start;">
                    <select id="modal-analysis-mode-select" onchange="switchModalAnalysisMode()" style="padding: 6px 12px; border-radius: 4px; border: 1px solid #ccc; font-size: 14px; font-weight: bold; cursor: pointer; outline: none;">
                        <option value="draw">初手ドロー</option>
                        <option value="first-action">初動チェック</option>
                        <option value="analysis" selected>デッキ分析</option>
                    </select>
                </div>

                <!-- 1. デッキ分析ビュー (従来の文明・マナカーブ) -->
                <div id="modal-analysis-view-deck" class="modal-analysis-mode-content">
                    <div class="analysis-grid">
                        <div class="chart-box">
                            <h4>文明バランス</h4>
                            <div class="canvas-container"><canvas id="colorPieChart"></canvas></div>
                            <table class="analysis-table">
                                <thead>
                                    <tr>
                                        <th></th>
                                        <th class="civ-fire">火</th><th class="civ-water">水</th><th class="civ-light">光</th>
                                        <th class="civ-dark">闇</th><th class="civ-nature">自然</th><th class="civ-zero">零</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr><td class="row-label">合計</td><td id="total-4">0</td><td id="total-2">0</td><td id="total-1">0</td><td id="total-3">0</td><td id="total-5">0</td><td id="total-6">0</td></tr>
                                    <tr><td class="row-label">単色</td><td id="single-4">0</td><td id="single-2">0</td><td id="single-1">0</td><td id="single-3">0</td><td id="single-5">0</td><td id="single-6">0</td></tr>
                                    <tr><td class="row-label">多色</td><td id="multi-4">0</td><td id="multi-2">0</td><td id="multi-1">0</td><td id="multi-3">0</td><td id="multi-5">0</td><td id="multi-6">0</td></tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="chart-box">
                            <h4>マナカーブ</h4>
                            <div class="canvas-container"><canvas id="manaBarChart"></canvas></div>
                            <table class="analysis-table">
                                <thead>
                                    <tr><th class="row-label">コスト</th><th>0</th><th>1</th><th>2</th><th>3</th><th>4</th><th>5</th><th>6</th><th>7</th><th>8</th><th>9+</th></tr>
                                </thead>
                                <tbody>
                                    <tr><td class="row-label">枚数</td><td id="mana-0">0</td><td id="mana-1">0</td><td id="mana-2">0</td><td id="mana-3">0</td><td id="mana-4">0</td><td id="mana-5">0</td><td id="mana-6">0</td><td id="mana-7">0</td><td id="mana-8">0</td><td id="mana-9">0</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- 2. 初手ドロービュー -->
                <div id="modal-analysis-view-draw" class="modal-analysis-mode-content" style="display: none;">
                    <div class="modal-draw-zone">
                        <div class="modal-draw-zone-title">シールド (5枚)</div>
                        <div id="modal-draw-shield-list" class="modal-draw-card-list"></div>
                    </div>
                    <div class="modal-draw-zone">
                        <div class="modal-draw-zone-title">初手 (5枚)</div>
                        <div id="modal-draw-hand-list" class="modal-draw-card-list"></div>
                    </div>
                    <div class="modal-draw-zone">
                        <div class="modal-draw-zone-title">ドロー <span id="modal-draw-count-label" style="font-weight: normal; font-size: 13px; color: #666;">(0枚)</span></div>
                        <div id="modal-draw-extra-list" class="modal-draw-card-list"></div>
                    </div>
                    <div class="modal-draw-actions">
                        <button class="modal-btn-draw-action modal-btn-draw-reload" onclick="initModalFirstDraw()">再読み込み</button>
                        <button class="modal-btn-draw-action modal-btn-draw-add" onclick="addModalOneDraw()">追加ドロー</button>
                    </div>
                </div>

                <!-- 3. 初動チェックビュー -->
                <div id="modal-analysis-view-first-action" class="modal-analysis-mode-content" style="display: none;">
                    <div class="modal-fa-header">
                        <span class="modal-fa-title">初動チェック結果</span>
                        <button class="modal-btn-fa-reset" onclick="resetModalFirstAction()">再設定</button>
                    </div>

                    <div id="modal-fa-setup-prompt" class="modal-fa-setup-area">
                        <div class="modal-fa-setup-text">初動カードを設定してください。</div>
                        <button class="modal-btn-fa-setup" onclick="openModalFaSetupModal()">設定</button>
                    </div>

                    <div id="modal-fa-selected-container" class="modal-fa-selected-cards" style="display: none;"></div>

                    <div class="modal-fa-result-box">
                        <div class="modal-fa-result-item">
                            <span class="modal-fa-result-label">引ける平均枚数</span>
                            <span class="modal-fa-result-val"><span id="modal-fa-val-average">0.00</span> 枚</span>
                        </div>
                        <div class="modal-fa-result-item">
                            <span class="modal-fa-result-label">1枚以上引ける確率</span>
                            <span class="modal-fa-result-val"><span id="modal-fa-val-percent">0.00</span> %</span>
                        </div>
                    </div>

                    <div class="modal-fa-control-hand">
                        <span style="font-weight: bold; font-size: 14px; color: #333;">手札の枚数 : </span>
                        <button class="modal-btn-hand-qty" onclick="adjustModalFaHand(-1)">-</button>
                        <span id="modal-fa-hand-val" style="font-size: 18px; font-weight: bold; min-width: 25px; text-align: center;">5</span>
                        <button class="modal-btn-hand-qty" onclick="adjustModalFaHand(1)">+</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 既存デッキ詳細モーダル用の初動カード設定サブモーダル -->
<div id="modalFirstActionSetupModal" class="sub-modal" style="z-index: 10001; background: rgba(0,0,0,0.5); display: none;">
    <div class="sub-modal-content" style="width: 90%; max-width: 650px; height: auto; max-height: 80vh; margin: 10vh auto; border-radius: 12px; display: flex; flex-direction: column; overflow: hidden; background: #fff;">
        <div class="sub-modal-header" style="padding: 12px 15px; background: #333; color: #fff; display: flex; justify-content: space-between; align-items: center; border-radius: 12px 12px 0 0;">
            <span style="font-weight: bold; font-size: 14px;">初動カードの設定</span>
            <span onclick="clearAllModalFaSelections()" style="cursor:pointer; font-size: 13px; font-weight: bold; color: #ff4d4d; text-decoration: underline; padding-right: 15px;">すべてクリア</span>
        </div>
        <div class="sub-modal-body" style="padding: 15px; flex: 1; overflow-y: auto;">
            <div id="modal-setup-modal-grid" class="setup-modal-grid"></div>
        </div>
        <div style="padding: 15px; border-top: 1px solid #eee; display: flex; gap: 10px; background: #fff;">
            <button onclick="closeModalFaSetupModal()" style="flex: 1; padding: 10px; background: #f2f2f7; color: #555; border: none; border-radius: 6px; cursor: pointer; font-weight: bold;">キャンセル</button>
            <button onclick="confirmModalFaSetup()" style="flex: 1; padding: 10px; background: #007bff; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: bold;">決定</button>
        </div>
    </div>
</div>

<!-- 共通デッキ詳細モーダルの制御スクリプト -->
<script>
let colorChart = null;
let manaChart = null;

// モーダル分析側ドロー＆初動変数
let currentDeckCards = []; // 現在のデッキの全カードデータ退避用
let modalDrawDeck = [];
let modalDrawIndex = 0;
let modalAdditionalDrawCount = 0;

let modalFaSelectedCardIds = new Set();
let modalFaHandCount = 5;

function openDeckModal(deckId, deckName) {
    document.getElementById('modal-deck-title').innerText = deckName;
    const mainList = document.getElementById('modal-main-list');
    mainList.innerHTML = '読み込み中...';
    document.getElementById('modal-dim-list').innerHTML = '';
    document.getElementById('modal-gr-list').innerHTML = '';
    document.getElementById('modal-special-list').innerHTML = '';

    document.getElementById('deckModal').style.display = 'block';
    document.body.style.overflow = 'hidden'; 
    switchTab('main'); 

    fetch('/api/decks/view?deck_id=' + deckId)
        .then(res => res.json())
        .then(data => {
            currentDeckCards = data; // データを保存
            renderImages(data);
            initCharts(data);
        })
        .catch(err => {
            mainList.innerHTML = 'データの読み込みに失敗しました。';
            console.error(err);
        });
}

function renderImages(cards) {
    const mainList = document.getElementById('modal-main-list');
    const dimList = document.getElementById('modal-dim-list');
    const grList = document.getElementById('modal-gr-list');
    const specialList = document.getElementById('modal-special-list'); 
    
    [mainList, dimList, grList, specialList].forEach(el => el.innerHTML = '');

    cards.forEach(card => {
        const isSpecial = card.card_type_in_deck === 'special' || (card.card_name && (card.card_name.includes('ドルマゲドン') || card.card_name.includes('零龍')));

        for(let i=0; i < card.quantity; i++) {
            const img = document.createElement('img');
            const path = card.imagepath.startsWith('/') ? card.imagepath : '/' + card.imagepath;
            img.src = '/images/card' + path;
            img.dataset.cardId = card.card_id;
            img.style.cursor = 'pointer';
            img.onclick = () => openCardDetail(card.card_id);
            img.onerror = function() { this.src = '/images/card/noimage.webp'; this.onerror = null; };

            if (isSpecial) {
                specialList.appendChild(img);
            } else if (card.card_type_in_deck === 'super_dimensional') {
                dimList.appendChild(img);
            } else if (card.card_type_in_deck === 'gr') {
                grList.appendChild(img);
            } else {
                mainList.appendChild(img);
            }
        }
    });

    if (!mainList.innerHTML) mainList.innerHTML = '<p>メインデッキが空です</p>';
    if (!dimList.innerHTML) dimList.innerHTML = '<p style="color:#666; font-size:14px;">超次元カードはありません</p>';
    if (!grList.innerHTML) grList.innerHTML = '<p style="color:#666; font-size:14px;">超GRカードはありません</p>';
    if (!specialList.innerHTML) specialList.innerHTML = '<p style="color:#666; font-size:14px;">特殊カードはありません</p>';
}

function initCharts(cards) {
    const mainCards = cards.filter(c => {
        const isSpecial = c.card_type_in_deck === 'special' || (c.card_name && (c.card_name.includes('ドルマゲドン') || c.card_name.includes('零龍')));
        if (isSpecial) return false;
        return c.card_type_in_deck === 'main' || c.card_type_in_deck === null || c.card_type_in_deck === '';
    });

    let singleTotalCount = 0, multiTotalCount = 0;
    let mana = Array(10).fill(0);
    const civData = {
        total: { 1:0, 2:0, 3:0, 4:0, 5:0, 6:0 },
        multi: { 1:0, 2:0, 3:0, 4:0, 5:0, 6:0 },
        single: { 1:0, 2:0, 3:0, 4:0, 5:0, 6:0 }
    };

    mainCards.forEach(c => {
        const qty = parseInt(c.quantity) || 0;
        const civs = c.civ_ids ? c.civ_ids.split(',').map(Number) : [];
        
        if (civs.length > 1) {
            multiTotalCount += qty;
            civs.forEach(id => {
                if(civData.total[id] !== undefined) {
                    civData.total[id] += qty;
                    civData.multi[id] += qty;
                }
            });
        } else if (civs.length === 1) {
            singleTotalCount += qty;
            const id = civs[0];
            if(civData.total[id] !== undefined) {
                civData.total[id] += qty;
                civData.single[id] += qty;
            }
        }

        let cost = parseInt(c.cost);
        if (isNaN(cost)) cost = 0;
        if (cost > 9) cost = 9;
        mana[cost] += qty;
    });

    [1, 2, 3, 4, 5, 6].forEach(id => {
        const tEl = document.getElementById(`total-${id}`);
        const mEl = document.getElementById(`multi-${id}`);
        const sEl = document.getElementById(`single-${id}`);
        if(tEl) tEl.innerText = civData.total[id];
        if(mEl) mEl.innerText = civData.multi[id];
        if(sEl) sEl.innerText = civData.single[id];
    });
    for(let i=0; i<=9; i++) {
        const mEl = document.getElementById(`mana-${i}`);
        if(mEl) mEl.innerText = mana[i];
    }

    if (colorChart) colorChart.destroy();
    if (manaChart) manaChart.destroy();

    const chartOptions = { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } };

    const ctxPie = document.getElementById('colorPieChart');
    if (ctxPie) {
        colorChart = new Chart(ctxPie, {
            type: 'pie',
            data: { labels: ['単色', '多色'], datasets: [{ data: [singleTotalCount, multiTotalCount], backgroundColor: ['#28a745', '#ffc107'] }] },
            options: chartOptions
        });
    }

    const ctxBar = document.getElementById('manaBarChart');
    if (ctxBar) {
        manaChart = new Chart(ctxBar, {
            type: 'bar',
            data: { labels: ['0','1','2','3','4','5','6','7','8','9+'], datasets: [{ label: '枚数', data: mana, backgroundColor: '#007bff' }] },
            options: { ...chartOptions, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
        });
    }
}

function switchTab(type) {
    const types = ['main', 'extra', 'analysis'];
    types.forEach(t => {
        const tabEl = document.getElementById('tab-' + t);
        const contEl = document.getElementById('content-' + t);
        if(tabEl) tabEl.classList.toggle('active', t === type);
        if(contEl) contEl.classList.toggle('active', t === type);
    });
    if (type === 'analysis') {
        // 分析タブに切り替わった時、分析モードの表示切替を呼ぶ
        switchModalAnalysisMode();
    }
}

function closeModal() {
    document.getElementById('deckModal').style.display = 'none';
    document.body.style.overflow = 'auto';
    // モードを初期（デッキ分析）に戻す
    const select = document.getElementById('modal-analysis-mode-select');
    if (select) select.value = 'analysis';
}

// 枠外クリックで閉じる制御
window.addEventListener('click', (e) => {
    if (e.target == document.getElementById('deckModal')) closeModal();
    if (e.target == document.getElementById('cardDetailModal')) {
        if (typeof closeDetailModal === 'function') closeDetailModal();
    }
});

/* ==========================================
   共通モーダル側 分析タブ内のモード切替
   ========================================== */
function switchModalAnalysisMode() {
    const mode = document.getElementById('modal-analysis-mode-select').value;
    document.querySelectorAll('.modal-analysis-mode-content').forEach(el => el.style.display = 'none');
    
    if (mode === 'analysis') {
        document.getElementById('modal-analysis-view-deck').style.display = 'block';
        if (colorChart) colorChart.resize();
        if (manaChart) manaChart.resize();
    } else if (mode === 'draw') {
        document.getElementById('modal-analysis-view-draw').style.display = 'block';
        initModalFirstDraw();
    } else if (mode === 'first-action') {
        document.getElementById('modal-analysis-view-first-action').style.display = 'block';
        resetModalFirstAction();
    }
}

/* ==========================================
   分析：初手ドローシミュレータ
   ========================================== */
function initModalFirstDraw() {
    // メインデッキ（特殊カード・超次元・GRを除く）を絞り込む
    const mainCards = currentDeckCards.filter(c => {
        const isSpecial = c.card_type_in_deck === 'special' || (c.card_name && (c.card_name.includes('ドルマゲドン') || c.card_name.includes('零龍')));
        if (isSpecial) return false;
        return c.card_type_in_deck === 'main' || c.card_type_in_deck === null || c.card_type_in_deck === '';
    });

    // 採用枚数分配列に展開する
    modalDrawDeck = [];
    mainCards.forEach(card => {
        const path = card.imagepath.startsWith('/') ? card.imagepath : '/' + card.imagepath;
        const src = '/images/card' + path;
        for (let i = 0; i < card.quantity; i++) {
            modalDrawDeck.push({
                src: src,
                cardId: card.card_id,
                cardName: card.card_name
            });
        }
    });

    const totalCount = modalDrawDeck.length;
    if (totalCount < 10) {
        alert("メインデッキにカードが10枚以上入っていないため、ドローを実行できません。");
        return;
    }

    // シャッフル
    for (let i = modalDrawDeck.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [modalDrawDeck[i], modalDrawDeck[j]] = [modalDrawDeck[j], modalDrawDeck[i]];
    }

    modalDrawIndex = 0;
    modalAdditionalDrawCount = 0;

    const shieldList = document.getElementById('modal-draw-shield-list');
    const handList = document.getElementById('modal-draw-hand-list');
    const extraList = document.getElementById('modal-draw-extra-list');

    shieldList.innerHTML = '';
    handList.innerHTML = '';
    extraList.innerHTML = '';
    document.getElementById('modal-draw-count-label').innerText = '(0枚)';

    // シールドに5枚
    for (let i = 0; i < 5; i++) {
        const card = modalDrawDeck[modalDrawIndex++];
        const img = document.createElement('img');
        img.src = card.src;
        img.dataset.cardId = card.cardId;
        img.alt = card.cardName;
        shieldList.appendChild(img);
    }

    // 初手に5枚
    for (let i = 0; i < 5; i++) {
        const card = modalDrawDeck[modalDrawIndex++];
        const img = document.createElement('img');
        img.src = card.src;
        img.dataset.cardId = card.cardId;
        img.alt = card.cardName;
        handList.appendChild(img);
    }
}

function addModalOneDraw() {
    if (modalDrawDeck.length === 0) {
        initModalFirstDraw();
        return;
    }

    if (modalDrawIndex >= modalDrawDeck.length) {
        alert("デッキのカードがなくなりました。これ以上ドローできません。");
        return;
    }

    const card = modalDrawDeck[modalDrawIndex++];
    modalAdditionalDrawCount++;

    const extraList = document.getElementById('modal-draw-extra-list');
    const img = document.createElement('img');
    img.src = card.src;
    img.dataset.cardId = card.cardId;
    img.alt = card.cardName;
    extraList.appendChild(img);

    document.getElementById('modal-draw-count-label').innerText = `(${modalAdditionalDrawCount}枚)`;
    extraList.scrollLeft = extraList.scrollWidth;
}

/* ==========================================
   分析：初動チェックシミュレータ
   ========================================== */
function resetModalFirstAction() {
    modalFaSelectedCardIds.clear();
    modalFaHandCount = 5;
    document.getElementById('modal-fa-hand-val').innerText = modalFaHandCount;
    
    document.getElementById('modal-fa-setup-prompt').style.display = 'flex';
    
    const container = document.getElementById('modal-fa-selected-container');
    container.style.display = 'none';
    container.innerHTML = '';
    
    calculateModalFirstAction();
    openModalFaSetupModal(); // 再設定時に直接モーダルを立ち上げる
}

function openModalFaSetupModal() {
    // 特殊ゾーンを除外したメインデッキのカードのみ抽出
    const mainCards = currentDeckCards.filter(c => {
        const isSpecial = c.card_type_in_deck === 'special' || (c.card_name && (c.card_name.includes('ドルマゲドン') || c.card_name.includes('零龍')));
        if (isSpecial) return false;
        return c.card_type_in_deck === 'main' || c.card_type_in_deck === null || c.card_type_in_deck === '';
    });

    if (mainCards.length === 0) {
        alert("メインデッキにカードが入っていません。");
        return;
    }

    const grid = document.getElementById('modal-setup-modal-grid');
    grid.innerHTML = '';

    // ★ 修正：同一カードの重複を排除しつつ、枚数を正しく合算（集約）する
    const cardMap = {};
    mainCards.forEach(card => {
        const id = card.card_id;
        const path = card.imagepath.startsWith('/') ? card.imagepath : '/' + card.imagepath;
        const src = '/images/card' + path;
        const qty = parseInt(card.quantity || card.qty || 1);

        if (!cardMap[id]) {
            cardMap[id] = {
                id: id,
                name: card.card_name,
                src: src,
                count: 0
            };
        }
        cardMap[id].count += qty; // 複数行に分かれて登録されている枚数を足し合わせる
    });

    // 集約したユニークなカードデータをもとにモーダル一覧を生成
    Object.values(cardMap).forEach(card => {
        const item = document.createElement('div');
        item.className = 'setup-card-item';
        item.dataset.cardId = card.id;

        if (modalFaSelectedCardIds.has(card.id)) {
            item.classList.add('selected');
        }

        item.innerHTML = `
            <img src="${card.src}" alt="${card.name}">
            <div class="setup-card-qty-badge">×${card.count}</div>
        `;

        item.onclick = () => {
            if (modalFaSelectedCardIds.has(card.id)) {
                modalFaSelectedCardIds.delete(card.id);
                item.classList.remove('selected');
            } else {
                modalFaSelectedCardIds.add(card.id);
                item.classList.add('selected');
            }
        };

        grid.appendChild(item);
    });

    document.getElementById('modalFirstActionSetupModal').style.display = 'block';
}

function closeModalFaSetupModal() {
    document.getElementById('modalFirstActionSetupModal').style.display = 'none';
}

function clearAllModalFaSelections() {
    document.querySelectorAll('#modalFirstActionSetupModal .setup-card-item').forEach(el => {
        el.classList.remove('selected');
    });
    modalFaSelectedCardIds.clear();
}

function confirmModalFaSetup() {
    closeModalFaSetupModal();
    
    const container = document.getElementById('modal-fa-selected-container');
    container.innerHTML = '';

    if (modalFaSelectedCardIds.size === 0) {
        document.getElementById('modal-fa-setup-prompt').style.display = 'flex';
        document.getElementById('modal-fa-selected-container').style.display = 'none';
    } else {
        document.getElementById('modal-fa-setup-prompt').style.display = 'none';
        container.style.display = 'grid';
        
        const mainCards = currentDeckCards.filter(c => {
            const isSpecial = c.card_type_in_deck === 'special' || (c.card_name && (c.card_name.includes('ドルマゲドン') || c.card_name.includes('零龍')));
            if (isSpecial) return false;
            return c.card_type_in_deck === 'main' || c.card_type_in_deck === null || c.card_type_in_deck === '';
        });

        mainCards.forEach(card => {
            if (modalFaSelectedCardIds.has(card.card_id)) {
                const path = card.imagepath.startsWith('/') ? card.imagepath : '/' + card.imagepath;
                const src = '/images/card' + path;
                const img = document.createElement('img');
                img.src = src;
                img.alt = card.card_name;
                container.appendChild(img);
            }
        });
    }

    calculateModalFirstAction();
}

function adjustModalFaHand(diff) {
    const mainCards = currentDeckCards.filter(c => {
        const isSpecial = c.card_type_in_deck === 'special' || (c.card_name && (c.card_name.includes('ドルマゲドン') || c.card_name.includes('零龍')));
        if (isSpecial) return false;
        return c.card_type_in_deck === 'main' || c.card_type_in_deck === null || c.card_type_in_deck === '';
    });
    
    let totalN = 0;
    mainCards.forEach(c => totalN += parseInt(c.quantity) || 0);
    const maxHand = Math.max(1, totalN);
    
    let newHand = modalFaHandCount + diff;
    if (newHand < 1) newHand = 1;
    if (newHand > maxHand) newHand = maxHand;
    
    modalFaHandCount = newHand;
    document.getElementById('modal-fa-hand-val').innerText = modalFaHandCount;
    calculateModalFirstAction();
}

function calculateModalFirstAction() {
    const mainCards = currentDeckCards.filter(c => {
        const isSpecial = c.card_type_in_deck === 'special' || (c.card_name && (c.card_name.includes('ドルマゲドン') || c.card_name.includes('零龍')));
        if (isSpecial) return false;
        return c.card_type_in_deck === 'main' || c.card_type_in_deck === null || c.card_type_in_deck === '';
    });

    let N = 0;
    mainCards.forEach(c => N += parseInt(c.quantity) || 0);

    let K = 0;
    if (modalFaSelectedCardIds.size > 0 && N > 0) {
        mainCards.forEach(c => {
            if (modalFaSelectedCardIds.has(c.card_id)) {
                K += parseInt(c.quantity) || 0;
            }
        });
    }

    const n = modalFaHandCount;

    let averageStr = '0.00';
    let percentStr = '0.00';

    if (N > 0 && K > 0 && n > 0) {
        const average = n * (K / N);
        averageStr = average.toFixed(2);

        const p0 = combination(N - K, n) / combination(N, n);
        const percent = (1 - p0) * 100;
        percentStr = Math.max(0, Math.min(100, percent)).toFixed(2);
    }

    document.getElementById('modal-fa-val-average').innerText = averageStr;
    document.getElementById('modal-fa-val-percent').innerText = percentStr;
}

// 組合せの数
function combination(n, r) {
    if (r < 0 || r > n) return 0;
    if (r === 0 || r === n) return 1;
    if (r > n / 2) r = n - r;
    let num = 1;
    let den = 1;
    for (let i = 1; i <= r; i++) {
        num *= (n - r + i);
        den *= i;
    }
    return num / den;
}
</script>