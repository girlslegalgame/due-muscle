<!-- app/Views/deck/index.php -->
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>マイデッキ一覧</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            display: grid !important;  /* flex から grid に変更 */
            grid-template-columns: repeat(8, 1fr); /* 常に横8等分 */
            grid-template-rows: repeat(5, auto);   /* 縦5行 */
            gap: 4px;                  /* カード同士の間隔 */
            width: 100%;
            max-width: 800px;          /* ウィンドウが大きく広がっても間伸びしない最大幅 */
            margin: 0 auto;            /* 中央寄せ */
            box-sizing: border-box;
        }
        
        .image-grid img {
            width: 100%;               /* 列幅（1/8）に合わせて自動リサイズ */
            height: auto;
            aspect-ratio: 110/154;     /* デュエマカードの縦横比を維持 */
            object-fit: fill;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        /* --- 分析エリア --- */
        .analysis-grid { 
            display: flex !important; 
            flex-direction: row !important; /* 縦落ちを絶対に防ぐ */
            flex-wrap: nowrap !important;   /* 折り返し禁止 */
            gap: 12px; 
            justify-content: center;
            width: 100%;
        }
        
        .chart-box { 
            flex: 1; 
            min-width: 0 !important;        /* 画面幅に応じていくらでも縮むように指定 */
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
            height: 140px;                  /* 高さを少し低めに調整して縮小時の歪みを防ぐ */
            position: relative; 
            margin-bottom: 10px; 
        }
        /* 分析テーブルのフォント縮小 */
        .analysis-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.7rem;              /* 小さい画面でも潰れないよう文字サイズを縮小 */
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
            width: 35px;                    /* 表の1列目の幅を小さくして横幅を確保 */
        }
        /* 文明カラーバー */
        .civ-fire { border-bottom: 4px solid #ff3344 !important; }
        .civ-water { border-bottom: 4px solid #3399ff !important; }
        .civ-light { border-bottom: 4px solid #ffcc00 !important; }
        .civ-dark { border-bottom: 4px solid #666666 !important; }
        .civ-nature { border-bottom: 4px solid #22aa55 !important; }
        .civ-zero { border-bottom: 4px solid #999999 !important; }

        /* 超GR：縦3枚・横4枚 (12枚) */
        .grid-gr {
            display: grid !important;
            grid-template-columns: repeat(4, 1fr); 
            gap: 4px;
            width: 100%;
            max-width: 320px;
            justify-content: center;
        }
        /* 超次元：縦2枚・横4枚 (8枚) */
        .grid-dim {
            display: grid !important;
            grid-template-columns: repeat(4, 1fr); 
            gap: 4px;
            width: 100%;
            max-width: 320px;
            justify-content: center;
        }
        /* 特殊：フレックス並び */
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
            flex-direction: row !important; /* スマホでも縦並びに落とさない */
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
            min-width: 0;                   /* 子要素が極限まで縮むように許可 */
        }
        
        .vertical-divider {
            width: 1px;
            background-color: #ddd;
            align-self: stretch;
            margin: 0 10px;
            flex-shrink: 0;
        }        
        /* 横線のスタイル */
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
        /* 画像サイズ調整 */
        .grid-gr img,
        .grid-dim img,
        .grid-special img {
            width: 100% !important;
            height: auto !important;
            aspect-ratio: 110/154 !important;
            object-fit: fill;
            border: 1px solid #ddd;
            border-radius: 4px;
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

<!-- デッキ詳細モーダル -->
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
                <!-- 左側：超GRゾーン -->
                <div class="extra-side">
                    <h4 class="zone-title" style="margin:0 0 10px 0;">超GRゾーン</h4>
                    <div id="modal-gr-list" class="grid-gr"></div>
                </div>
                
                <!-- 中央の縦線 -->
                <div class="vertical-divider"></div>
                
                <!-- 右側：超次元ゾーン ＆ 特殊カード -->
                <div class="extra-side" style="display: flex; flex-direction: column; width: 100%;">
                    
                    <!-- 超次元 -->
                    <div style="width: 100%; display: flex; flex-direction: column; align-items: center;">
                        <h4 class="zone-title" style="margin:0 0 10px 0;">超次元ゾーン</h4>
                        <div id="modal-dim-list" class="grid-dim"></div>
                    </div>
                    
                    <!-- 横線 -->
                    <div class="horizontal-divider"></div>
                    
                    <!-- 特殊カード -->
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
                    <!-- 文明分析 -->
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

                    <!-- マナ分析 -->
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

<!-- 共通カード詳細モーダルの読み込み -->
<?php include __DIR__ . '/card_detail_modal.php'; ?>

<script>
let colorChart = null;
let manaChart = null;

/**
 * デッキ詳細を開く
 */
function openDeckModal(deckId, deckName) {
    document.getElementById('modal-deck-title').innerText = deckName;
    
    // 表示の初期化
    const mainList = document.getElementById('modal-main-list');
    mainList.innerHTML = '読み込み中...';
    document.getElementById('modal-dim-list').innerHTML = '';
    document.getElementById('modal-gr-list').innerHTML = '';
    document.getElementById('modal-special-list').innerHTML = '';

    document.getElementById('deckModal').style.display = 'block';
    document.body.style.overflow = 'hidden'; 
    
    // 初期タブをメインに
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

/**
 * 画像の描画と振り分け
 */
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
            img.dataset.cardId = card.card_id; // ★カードIDを保持
            img.style.cursor = 'pointer';      // ★カーソルをポインターにする
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

/**
 * 分析グラフと表の初期化（メインデッキのみを対象に集計）
 */
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

    const chartOptions = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { position: 'bottom' } }
    };

    const ctxPie = document.getElementById('colorPieChart');
    if (ctxPie) {
        colorChart = new Chart(ctxPie, {
            type: 'pie',
            data: { 
                labels: ['単色', '多色'], 
                datasets: [{ data: [singleTotalCount, multiTotalCount], backgroundColor: ['#28a745', '#ffc107'] }] 
            },
            options: chartOptions
        });
    }

    const ctxBar = document.getElementById('manaBarChart');
    if (ctxBar) {
        manaChart = new Chart(ctxBar, {
            type: 'bar',
            data: { 
                labels: ['0','1','2','3','4','5','6','7','8','9+'], 
                datasets: [{ label: '枚数', data: mana, backgroundColor: '#007bff' }] 
            },
            options: {
                ...chartOptions,
                scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
            }
        });
    }
}

/**
 * タブ切り替え
 */
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

// 枠外クリックで閉じる制御のアップデート
window.onclick = (e) => { 
    if (e.target == document.getElementById('deckModal')) closeModal();
    if (e.target == document.getElementById('cardDetailModal')) closeDetailModal();
}
</script>

</body>
</html>