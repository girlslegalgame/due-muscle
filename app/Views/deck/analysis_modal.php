<!-- app/Views/deck/analysis_modal.php -->

<!-- 外部ライブラリ（Chart.js）読み込み -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
    /* --- 分析ボタンおよびモーダルの専用スタイル --- */
    .btn-analysis { 
        padding: 10px 20px; 
        background: #28a745; 
        color: white; 
        border: none; 
        border-radius: 4px; 
        cursor: pointer; 
        font-weight: bold; 
    }
    .btn-analysis:hover {
        background: #218838;
    }

    /* --- 初手ドロー専用のスタイル定義 --- */
    .draw-zone {
        margin-bottom: 20px;
    }
    .draw-zone-title {
        font-weight: bold;
        font-size: 14px;
        margin-bottom: 8px;
        color: #333;
        border-left: 4px solid #007bff;
        padding-left: 8px;
    }
    .draw-card-list {
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
    .draw-card-list::-webkit-scrollbar {
        height: 6px;
    }
    .draw-card-list::-webkit-scrollbar-thumb {
        background: #ccc;
        border-radius: 3px;
    }
    .draw-card-list img {
        height: 105px;
        width: auto;
        aspect-ratio: 110/154;
        object-fit: contain;
        border-radius: 4px;
        box-shadow: 1px 1px 4px rgba(0,0,0,0.15);
        flex-shrink: 0;
    }
    .draw-actions {
        display: flex;
        gap: 15px;
        justify-content: center;
        margin-top: 25px;
        margin-bottom: 10px;
    }
    .btn-draw-action {
        padding: 10px 24px;
        font-weight: bold;
        border-radius: 6px;
        border: none;
        cursor: pointer;
        font-size: 14px;
        transition: background 0.2s;
    }
    .btn-draw-reload { background: #6c757d; color: white; }
    .btn-draw-reload:hover { background: #5a6268; }
    .btn-draw-add { background: #007bff; color: white; }
    .btn-draw-add:hover { background: #0069d9; }

    /* --- 初動チェック専用のスタイル定義 --- */
    .fa-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        border-bottom: 2px solid #ddd;
        padding-bottom: 10px;
    }
    .fa-title {
        font-size: 16px;
        font-weight: bold;
        color: #333;
    }
    .btn-fa-reset {
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
    .btn-fa-reset:hover {
        background: #c82333;
    }
    .fa-setup-area {
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
    .fa-setup-text {
        font-size: 14px;
        color: #666;
        margin-bottom: 15px;
    }
    .btn-fa-setup {
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
    .btn-fa-setup:hover {
        background: #0069d9;
    }
    .fa-selected-cards {
        display: grid;
        grid-template-columns: repeat(6, 1fr);
        gap: 8px;
        margin-bottom: 20px;
    }
    .fa-selected-cards img {
        width: 100%;
        height: auto;
        aspect-ratio: 110/154;
        object-fit: contain;
        border-radius: 4px;
        border: 1px solid #ddd;
        box-shadow: 1px 1px 4px rgba(0,0,0,0.1);
    }
    .fa-result-box {
        background: #fff;
        border: 1px solid #ddd;
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 20px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }
    .fa-result-item {
        display: flex;
        justify-content: space-between;
        font-size: 15px;
        margin-bottom: 10px;
        padding-bottom: 10px;
        border-bottom: 1px solid #eee;
    }
    .fa-result-item:last-child {
        margin-bottom: 0;
        padding-bottom: 0;
        border-bottom: none;
    }
    .fa-result-label {
        font-weight: bold;
        color: #555;
    }
    .fa-result-val {
        font-weight: bold;
        color: #28a745;
        font-size: 17px;
    }
    .fa-control-hand {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 20px;
        background: #f1f3f5;
        padding: 12px;
        border-radius: 8px;
    }
    .btn-hand-qty {
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
    .btn-hand-qty:hover {
        background: #e2e6ea;
    }

    /* 設定用ポップアップのグリッド */
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
        border: 3.5px solid transparent; /* 選択時の青線を強調 */
        transition: all 0.2s;
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
        .btn-analysis {
            padding: 6px 10px;
            font-size: 12px;
        }
        .draw-card-list img {
            height: 85px;
        }
        .draw-card-list {
            min-height: 105px;
        }
        .fa-selected-cards {
            grid-template-columns: repeat(4, 1fr); /* スマホは4列に */
        }
        .setup-modal-grid {
            grid-template-columns: repeat(4, 1fr); /* スマホは4列に */
        }
    }
</style>

<!-- 分析モーダル本体 -->
<div id="analysisModal" class="sub-modal">
    <div class="sub-modal-content" style="width: 90%; max-width: 950px; height: 90vh; display: flex; flex-direction: column; margin: 2vh auto; border-radius: 12px; background: #fff;">        <div class="sub-modal-header" style="padding: 15px; background: #333; color: #fff; display: flex; justify-content: space-between; align-items: center; border-radius: 12px 12px 0 0;">
            <!-- 左上のドロップダウンリスト -->
            <div>
                <select id="analysis-mode-select" onchange="switchAnalysisMode()" style="padding: 6px 12px; border-radius: 4px; border: 1px solid #ccc; font-size: 14px; font-weight: bold; cursor: pointer; outline: none;">
                    <option value="draw">初手ドロー</option>
                    <option value="first-action">初動チェック</option>
                    <option value="analysis" selected>デッキ分析</option>
                </select>
            </div>
            <span onclick="closeAnalysisModal()" style="cursor:pointer; font-size: 24px;">&times;</span>
        </div>
        
        <div class="sub-modal-body" style="flex: 1; overflow-y: auto; padding: 20px;">
            <!-- 1. デッキ分析コンテンツ -->
            <div id="analysis-view-deck" class="analysis-mode-content" style="display: none;">
                <div class="analysis-grid" style="display: flex; gap: 20px; justify-content: center; flex-wrap: wrap;">
                    
                    <!-- 文明バランス -->
                    <div class="chart-box" style="flex: 1; min-width: 300px; max-width: 440px; border: 1px solid #ddd; padding: 15px; border-radius: 8px; box-sizing: border-box; background: #fff;">
                        <h4 style="margin-top:0; text-align:center;">文明バランス</h4>
                        <div class="canvas-container" style="position: relative; height: 180px; width: 100%;"><canvas id="createColorPieChart"></canvas></div>
                        <table class="analysis-table" style="width: 100%; border-collapse: collapse; font-size: 0.75rem; margin-top: 10px;">
                            <thead>
                                <tr>
                                    <th style="border:1px solid #ddd; padding:4px;"></th>
                                    <th class="civ-fire" style="border:1px solid #ddd; padding:4px; border-bottom: 4px solid #ff3344 !important;">火</th>
                                    <th class="civ-water" style="border:1px solid #ddd; padding:4px; border-bottom: 4px solid #3399ff !important;">水</th>
                                    <th class="civ-light" style="border:1px solid #ddd; padding:4px; border-bottom: 4px solid #ffcc00 !important;">光</th>
                                    <th class="civ-dark" style="border:1px solid #ddd; padding:4px; border-bottom: 4px solid #666666 !important;">闇</th>
                                    <th class="civ-nature" style="border:1px solid #ddd; padding:4px; border-bottom: 4px solid #22aa55 !important;">自然</th>
                                    <th class="civ-zero" style="border:1px solid #ddd; padding:4px; border-bottom: 4px solid #999999 !important;">零</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr><td class="row-label" style="border:1px solid #ddd; padding:4px; background:#f8f9fa; font-weight:bold; text-align:center;">合計</td><td id="create-total-4" style="border:1px solid #ddd; padding:4px; text-align:center;">0</td><td id="create-total-2" style="border:1px solid #ddd; padding:4px; text-align:center;">0</td><td id="create-total-1" style="border:1px solid #ddd; padding:4px; text-align:center;">0</td><td id="create-total-3" style="border:1px solid #ddd; padding:4px; text-align:center;">0</td><td id="create-total-5" style="border:1px solid #ddd; padding:4px; text-align:center;">0</td><td id="create-total-6" style="border:1px solid #ddd; padding:4px; text-align:center;">0</td></tr>
                                <tr><td class="row-label" style="border:1px solid #ddd; padding:4px; background:#f8f9fa; font-weight:bold; text-align:center;">単色</td><td id="create-single-4" style="border:1px solid #ddd; padding:4px; text-align:center;">0</td><td id="create-single-2" style="border:1px solid #ddd; padding:4px; text-align:center;">0</td><td id="create-single-1" style="border:1px solid #ddd; padding:4px; text-align:center;">0</td><td id="create-single-3" style="border:1px solid #ddd; padding:4px; text-align:center;">0</td><td id="create-single-5" style="border:1px solid #ddd; padding:4px; text-align:center;">0</td><td id="create-single-6" style="border:1px solid #ddd; padding:4px; text-align:center;">0</td></tr>
                                <tr><td class="row-label" style="border:1px solid #ddd; padding:4px; background:#f8f9fa; font-weight:bold; text-align:center;">多色</td><td id="create-multi-4" style="border:1px solid #ddd; padding:4px; text-align:center;">0</td><td id="create-multi-2" style="border:1px solid #ddd; padding:4px; text-align:center;">0</td><td id="create-multi-1" style="border:1px solid #ddd; padding:4px; text-align:center;">0</td><td id="create-multi-3" style="border:1px solid #ddd; padding:4px; text-align:center;">0</td><td id="create-multi-5" style="border:1px solid #ddd; padding:4px; text-align:center;">0</td><td id="create-multi-6" style="border:1px solid #ddd; padding:4px; text-align:center;">0</td></tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- マナカーブ -->
                    <div class="chart-box" style="flex: 1; min-width: 300px; max-width: 440px; border: 1px solid #ddd; padding: 15px; border-radius: 8px; box-sizing: border-box; background: #fff;">
                        <h4 style="margin-top:0; text-align:center;">マナカーブ</h4>
                        <div class="canvas-container" style="position: relative; height: 180px; width: 100%;"><canvas id="createManaBarChart"></canvas></div>
                        <table class="analysis-table" style="width: 100%; border-collapse: collapse; font-size: 0.75rem; margin-top: 10px;">
                            <thead>
                                <tr><th class="row-label" style="border:1px solid #ddd; padding:4px; background:#f8f9fa; font-weight:bold; text-align:center;">コスト</th><th style="border:1px solid #ddd; padding:4px; text-align:center;">0</th><th style="border:1px solid #ddd; padding:4px; text-align:center;">1</th><th style="border:1px solid #ddd; padding:4px; text-align:center;">2</th><th style="border:1px solid #ddd; padding:4px; text-align:center;">3</th><th style="border:1px solid #ddd; padding:4px; text-align:center;">4</th><th style="border:1px solid #ddd; padding:4px; text-align:center;">5</th><th style="border:1px solid #ddd; padding:4px; text-align:center;">6</th><th style="border:1px solid #ddd; padding:4px; text-align:center;">7</th><th style="border:1px solid #ddd; padding:4px; text-align:center;">8</th><th style="border:1px solid #ddd; padding:4px; text-align:center;">9+</th></tr>
                            </thead>
                            <tbody>
                                <tr><td class="row-label" style="border:1px solid #ddd; padding:4px; background:#f8f9fa; font-weight:bold; text-align:center;">枚数</td><td id="create-mana-0" style="border:1px solid #ddd; padding:4px; text-align:center;">0</td><td id="create-mana-1" style="border:1px solid #ddd; padding:4px; text-align:center;">0</td><td id="create-mana-2" style="border:1px solid #ddd; padding:4px; text-align:center;">0</td><td id="create-mana-3" style="border:1px solid #ddd; padding:4px; text-align:center;">0</td><td id="create-mana-4" style="border:1px solid #ddd; padding:4px; text-align:center;">0</td><td id="create-mana-5" style="border:1px solid #ddd; padding:4px; text-align:center;">0</td><td id="create-mana-6" style="border:1px solid #ddd; padding:4px; text-align:center;">0</td><td id="create-mana-7" style="border:1px solid #ddd; padding:4px; text-align:center;">0</td><td id="create-mana-8" style="border:1px solid #ddd; padding:4px; text-align:center;">0</td><td id="create-mana-9" style="border:1px solid #ddd; padding:4px; text-align:center;">0</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- 2. 初手ドローコンテンツ -->
            <div id="analysis-view-draw" class="analysis-mode-content" style="display: none;">
                <div class="draw-zone">
                    <div class="draw-zone-title">シールド (5枚)</div>
                    <div id="draw-shield-list" class="draw-card-list"></div>
                </div>
                <div class="draw-zone">
                    <div class="draw-zone-title">初手 (5枚)</div>
                    <div id="draw-hand-list" class="draw-card-list"></div>
                </div>
                <div class="draw-zone">
                    <div class="draw-zone-title">ドロー <span id="draw-count-label" style="font-weight: normal; font-size: 13px; color: #666;">(0枚)</span></div>
                    <div id="draw-extra-list" class="draw-card-list"></div>
                </div>
                <div class="draw-actions">
                    <button class="btn-draw-action btn-draw-reload" onclick="initFirstDraw()">再読み込み</button>
                    <button class="btn-draw-action btn-draw-add" onclick="addOneDraw()">追加ドロー</button>
                </div>
            </div>

            <!-- 3. 初動チェックコンテンツ (新規実装) -->
            <div id="analysis-view-first-action" class="analysis-mode-content" style="display: none;">
                <!-- ヘッダーエリア -->
                <div class="fa-header">
                    <span class="fa-title">初動チェック結果</span>
                    <button class="btn-fa-reset" onclick="resetFirstAction()">再設定</button>
                </div>

                <!-- 初動カードが設定されていない場合の表示 -->
                <div id="fa-setup-prompt" class="fa-setup-area">
                    <div class="fa-setup-text">初動カードを設定してください。</div>
                    <button class="btn-fa-setup" onclick="openFaSetupModal()">設定</button>
                </div>

                <!-- 選択されたカード表示領域 (設定完了後に grid 表示に切り替え) -->
                <div id="fa-selected-container" class="fa-selected-cards" style="display: none;"></div>

                <!-- 確率集計ボックス -->
                <div class="fa-result-box">
                    <div class="fa-result-item">
                        <span class="fa-result-label">引ける平均枚数</span>
                        <span class="fa-result-val"><span id="fa-val-average">0.00</span> 枚</span>
                    </div>
                    <div class="fa-result-item">
                        <span class="fa-result-label">1枚以上引ける確率</span>
                        <span class="fa-result-val"><span id="fa-val-percent">0.00</span> %</span>
                    </div>
                </div>

                <!-- 手札調整スライダー/ボタン -->
                <div class="fa-control-hand">
                    <span style="font-weight: bold; font-size: 14px; color: #333;">手札の枚数 : </span>
                    <button class="btn-hand-qty" onclick="adjustFaHand(-1)">-</button>
                    <span id="fa-hand-val" style="font-size: 18px; font-weight: bold; min-width: 25px; text-align: center;">5</span>
                    <button class="btn-hand-qty" onclick="adjustFaHand(1)">+</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 初動カード設定用サブモーダル -->
<div id="firstActionSetupModal" class="sub-modal" style="z-index: 10001; background: rgba(0,0,0,0.5); display: none;">
    <div class="sub-modal-content" style="width: 90%; max-width: 650px; height: auto; max-height: 80vh; margin: 10vh auto; border-radius: 12px; display: flex; flex-direction: column; overflow: hidden; background: #fff;">
        <div class="sub-modal-header" style="padding: 12px 15px; background: #333; color: #fff; display: flex; justify-content: space-between; align-items: center; border-radius: 12px 12px 0 0;">
            <span style="font-weight: bold; font-size: 14px;">初動カードの設定</span>
            <span onclick="clearAllFaSelections()" style="cursor:pointer; font-size: 13px; font-weight: bold; color: #ff4d4d; text-decoration: underline; padding-right: 15px;">すべてクリア</span>
        </div>
        <div class="sub-modal-body" style="padding: 15px; flex: 1; overflow-y: auto;">
            <div id="setup-modal-grid" class="setup-modal-grid"></div>
        </div>
        <div style="padding: 15px; border-top: 1px solid #eee; display: flex; gap: 10px; background: #fff;">
            <button onclick="closeFaSetupModal()" style="flex: 1; padding: 10px; background: #f2f2f7; color: #555; border: none; border-radius: 6px; cursor: pointer; font-weight: bold;">キャンセル</button>
            <button onclick="confirmFaSetup()" style="flex: 1; padding: 10px; background: #007bff; color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: bold;">決定</button>
        </div>
    </div>
</div>

<script>
let createColorChart = null;
let createManaChart = null;

// ドローシミュレーター用変数
let drawDeck = [];
let drawIndex = 0;
let additionalDrawCount = 0;

// 初動チェック用変数
let faSelectedCardIds = new Set(); // 選択されたカードID
let faHandCount = 5;               // 現在の手札枚数（デフォルト5）

function openAnalysisModal() {
    document.getElementById('analysisModal').style.display = 'block';
    const modeSelect = document.getElementById('analysis-mode-select');
    modeSelect.value = 'analysis';
    switchAnalysisMode();
}

function closeAnalysisModal() {
    document.getElementById('analysisModal').style.display = 'none';
}

function switchAnalysisMode() {
    const mode = document.getElementById('analysis-mode-select').value;
    document.querySelectorAll('.analysis-mode-content').forEach(el => el.style.display = 'none');
    
    if (mode === 'analysis') {
        document.getElementById('analysis-view-deck').style.display = 'block';
        updateAnalysisCharts();
    } else if (mode === 'draw') {
        document.getElementById('analysis-view-draw').style.display = 'block';
        initFirstDraw();
    } else if (mode === 'first-action') {
        document.getElementById('analysis-view-first-action').style.display = 'block';
        resetFirstAction(); // 初動チェック選択時にリセットをかける
    }
}

/**
 * 初手・シールドを配る（初期化処理）
 */
function initFirstDraw() {
    const mainImgs = Array.from(mainList.querySelectorAll('img'));
    const totalCount = mainImgs.length;

    if (totalCount < 10) {
        alert("メインデッキにカードが10枚以上入っていないため、ドローを実行できません。");
        return;
    }

    drawDeck = mainImgs.map(img => ({
        src: img.src,
        cardId: img.dataset.cardId,
        cardName: img.dataset.cardName
    }));

    for (let i = drawDeck.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [drawDeck[i], drawDeck[j]] = [drawDeck[j], drawDeck[i]];
    }

    drawIndex = 0;
    additionalDrawCount = 0;

    const shieldList = document.getElementById('draw-shield-list');
    const handList = document.getElementById('draw-hand-list');
    const extraList = document.getElementById('draw-extra-list');

    shieldList.innerHTML = '';
    handList.innerHTML = '';
    extraList.innerHTML = '';
    document.getElementById('draw-count-label').innerText = '(0枚)';

    for (let i = 0; i < 5; i++) {
        const card = drawDeck[drawIndex++];
        const img = document.createElement('img');
        img.src = card.src;
        img.dataset.cardId = card.cardId;
        img.alt = card.cardName;
        shieldList.appendChild(img);
    }

    for (let i = 0; i < 5; i++) {
        const card = drawDeck[drawIndex++];
        const img = document.createElement('img');
        img.src = card.src;
        img.dataset.cardId = card.cardId;
        img.alt = card.cardName;
        handList.appendChild(img);
    }
}

/**
 * 残りデッキから1枚追加でドローする
 */
function addOneDraw() {
    if (drawDeck.length === 0) {
        initFirstDraw();
        return;
    }

    if (drawIndex >= drawDeck.length) {
        alert("デッキのカードがなくなりました。これ以上ドローできません。");
        return;
    }

    const card = drawDeck[drawIndex++];
    additionalDrawCount++;

    const extraList = document.getElementById('draw-extra-list');
    const img = document.createElement('img');
    img.src = card.src;
    img.dataset.cardId = card.cardId;
    img.alt = card.cardName;
    extraList.appendChild(img);

    document.getElementById('draw-count-label').innerText = `(${additionalDrawCount}枚)`;
    extraList.scrollLeft = extraList.scrollWidth;
}

/* ==========================================
   初動チェック機能のコントロール
   ========================================== */

/**
 * 初動チェックの設定および表示リセット
 */
function resetFirstAction() {
    faSelectedCardIds.clear();
    faHandCount = 5;
    document.getElementById('fa-hand-val').innerText = faHandCount;
    
    document.getElementById('fa-setup-prompt').style.display = 'flex';
    
    const container = document.getElementById('fa-selected-container');
    container.style.display = 'none';
    container.innerHTML = '';
    
    calculateFirstAction();
    
    // ★ 追記：リセット後に直接カードの選択サブモーダルを開きます
    openFaSetupModal();
}

/**
 * 設定サブモーダルを開き、デッキ内カードを表示
 */
function openFaSetupModal() {
    const mainImgs = Array.from(mainList.querySelectorAll('img'));
    if (mainImgs.length === 0) {
        alert("メインデッキにカードが入っていません。");
        return;
    }

    const grid = document.getElementById('setup-modal-grid');
    grid.innerHTML = '';

    // 重複を弾いてカード枚数をマップ化
    const cardMap = {};
    mainImgs.forEach(img => {
        const id = img.dataset.cardId;
        const name = img.dataset.cardName;
        const src = img.src;
        if (!cardMap[id]) {
            cardMap[id] = { id, name, src, count: 0 };
        }
        cardMap[id].count++;
    });

    // モーダルグリッドにカードを描画
    Object.values(cardMap).forEach(card => {
        const item = document.createElement('div');
        item.className = 'setup-card-item';
        item.dataset.cardId = card.id;

        // すでに選択済みの場合は枠線を適用
        if (faSelectedCardIds.has(card.id)) {
            item.classList.add('selected');
        }

        item.innerHTML = `
            <img src="${card.src}" alt="${card.name}">
            <div class="setup-card-qty-badge">×${card.count}</div>
        `;

        // 選択のトグル処理
        item.onclick = () => {
            if (faSelectedCardIds.has(card.id)) {
                faSelectedCardIds.delete(card.id);
                item.classList.remove('selected');
            } else {
                faSelectedCardIds.add(card.id);
                item.classList.add('selected');
            }
        };

        grid.appendChild(item);
    });

    document.getElementById('firstActionSetupModal').style.display = 'block';
}

function closeFaSetupModal() {
    document.getElementById('firstActionSetupModal').style.display = 'none';
}

/**
 * 設定サブモーダル内での「すべてクリア」
 */
function clearAllFaSelections() {
    document.querySelectorAll('.setup-card-item').forEach(el => {
        el.classList.remove('selected');
    });
    faSelectedCardIds.clear();
}

/**
 * 決定ボタン：選択されたカードを確定してモーダルを表示
 */
function confirmFaSetup() {
    closeFaSetupModal();
    
    const container = document.getElementById('fa-selected-container');
    container.innerHTML = '';

    if (faSelectedCardIds.size === 0) {
        document.getElementById('fa-setup-prompt').style.display = 'flex';
        document.getElementById('fa-selected-container').style.display = 'none';
    } else {
        document.getElementById('fa-setup-prompt').style.display = 'none';
        container.style.display = 'grid';
        
        // 選択されたカードを横6列で並べる (重複なし画像で表示)
        const mainImgs = Array.from(mainList.querySelectorAll('img'));
        const seen = new Set();
        mainImgs.forEach(img => {
            const id = img.dataset.cardId;
            if (faSelectedCardIds.has(id) && !seen.has(id)) {
                seen.add(id);
                const clone = img.cloneNode();
                container.appendChild(clone);
            }
        });
    }

    calculateFirstAction();
}

/**
 * 手札枚数の変更
 */
function adjustFaHand(diff) {
    const mainImgs = Array.from(mainList.querySelectorAll('img'));
    const maxHand = Math.max(1, mainImgs.length);
    
    let newHand = faHandCount + diff;
    if (newHand < 1) newHand = 1;
    if (newHand > maxHand) newHand = maxHand;
    
    faHandCount = newHand;
    document.getElementById('fa-hand-val').innerText = faHandCount;
    calculateFirstAction();
}

/**
 * 超幾何分布に基づく確率計算
 */
function calculateFirstAction() {
    const mainImgs = Array.from(mainList.querySelectorAll('img'));
    const N = mainImgs.length; // デッキ枚数

    // 選択された初動カードの採用合計枚数をカウントする
    let K = 0; 
    if (faSelectedCardIds.size > 0 && N > 0) {
        mainImgs.forEach(img => {
            if (faSelectedCardIds.has(img.dataset.cardId)) {
                K++;
            }
        });
    }

    const n = faHandCount; // 手札枚数

    let averageStr = '0.00';
    let percentStr = '0.00';

    if (N > 0 && K > 0 && n > 0) {
        // 1. 引ける期待値（平均枚数）の計算
        const average = n * (K / N);
        averageStr = average.toFixed(2);

        // 2. 1枚以上引ける確率（1 - P(0)）の計算
        const p0 = combination(N - K, n) / combination(N, n);
        const percent = (1 - p0) * 100;
        percentStr = Math.max(0, Math.min(100, percent)).toFixed(2);
    }

    document.getElementById('fa-val-average').innerText = averageStr;
    document.getElementById('fa-val-percent').innerText = percentStr;
}

/**
 * 組合せ(Combination)の数を求める数学的関数
 */
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

/* ==========================================
   分析チャート
   ========================================== */
function updateAnalysisCharts() {
    const mainImgs = Array.from(mainList.querySelectorAll('img'));
    
    let singleTotalCount = 0;
    let multiTotalCount = 0;
    let mana = Array(10).fill(0);
    
    const civData = {
        total: { 1: 0, 2: 0, 3: 0, 4: 0, 5: 0, 6: 0 },
        multi: { 1: 0, 2: 0, 3: 0, 4: 0, 5: 0, 6: 0 },
        single: { 1: 0, 2: 0, 3: 0, 4: 0, 5: 0, 6: 0 }
    };

    mainImgs.forEach(img => {
        const civStr = img.dataset.civ || '';
        const civs = civStr ? civStr.split(',').map(Number).filter(n => !isNaN(n)) : [];

        if (civs.length > 1) {
            multiTotalCount += 1;
            civs.forEach(id => {
                if (civData.total[id] !== undefined) {
                    civData.total[id] += 1;
                    civData.multi[id] += 1;
                }
            });
        } else if (civs.length === 1) {
            singleTotalCount += 1;
            const id = civs[0];
            if (civData.total[id] !== undefined) {
                civData.total[id] += 1;
                civData.single[id] += 1;
            }
        }

        let cost = parseInt(img.dataset.cost);
        if (isNaN(cost)) cost = 0;
        if (cost > 9) cost = 9;
        mana[cost] += 1;
    });

    [1, 2, 3, 4, 5, 6].forEach(id => {
        const tEl = document.getElementById(`create-total-${id}`);
        const mEl = document.getElementById(`create-multi-${id}`);
        const sEl = document.getElementById(`create-single-${id}`);
        if (tEl) tEl.innerText = civData.total[id];
        if (mEl) mEl.innerText = civData.multi[id];
        if (sEl) sEl.innerText = civData.single[id];
    });
    for (let i = 0; i <= 9; i++) {
        const mEl = document.getElementById(`create-mana-${i}`);
        if (mEl) mEl.innerText = mana[i];
    }

    if (createColorChart) createColorChart.destroy();
    if (createManaChart) createManaChart.destroy();

    const chartOptions = { 
        responsive: true, 
        maintainAspectRatio: false, 
        plugins: { legend: { position: 'bottom' } } 
    };

    const ctxPie = document.getElementById('createColorPieChart');
    if (ctxPie) {
        createColorChart = new Chart(ctxPie, {
            type: 'pie',
            data: { 
                labels: ['単色', '多色'], 
                datasets: [{ 
                    data: [singleTotalCount, multiTotalCount], 
                    backgroundColor: ['#28a745', '#ffc107'] 
                }] 
            },
            options: chartOptions
        });
    }

    const ctxBar = document.getElementById('createManaBarChart');
    if (ctxBar) {
        createManaChart = new Chart(ctxBar, {
            type: 'bar',
            data: { 
                labels: ['0','1','2','3','4','5','6','7','8','9+'], 
                datasets: [{ 
                    label: '枚数', 
                    data: mana, 
                    backgroundColor: '#007bff' 
                }] 
            },
            options: { 
                ...chartOptions, 
                scales: { 
                    y: { 
                        beginAtZero: true, 
                        ticks: { stepSize: 1 } 
                    } 
                } 
            }
        });
    }
}
</script>