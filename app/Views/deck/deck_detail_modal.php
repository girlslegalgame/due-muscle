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
        </div>
    </div>
</div>

<!-- 共通デッキ詳細モーダルの制御スクリプト -->
<script>
let colorChart = null;
let manaChart = null;

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
        const isSpecial = card.card_type_in_deck === 'special' || card.card_name.includes('ドルマゲドン') || card.card_name.includes('零龍');

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
        const isSpecial = c.card_type_in_deck === 'special' || c.card_name.includes('ドルマゲドン') || c.card_name.includes('零龍');
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
        if (colorChart) colorChart.resize();
        if (manaChart) manaChart.resize();
    }
}

function closeModal() {
    document.getElementById('deckModal').style.display = 'none';
    document.body.style.overflow = 'auto';
}

// 枠外クリックで閉じる制御
window.addEventListener('click', (e) => {
    if (e.target == document.getElementById('deckModal')) closeModal();
    if (e.target == document.getElementById('cardDetailModal')) {
        if (typeof closeDetailModal === 'function') closeDetailModal();
    }
});
</script>