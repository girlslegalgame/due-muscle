<!-- app/Views/deck/create.php -->
<style>
    body {
        margin: 0;
        padding: 0;
        display: flex;
        flex-direction: column;
        height: 100vh;
        background-color: #f0f0f0;
        font-family: sans-serif;
        overflow: hidden;
    }

    /* 全体のコンテナ幅をレスポンシブにし、縦幅も画面サイズ（100vh）に完全に収める */
    #container { 
        width: 100%; 
        max-width: 900px; 
        height: 100vh; 
        max-height: 100vh; 
        margin: 0 auto; 
        display: flex; 
        flex-direction: column; 
        flex-grow: 1; 
        box-sizing: border-box;
        position: relative; /* ★ 浮遊する検索エリアの基準点 */
    }

    /* --- 2. デッキ作成エリア（上部） --- */
    #deck-area { 
        flex: 1; 
        min-height: 0; 
        display: flex; 
        flex-direction: column; 
        padding: 10px; 
        padding-bottom: 220px; /* ★ 初期値：検索エリアの高さ(200px) + 余白(20px) */
        box-sizing: border-box;
    }
    #deck-tabs { display: flex; gap: 5px; height: 35px; flex-shrink: 0; }
    .tab { 
        background: #eee; padding: 5px 15px; border: 1px solid #ccc; 
        cursor: pointer; font-weight: bold; border-radius: 4px 4px 0 0; font-size: 14px; 
    }
    .tab.active { background: #fff; border-bottom: none; color: #007bff; }
    
    #deck-header { display: flex; justify-content: space-between; align-items: center; height: 40px; flex-shrink: 0; }
    #deck-header h3 { margin: 0; font-size: 16px; }

    /* ゾーン表示制御 */
    .deck-content { display: none; width: 100%; flex: 1; min-height: 0; }
    .deck-content.active { display: grid; }

    /* メインデッキリスト (8列固定) */
    #main-deck-list {
        grid-template-columns: repeat(8, 1fr); 
        background-color: #fff; border: 1px solid #ccc;
        padding: 0; gap: 0; overflow-y: hidden; overflow-x: hidden;
        margin-bottom: 20px !important; /* ★ 下部に20pxの空間（ゆとり）を強制確保 */
    }
    #main-deck-list img { 
        width: 100%; 
        height: 100%; /* ★ マス目の高さに追従させます */
        aspect-ratio: 110/154; 
        object-fit: contain; 
        cursor: pointer; 
        display: block; 
    }
    /* 40枚を超えた時のスタック（縦重なり） */
    #main-deck-list.stacked-mode { grid-auto-rows: 45px; }

    /* --- 3. 超次元・GRエリア --- */
    #extra-deck-area { 
        display: none; flex-direction: row; gap: 15px; padding: 10px; 
        border: 1px solid #ccc; background: #f9f9f9; 
    }
    #extra-deck-area.active { display: flex !important; } /* 横並びにするためflex */
    
    .extra-side { flex: 1; display: flex; flex-direction: column; align-items: center; }
    .extra-list { 
        display: grid !important; grid-template-columns: repeat(4, 90px); 
        grid-auto-rows: 125px; gap: 5px; background: #fff; border: 1px solid #ddd; padding: 5px;
    }
    .extra-list img { width: 90px; height: auto; aspect-ratio: 110/154; object-fit: fill; border-radius: 4px; }
    .v-divider { width: 1px; background: #ccc; align-self: stretch; margin: 10px 0; }

    /* --- 4. 特殊タブ（ドルマゲドン・零龍） --- */
    #special-deck-list.active {
        display: flex !important; 
        flex-direction: row !important;
        justify-content: center; align-items: stretch;
        background: #fff; border: 1px solid #ccc;
    }
    .special-zone { flex: 1; display: flex; flex-direction: column; align-items: center; padding: 20px; }
    .special-v-line { width: 2px; background-color: #ccc; margin: 20px 0; flex-shrink: 0; }
    .special-label { margin: 0 0 10px 0; font-size: 15px; font-weight: bold; }
    .btn-add-special { margin-bottom: 20px; padding: 8px 20px; background: #007bff; color: #fff; border: none; border-radius: 4px; cursor: pointer; }
    
    .special-box {
        width: 280px; height: 390px;
        border: 2px dashed #bbb;
        display: flex; justify-content: center; align-items: center;
        border-radius: 8px; background: #fff; overflow: hidden;
    }
    .special-box img {
        max-width: 100%; max-height: 100%; object-fit: contain; cursor: pointer;
    }
    .special-box.active { border: 2px solid #28a745; }

    /* --- 5. 検索セクション（下部） --- */
    #search-section {
        position: fixed;      /* ★ 画面最下部に固定（浮遊化） */
        bottom: 0;
        left: 50%;
        transform: translateX(-50%) translateY(0); /* ★ 通常時は表示状態 */
        transition: transform 0.3s ease; /* ★ スライド閉閉時の滑らかなアニメーション */
        width: 100%;
        max-width: 900px;     
        height: 200px;        
        z-index: 2000;        
        box-sizing: border-box;
        background: rgba(249, 249, 249, 0.98); 
        border-top: 3px solid #333;
        box-shadow: 0 -5px 20px rgba(0, 0, 0, 0.15); 
        border-radius: 12px 12px 0 0; 
        padding: 5px 10px; 
        display: flex; 
        flex-direction: column; 
    }

    /* ★ 検索セクションが閉じている状態（画面外に引っ込める） */
    #search-section.collapsed {
        transform: translateX(-50%) translateY(200px); 
    }

    /* ★ 検索開閉ボタン（タブ） */
    #search-toggle-btn {
        position: absolute;
        top: -32px; /* ★ 検索エリアの上に飛び出させる */
        right: 20px;
        height: 32px;
        padding: 0 15px;
        background: #333;
        color: #fff;
        border: none;
        border-radius: 8px 8px 0 0;
        cursor: pointer;
        font-weight: bold;
        font-size: 13px;
        display: flex;
        align-items: center;
        gap: 5px;
        box-shadow: 0 -3px 10px rgba(0,0,0,0.15);
        z-index: 2001;
    }
    #search-toggle-btn:hover {
        background: #444;
    }

    #search-results { 
        height: 110px; 
        display: flex; overflow-x: auto; white-space: nowrap; padding: 3px 0; gap: 8px; align-items: center; 
    }
    #search-results img { height: 100px; cursor: pointer; border: 1px solid #ccc; flex-shrink: 0; }
    
    #search-controls-wrapper { display: flex; flex-direction: column; gap: 5px; }
    #search-controls { display: flex; gap: 10px; height: 45px; align-items: center; }
    #card-search-input { flex: 1; padding: 10px; border: 1px solid #ccc; border-radius: 4px; font-size: 16px; }
    .btn-filter { padding: 10px 20px; background: #17a2b8; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; }
    .btn-sort { padding: 10px 20px; background: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; }
    .search-scope { display: flex; gap: 15px; font-size: 12px; color: #666; }

    /* --- 6. モーダル共通 --- */
    #cardDetailModal, #filterModal, .sub-modal { 
        display: none; position: fixed; z-index: 3000; 
        left: 0; top: 0; width: 100%; height: 100%; 
        background: rgba(0,0,0,0.8); 
    }
    .detail-content, .filter-content, .sub-modal-content { 
        background: white; margin: 5vh auto; padding: 25px; 
        border-radius: 12px; position: relative; box-sizing: border-box; 
    }
    
    /* カード詳細 */
    .detail-content { width: 850px; display: flex; flex-direction: column; }
    .detail-top { display: flex; gap: 25px; margin-bottom: 20px; }
    .detail-left { width: 280px; flex-shrink: 0; text-align: center; }
    .detail-left img { width: 100%; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.2); }
    .detail-right { flex: 1; min-width: 0; }
    #detail-name { margin: 0 0 15px 0; font-size: 1.5rem; border-bottom: 2px solid #eee; padding-bottom: 10px; }
    #detail-text { white-space: pre-wrap; word-wrap: break-word; background: #f8f9fa; padding: 15px; border-radius: 5px; font-size: 0.95rem; line-height: 1.6; max-height: 250px; overflow-y: auto; }
    .version-list { display: flex; overflow-x: auto; gap: 12px; padding: 10px 5px; }
    .version-list img { height: 100px; cursor: pointer; border: 3px solid transparent; border-radius: 4px; flex-shrink: 0; }
    .version-list img.selected { border-color: #007bff; }
    .qty-controls { display: flex; align-items: center; gap: 20px; margin: 20px 0; justify-content: center; font-size: 1.2rem; }
    .btn-qty { width: 40px; height: 40px; border-radius: 50%; border: 1px solid #ccc; cursor: pointer; font-size: 1.5rem; background: #fff; }

    /* 絞り込み */
    .filter-content { width: 500px; }
    .filter-scroll { max-height: 400px; overflow-y: auto; padding-right: 5px; }
    .civ-checkboxes { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 10px; }
    .select-trigger { 
        width: 100% !important; padding: 6px 10px; border: 1px solid #ccc; border-radius: 4px; 
        cursor: pointer; background: #fff; box-sizing: border-box; min-height: 32px; 
        font-size: 13px; color: #333; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
    }
    .logic-switch { display: flex; border: 1px solid #ccc; border-radius: 20px; overflow: hidden; background: #eee; }
    .logic-switch label { flex: 1; text-align: center; cursor: pointer; font-size: 11px; margin:0; padding: 3px 8px; }
    .logic-switch input { display: none; }
    .logic-switch input:checked + span { background: #007bff; color: #fff; display: block; border-radius: 20px; }

    /* サブモーダル */
    .sub-modal-content { width: 400px; display: flex; flex-direction: column; height: 80vh; }
    .sub-modal-header { padding: 15px; background: #333; color: #fff; display: flex; justify-content: space-between; border-radius: 12px 12px 0 0; }
    .sub-modal-body { flex: 1; overflow-y: auto; padding: 15px; }
    .sub-modal-content input[type="text"] { width: 100% !important; box-sizing: border-box; padding: 10px; margin-bottom: 10px; border: 1px solid #ccc; border-radius: 4px; }
    .list-item { display: flex; align-items: center; justify-content: space-between; padding: 10px 5px; border-bottom: 1px solid #eee; cursor: pointer; font-size: 14px; }

    /* その他 */
    #trash-area {
        width: 60px; height: 60px; background: #ffcccc; border: 2px dashed #cc0000;
        display: none; justify-content: center; align-items: center; 
        font-size: 30px; border-radius: 8px; color: #cc0000;
        position: fixed; right: calc(50% - 440px); top: 120px; z-index: 100;
    }
    .search-msg { display: flex; align-items: center; justify-content: center; width: 100%; min-width: 880px; height: 100px; color: #666; font-weight: bold; }
    .special-box.empty img { display: none; }
/* --- デッキ公開トグルスイッチのスタイル --- */
        .switch-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 15px;
            border-top: 1px solid #eee;
            padding-top: 15px;
        }
        .switch {
            position: relative;
            display: inline-block;
            width: 46px;
            height: 24px;
            flex-shrink: 0;
        }
        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0; left: 0; right: 0; bottom: 0;
            background-color: #ccc;
            transition: .3s;
            border-radius: 24px;
        }
        .slider:before {
            position: absolute;
            content: "";
            height: 16px;
            width: 16px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .3s;
            border-radius: 50%;
        }
        .switch input:checked + .slider {
            background-color: #28a745; /* ONのときは緑色に */
        }
        .switch input:checked + .slider:before {
            transform: translateX(22px);
        }
</style>

<!-- 外部ライブラリ読み込み -->
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>

<div id="container">
    <!-- デッキエリア -->
    <div id="deck-area">
        <div id="deck-tabs">
            <div id="tab-main" class="tab active" onclick="switchDeckTab('main')">メイン 0</div>
            <div id="tab-extra" class="tab" onclick="switchDeckTab('extra')">GR/超次元 0</div>
            <div id="tab-special" class="tab" onclick="switchDeckTab('special')">特殊</div>
        </div>
        <div id="deck-header">
            <h3>デッキ内容</h3>
            <div style="display: flex; align-items: center; gap: 15px;">
                <button id="save-deck-btn" onclick="openSaveModal()" style="padding: 8px 20px; background: #28a745; color: #fff; border: none; border-radius: 4px; cursor: pointer; font-weight: bold;">保存する</button>                <div id="trash-area">🗑️</div>
            </div>
        </div>

        <!-- メインリスト -->
        <div id="main-deck-list" class="deck-content active"></div>

        <!-- GR/超次元エリア -->
        <div id="extra-deck-area" class="deck-content" style="display:none; flex-direction:row; gap:20px; padding:10px;">
            <div class="zone-container" style="flex:1;">
                <h4 style="margin:0 0 5px 0; border-bottom:2px solid #007bff;">超GRゾーン (最大12枚)</h4>
                <div id="gr-list" class="extra-list gr-layout"></div>
            </div>
            <div class="zone-container" style="flex:1;">
                <h4 style="margin:0 0 5px 0; border-bottom:2px solid #dc3545;">超次元ゾーン (最大8枚)</h4>
                <div id="super-dim-list" class="extra-list dim-layout"></div>
            </div>
        </div>

        <!-- 特殊リスト -->
        <div id="special-deck-list" class="deck-content">
            <div class="special-zone">
                <h4 class="special-label">終焉の禁断 ドルマゲドンX</h4>
                <button id="btn-doru" class="btn-add-special" onclick="toggleSpecial('slot-dolmagedon')">追加する</button>
                <div id="slot-dolmagedon" class="special-box empty"></div>
            </div>
            <div class="special-v-line"></div>
            <div class="special-zone">
                <h4 class="special-label">零龍</h4>
                <button id="btn-zero" class="btn-add-special" onclick="toggleSpecial('slot-zeroron')">追加する</button>
                <div id="slot-zeroron" class="special-box empty"></div>
            </div>
        </div>    
    </div>

    <!-- 検索エリア -->
    <div id="search-section">
        <!-- ★ 検索開閉トグルボタンを追加 -->
        <button id="search-toggle-btn" onclick="toggleSearchSection()">▼ 検索を隠す</button>
        <div id="search-results"></div>
        <div id="search-controls-wrapper">
            <div id="search-controls">
                <input type="text" id="card-search-input" placeholder="カード名を入力して即時検索..." autocomplete="off">
                <button class="btn-filter" onclick="toggleFilterModal()">絞り込み</button>
                <button class="btn-sort" onclick="toggleSortModal()">並び替え</button>
            </div>
            <div class="search-scope">
                <label><input type="checkbox" class="scope-check" value="name" checked> カード名</label>
                <label><input type="checkbox" class="scope-check" value="text"> テキスト</label>
            </div>
        </div>
    </div>
</div>

<!-- 詳細モーダル -->
<div id="cardDetailModal">
    <div class="detail-content">
        <span onclick="closeDetailModal()" style="position:absolute; right:20px; top:10px; cursor:pointer; font-size:30px;">&times;</span>
        <div class="detail-top">
            <div class="detail-left">
                <img id="detail-main-img" src="">
                <div class="qty-controls">
                    <button class="btn-qty" onclick="adjustQty(-1)">-</button>
                    <span id="detail-qty">0</span> / 4枚
                    <button class="btn-qty" onclick="adjustQty(1)">+</button>
                </div>
            </div>
            <div class="detail-right"><h2 id="detail-name"></h2><div id="detail-text"></div></div>
        </div>
        <div class="version-section"><h4>バージョン切り替え</h4><div id="detail-version-list" class="version-list"></div></div>
    </div>
</div>

<!-- 絞り込みモーダル -->
<div id="filterModal">
    <div class="filter-content">
        <h3>詳細絞り込み</h3>
        <div class="filter-scroll">
            <div class="filter-group">
                <label>文明</label>
                <!-- 単色・多色選択 -->
                <div style="display: flex; gap: 15px; margin-bottom: 8px;">
                    <label style="font-size: 13px;"><input type="checkbox" id="filter-civ-single" checked onchange="toggleFilterCivType()"> 単色</label>
                    <label style="font-size: 13px;"><input type="checkbox" id="filter-civ-multi" checked onchange="toggleFilterCivType()"> 多色</label>
                </div>
                
                <div class="civ-checkboxes">
                    <label><input type="checkbox" class="civ-check" value="1"> 光</label>
                    <label><input type="checkbox" class="civ-check" value="2"> 水</label>
                    <label><input type="checkbox" class="civ-check" value="3"> 闇</label>
                    <label><input type="checkbox" class="civ-check" value="4"> 火</label>
                    <label><input type="checkbox" class="civ-check" value="5"> 自然</label>
                    <label><input type="checkbox" class="civ-check" value="6"> ゼロ</label>
                </div>
                <!-- 多色がONの時のみ表示される「含まれない文明」除外エリア -->
                <div id="filter-exclude-civ-area" style="display: flex; gap: 10px; flex-wrap: wrap; border-top: 1px dashed #ccc; padding-top: 8px; margin-top: 5px;">
                    <span style="font-size: 11px; color: #666; width: 100%;"><strong>含まれない文明を指定</strong></span>
                    <label style="font-size: 11px;"><input type="checkbox" class="filter-exclude-civ-check" value="1"> 光</label>
                    <label style="font-size: 11px;"><input type="checkbox" class="filter-exclude-civ-check" value="2"> 水</label>
                    <label style="font-size: 11px;"><input type="checkbox" class="filter-exclude-civ-check" value="3"> 闇</label>
                    <label style="font-size: 11px;"><input type="checkbox" class="filter-exclude-civ-check" value="4"> 火</label>
                    <label style="font-size: 11px;"><input type="checkbox" class="filter-exclude-civ-check" value="5"> 自然</label>
                    <label style="font-size: 11px;"><input type="checkbox" class="filter-exclude-civ-check" value="6"> ゼロ</label>
                </div>
            
                <!-- 含む / のみ持つ ラジオボタン -->
                <div style="display: flex; gap: 15px; margin-bottom: 8px; font-size: 12px; color: #555;">
                    <label style="cursor: pointer;"><input type="radio" name="filter-civ-match-type" value="include" checked> 選択した文明を含む</label>
                    <label style="cursor: pointer;"><input type="radio" name="filter-civ-match-type" value="match"> 選択した文明のみ持つ</label>
                </div>
            
            </div>
            <div style="display:flex; gap:20px;">
                <div class="filter-group"><label>コスト</label><input type="number" id="cost-min" style="width:60px;"> 〜 <input type="number" id="cost-max" style="width:60px;"></div>
                <div class="filter-group"><label>パワー</label><input type="number" id="pow-min" style="width:80px;"> 〜 <input type="number" id="pow-max" style="width:80px;"></div>
            </div>
            <div class="filter-group">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <label>種族</label>
                    <div class="logic-switch">
                        <label><input type="radio" name="race_logic" value="AND"><span>AND</span></label>
                        <label><input type="radio" name="race_logic" value="OR" checked><span>OR</span></label>
                    </div>
                </div>
                <div id="race-trigger" class="select-trigger" onclick="openSubModal('race')">種族を選択</div>
            </div>
            <div class="filter-group">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <label>特殊能力</label>
                    <div class="logic-switch">
                        <label><input type="radio" name="ability_logic" value="AND"><span>AND</span></label>
                        <label><input type="radio" name="ability_logic" value="OR" checked><span>OR</span></label>
                    </div>
                </div>
                <div id="ability-trigger" class="select-trigger" onclick="openSubModal('ability')">特殊能力を選択</div>
            </div>
            <div class="filter-group"><label>レギュレーション</label><label><input type="checkbox" class="reg-check" value="2"> 殿堂</label><label><input type="checkbox" class="reg-check" value="3"> プレミアム殿堂</label></div>
        </div>
        <button onclick="applyFilters()" style="width:100%; padding:10px; background:#007bff; color:white; border:none; border-radius:4px; margin-top:10px;">適用する</button>
        <button onclick="clearAllFilters()" style="width:100%; padding:10px; background:#ccc; border:none; border-radius:4px; margin-top:5px;">クリア</button>
    </div>
</div>

<!-- 並び替えモーダル -->
<div id="sortModal" class="sub-modal">
    <div class="sub-modal-content" style="width: 400px; height: auto; max-height: 90vh; margin: 15vh auto;">
        <div class="sub-modal-header" style="padding: 15px; background: #333; color: #fff; display: flex; justify-content: space-between; border-radius: 12px 12px 0 0;">
            <span style="font-weight: bold;">並び替え設定</span>
            <span onclick="toggleSortModal()" style="cursor:pointer; font-size: 24px;">&times;</span>
        </div>
        <div class="sub-modal-body" style="padding: 20px; display: flex; flex-direction: column; gap: 15px;">
            <div>
                <label style="font-weight: bold; font-size: 13px; display: block; margin-bottom: 5px;">並び替え項目</label>
                <select id="sort-key" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; background: white; font-size: 14px;">
                    <option value="free">自由に並べ替える</option>
                    <option value="civ">文明順</option>
                    <option value="cost">コスト順</option>
                    <option value="name">カード名順</option>
                    <option value="count">採用順</option>
                </select>
            </div>
            <div>
                <label style="font-weight: bold; font-size: 13px; display: block; margin-bottom: 5px;">並び順</label>
                <div style="display: flex; gap: 20px;">
                    <label style="cursor: pointer; font-size: 14px;"><input type="radio" name="sort-order" value="asc" checked style="margin-right: 5px;">昇順</label>
                    <label style="cursor: pointer; font-size: 14px;"><input type="radio" name="sort-order" value="desc" style="margin-right: 5px;">降順</label>
                </div>
            </div>
        </div>
        <div style="padding: 15px; border-top: 1px solid #eee; display: flex; gap: 10px;">
            <button onclick="toggleSortModal()" style="flex: 1; padding: 10px; background: #ccc; border: none; border-radius: 4px; cursor: pointer; font-weight: bold;">キャンセル</button>
            <button onclick="applySort()" style="flex: 1; padding: 10px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: bold;">適用する</button>
        </div>
    </div>
</div>

<!-- サブモーダル (種族/能力) -->
<div id="raceSelectModal" class="sub-modal"><div class="sub-modal-content">
    <div style="padding:15px; background:#333; color:#fff; display:flex; justify-content:space-between;"><span>種族選択</span><span onclick="closeSubModal('race')" style="cursor:pointer;">&times;</span></div>
    <div style="padding:10px;"><input type="text" id="race-list-search" placeholder="検索..." style="width:100%; padding:8px;"></div>
    <div id="race-list-container" class="sub-modal-body"></div>
    <div style="padding:10px; border-top:1px solid #eee;"><button onclick="closeSubModal('race')" style="width:100%; padding:10px; background:#007bff; color:#fff; border:none; border-radius:4px;">決定</button></div>
</div></div>

<div id="abilitySelectModal" class="sub-modal"><div class="sub-modal-content">
    <div style="padding:15px; background:#333; color:#fff; display:flex; justify-content:space-between;"><span>特殊能力選択</span><span onclick="closeSubModal('ability')" style="cursor:pointer;">&times;</span></div>
    <div style="padding:10px;"><input type="text" id="ability-list-search" placeholder="検索..." style="width:100%; padding:8px;"></div>
    <div id="ability-list-container" class="sub-modal-body"></div>
    <div style="padding:10px; border-top:1px solid #eee;"><button onclick="closeSubModal('ability')" style="width:100%; padding:10px; background:#007bff; color:#fff; border:none; border-radius:4px;">決定</button></div>
</div></div>

<!-- デッキ保存・設定モーダル -->
<div id="deckSaveModal" class="sub-modal">
    <div class="sub-modal-content" style="width: 450px; height: auto; max-height: 90vh;">
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #eee; padding-bottom: 10px; margin-bottom: 15px;">
            <h3 style="margin: 0;">デッキ保存設定</h3>
            <span onclick="closeSaveModal()" style="cursor:pointer; font-size:24px;">&times;</span>
        </div>
        
        <div style="display: flex; flex-direction: column; gap: 15px; overflow-y: auto; max-height: 60vh; padding-right: 5px;">
            <!-- デッキ名 -->
            <div>
                <label style="font-weight: bold; font-size: 13px; display: block; margin-bottom: 5px;">デッキ名</label>
                <input type="text" id="save-deck-name" placeholder="デッキ名を入力してください" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box;">
            </div>
            
            <!-- フォーマット選択 -->
            <div>
                <label style="font-weight: bold; font-size: 13px; display: block; margin-bottom: 5px;">フォーマット</label>
                <select id="save-deck-format" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; background: white; font-size: 14px;">
                    <!-- JSで動的にオプション生成 -->
                </select>
            </div>
            
            <!-- サムネイル選択 -->
            <div>
                <label style="font-weight: bold; font-size: 13px; display: block; margin-bottom: 5px;">デッキサムネイル</label>
                <div style="display: flex; gap: 15px; align-items: center;">
                    <div id="thumbnail-preview-box" style="width: 90px; height: 126px; border: 2px dashed #ccc; border-radius: 4px; display: flex; align-items: center; justify-content: center; overflow: hidden; background: #f9f9f9; flex-shrink: 0;">
                        <span id="thumbnail-placeholder" style="font-size: 11px; color: #999; text-align: center; padding: 5px;">未選択</span>
                        <img id="thumbnail-preview-img" style="display: none; width: 100%; height: 100%; object-fit: contain;">
                    </div>
                    <div style="flex: 1;">
                        <p style="font-size: 12px; color: #666; margin: 0 0 8px 0;">デッキに採用されているカードの中から、1枚をサムネイル画像（看板）として登録できます。</p>
                        <button type="button" onclick="openThumbnailSelector()" style="padding: 6px 12px; background: #17a2b8; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 12px; font-weight: bold;">カードを選択する</button>
                    </div>
                </div>
                <input type="hidden" id="save-deck-thumbnail-id" value="">
            </div>

            <div class="switch-container">
                <div>
                    <label style="font-weight: bold; font-size: 13px; display: block; margin-bottom: 2px;">デッキを公開する</label>
                    <span style="font-size: 11px; color: #666; display: block;">公開すると、他のユーザーがデッキ検索で閲覧・コピーできるようになります。</span>
                </div>
                <label class="switch">
                    <input type="checkbox" id="save-deck-public">
                    <span class="slider"></span>
                </label>
            </div>
        </div>
        
        <div style="margin-top: 20px; display: flex; gap: 10px;">
            <button onclick="closeSaveModal()" style="flex: 1; padding: 10px; background: #ccc; border: none; border-radius: 4px; cursor: pointer; font-weight: bold;">キャンセル</button>
            <button onclick="submitDeckSave()" style="flex: 1; padding: 10px; background: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: bold;">保存する</button>
        </div>
    </div>
</div>

<!-- サムネイルカード選択サブモーダル -->
<div id="thumbnailSelectModal" class="sub-modal">
    <div class="sub-modal-content" style="width: 400px; height: 75vh;">
        <div class="sub-modal-header" style="padding: 12px; background: #333; color: #fff; display: flex; justify-content: space-between; align-items: center;">
            <span style="font-weight: bold; font-size: 14px;">サムネイルにするカードを選択</span>
            <span onclick="closeThumbnailSelector()" style="cursor:pointer; font-size: 20px;">&times;</span>
        </div>
        <div id="thumbnail-cards-list" class="sub-modal-body" style="padding: 15px; display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; align-content: start;">
            <!-- JSでデッキ内カード画像を動的に配置 -->
        </div>
    </div>
</div>

<script>
// --- A. グローバル状態管理 ---
const isEdit = <?php echo !empty($isEdit) ? 'true' : 'false'; ?>;
const deckId = <?php echo isset($deck['deck_id']) ? $deck['deck_id'] : 'null'; ?>;
const initialDeckName = <?php echo isset($deck['deck_name']) ? json_encode($deck['deck_name']) : '"マイデッキ"'; ?>;
const initialCards = <?php echo !empty($initialCards) ? json_encode($initialCards) : '[]'; ?>;
const currentSort = { key: null, order: 'asc' };

// Controllerからビューに渡されたフォーマット情報を取得
const formatsFromPhp = <?php echo isset($formats) ? json_encode($formats) : '[]'; ?>;

const mainList = document.getElementById('main-deck-list');
const superDimList = document.getElementById('super-dim-list');
const grList = document.getElementById('gr-list');
const resultsDiv = document.getElementById('search-results');
const trashArea = document.getElementById('trash-area');
const input = document.getElementById('card-search-input');

// 検索・通信管理
let currentFilters = { 
    q: '', scope: ['name'], civs: [], cost_min: '', cost_max: '', 
    pow_min: '', pow_max: '', races: [], abilities: [], 
    race_logic: 'OR', ability_logic: 'OR', reg: [] ,
    civ_type: '', civ_match_type: 'include', exclude_civs: []
};
let currentOffset = 0, isFetching = false, hasMoreCards = true;
let searchTimeout = null, abortController = null;

// モーダル・カード情報管理
let selectedCardData = null, allVersions = [], activeClickedElement = null;

/**
 * 画像パス生成 (通常・両面対応)
 */
function getCardImagePath(card) {
    if (!card.imagepath) return '/images/card/noimage.webp';
    const path = card.imagepath.startsWith('/') ? card.imagepath : '/' + card.imagepath;
    return '/images/card' + path;
}

function handleImageError(img) {
    img.src = '/images/card/noimage.webp';
    img.onerror = null;
}

// --- B. 初期化 ---
window.addEventListener('DOMContentLoaded', () => {
    // ★ 1. まず先にPHPから渡されたフォーマットデータをセレクトボックスに描画します
    if (formatsFromPhp && formatsFromPhp.length > 0) {
        renderFormatSelect(formatsFromPhp);
    }
    // 編集モード：既存デッキ読み込み
    if (isEdit && initialCards.length > 0) {
        initialCards.forEach(card => { 
            const qty = parseInt(card.quantity, 10) || 1;
            for (let i = 0; i < qty; i++) {
                addCardToDeck(card, card.card_type_in_deck); 
            }
        });
        updateDeckDisplay();
        document.getElementById('save-deck-btn').innerText = "上書き保存";
        
        // オプションが既に存在するため、既存のフォーマットが確実に選択・維持されます
        <?php if (isset($deck['format_id'])): ?>
            document.getElementById('save-deck-format').value = <?php echo $deck['format_id']; ?>;
        <?php endif; ?>

        <?php if (isset($deck['is_public'])): ?>
            document.getElementById('save-deck-public').checked = <?php echo $deck['is_public'] ? 'true' : 'false'; ?>;
        <?php endif; ?>

        <?php if (isset($deck['thumbnail_card_id']) && isset($deck['thumbnail_imagepath'])): ?>
            setThumbnail(
                '<?php echo $deck['thumbnail_card_id']; ?>', 
                '<?php echo $deck['thumbnail_imagepath']; ?>'
            );
        <?php endif; ?>
    }

    // マスターデータの取得
    fetch('/api/master-data')
        .then(res => res.json())
        .then(data => {
            renderMasterList('race', data.races);
            renderMasterList('ability', data.abilities);
        });

    // 初期ロード後にサイズ調整
    setTimeout(adjustMainDeckRows, 100);

    // デッキエリアのサイズ監視設定
    const resizeObserver = new ResizeObserver(entries => {
        for (let entry of entries) {
            if (entry.contentRect.width > 0) {
                adjustMainDeckRows();
            }
        }
    });
    const deckArea = document.getElementById('deck-area');
    if (deckArea) resizeObserver.observe(deckArea);
});

/**
 * フォーマットのドロップダウンオプションのレンダリング
 */
function renderFormatSelect(formats) {
    const select = document.getElementById('save-deck-format');
    select.innerHTML = '';
    formats.sort((a, b) => a.format_id - b.format_id); // IDの昇順ソート
    formats.forEach(f => {
        const opt = document.createElement('option');
        opt.value = f.format_id;
        opt.innerText = f.format_name;
        select.appendChild(opt);
    });
}

/**
 * デッキリストにカード画像を追加する
 */
function addCardToDeck(card, forcedType = null) {
    const img = document.createElement('img');
    img.src = getCardImagePath(card);
    img.dataset.cardId = card.card_id;
    img.dataset.cardName = card.card_name;
    img.dataset.charIds = card.char_ids;
    img.dataset.imagepath = card.imagepath;
    // 並び替え用に文明とコストの情報を属性として保存
    img.dataset.civ = card.civilization || card.civ || card.civ_ids || '';    img.dataset.cost = card.cost !== undefined ? card.cost : '';
    img.alt = card.card_name;
    img.onerror = () => handleImageError(img);

    const isSpecial = card.card_name.includes('ドルマゲドン') || card.card_name.includes('零龍');
    const type = forcedType || (isSpecial ? 'special' : determineZoneType(card.char_ids));

    if (type === 'special') {
        const slotId = card.card_name.includes('ドルマゲドン') ? 'slot-dolmagedon' : 'slot-zeroron';
        const slot = document.getElementById(slotId);
        if (slot) {
            slot.innerHTML = ''; 
            slot.appendChild(img);
            slot.classList.add('active');
            slot.classList.remove('empty');
            updateSaveButton(slotId, true);
        }
    } else {
        let targetList = mainList;
        if (type === 'super_dimensional') targetList = superDimList;
        else if (type === 'gr') targetList = grList;
        targetList.appendChild(img);
    }
    updateDeckDisplay();
}

/**
 * 特殊タイプIDから適切なゾーン名を判定する補助関数
 */
function determineZoneType(charIdsStr) {
    const ids = (charIdsStr || '').split(',');
    if (ids.includes('3') || ids.includes('6')) return 'super_dimensional';
    if (ids.includes('10')) return 'gr';
    return 'main';
}

// --- C. クリックイベント（デリゲーション） ---
[mainList, superDimList, grList].forEach(list => list.addEventListener('click', (e) => {
    if (e.target.tagName === 'IMG') openCardDetail(e.target.dataset.cardId, e.target);
}));
resultsDiv.addEventListener('click', (e) => {
    if (e.target.tagName === 'IMG') openCardDetail(e.target.dataset.cardId, e.target);
});
document.querySelectorAll('.special-box').forEach(box => box.addEventListener('click', (e) => {
    if (e.target.tagName === 'IMG') openCardDetail(e.target.dataset.cardId, e.target);
}));


// --- D. SortableJS 設定 ---
const deckSortableConfig = {
    group: 'shared', animation: 150,
    onStart: () => trashArea.style.display = 'flex',
    onEnd: () => { trashArea.style.display = 'none'; updateDeckDisplay(); },
    onAdd: function(evt) {
        const item = evt.item;
        const name = item.dataset.cardName;
        const charIds = (item.dataset.charIds || '').split(',');

        if (name.includes('ドルマゲドン') || name.includes('零龍')) {
            const slotId = name.includes('ドルマゲドン') ? 'slot-dolmagedon' : 'slot-zeroron';
            const slot = document.getElementById(slotId);
            if (slot.querySelectorAll('img').length > 0) {
                alert("既に登録されています。");
                item.remove();
                return;
            }
            slot.innerHTML = ''; 
            slot.appendChild(item);
            slot.classList.add('active');
            slot.classList.remove('empty');
            updateSaveButton(slotId, true);
            updateDeckDisplay();
            return;
        }

        if (charIds.includes('3') || charIds.includes('6')) {
            superDimList.appendChild(item);
            if (!checkLimit(name, 4, superDimList, item, 8, "超次元ゾーン")) {
                item.remove();
            }
        } else if (charIds.includes('10')) {
            grList.appendChild(item);
            if (!checkLimit(name, 2, grList, item, 12, "超GRゾーン")) {
                item.remove();
            }
        } else {
            mainList.appendChild(item);
            if (!checkLimit(name, 4, mainList, item, 60, "メインデッキ")) {
                item.remove();
            }
        }
        updateDeckDisplay();
    }
};

new Sortable(mainList, deckSortableConfig);
new Sortable(superDimList, deckSortableConfig);
new Sortable(grList, deckSortableConfig);
new Sortable(resultsDiv, { group: { name: 'shared', pull: 'clone', put: false }, sort: false, animation: 150 });
new Sortable(trashArea, { group: 'shared', onAdd: (evt) => { evt.item.remove(); updateDeckDisplay(); } });

document.querySelectorAll('.special-box').forEach(box => {
    new Sortable(box, {
        group: {
            name: 'shared',
            put: function (to, from, dragEl) {
                const cardName = dragEl.dataset.cardName || '';
                if (to.el.id === 'slot-dolmagedon') {
                    return cardName.includes('ドルマゲドン');
                }
                if (to.el.id === 'slot-zeroron') {
                    return cardName.includes('零龍');
                }
                return false;
            }
        },
        animation: 150, 
        filter: '.empty img',
        onAdd: function(evt) {
            if (evt.to.querySelectorAll('img').length > 1) {
                alert("既に登録されています。");
                evt.item.remove();
                return;
            }
            evt.to.classList.remove('empty');
            evt.to.classList.add('active');
            updateSaveButton(evt.to.id, true);
            updateDeckDisplay();
        },
        onRemove: (evt) => {
            evt.to.classList.add('empty');
            evt.to.classList.remove('active');
            updateSaveButton(evt.to.id, false);
            updateDeckDisplay();
        }
    });
});

function checkLimit(name, nameLimit, listElement, itemElement, totalLimit, zoneName) {
    const totalCount = listElement.querySelectorAll('img').length;
    if (totalCount > totalLimit) {
        alert(`${zoneName}は合計${totalLimit}枚までです。`);
        return false;
    }
    const nameCount = listElement.querySelectorAll(`img[data-card-name="${name}"]`).length;
    if (nameCount > nameLimit) {
        alert(`${name} は${zoneName}に最大${nameLimit}枚までです。`);
        return false;
    }
    return true;
}

// --- E. 共通UIロジック ---
function switchDeckTab(type) {
    document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.deck-content').forEach(c => {
        c.classList.remove('active');
        c.style.display = 'none';
    });
    
    const targetTab = document.getElementById('tab-' + type);
    const targetContent = type === 'extra' ? document.getElementById('extra-deck-area') : document.getElementById(type + '-deck-list');

    if (targetTab) targetTab.classList.add('active');
    if (targetContent) {
        targetContent.classList.add('active');
        if (type === 'extra' || type === 'special') {
            targetContent.style.display = 'flex';
        } else {
            targetContent.style.display = 'grid';
        }
    }

    if (type === 'main') {
        setTimeout(adjustMainDeckRows, 100); 
    }
}

function updateDeckDisplay() {
    if (currentSort.key) {
        sortDeckList(mainList, currentSort.key, currentSort.order);
        sortDeckList(superDimList, currentSort.key, currentSort.order);
        sortDeckList(grList, currentSort.key, currentSort.order);
    }
    const mCount = mainList.querySelectorAll('img').length;
    const gCount = grList.querySelectorAll('img').length;
    const sCount = superDimList.querySelectorAll('img').length;
    document.getElementById('tab-main').innerText = `メイン ${mCount}`;
    document.getElementById('tab-extra').innerText = `GR ${gCount}/12 超次元 ${sCount}/8`;

    adjustMainDeckRows();
}

// --- F. カード詳細モーダル ---
function openCardDetail(cardId, el) {
    activeClickedElement = el;
    fetch('/api/cards/versions?card_id=' + cardId)
        .then(res => res.json())
        .then(versions => {
            allVersions = versions;
            selectedCardData = versions.find(v => v.card_id == cardId);
            renderDetailModal();
        });
}

function renderDetailModal() {
    const modal = document.getElementById('cardDetailModal');
    const versionList = document.getElementById('detail-version-list');
    document.getElementById('detail-name').innerText = selectedCardData.card_name;
    document.getElementById('detail-text').innerText = selectedCardData.text || "効果なし";
    document.getElementById('detail-main-img').src = getCardImagePath(selectedCardData);

    const name = selectedCardData.card_name;
    let count = 0;
    
    if (name.includes('ドルマゲドン') || name.includes('零龍')) {
        const slotId = name.includes('ドルマゲドン') ? 'slot-dolmagedon' : 'slot-zeroron';
        count = document.getElementById(slotId).classList.contains('active') ? 1 : 0;
    } else {
        const type = determineZoneType(selectedCardData.char_ids);
        let targetList = type === 'super_dimensional' ? superDimList : (type === 'gr' ? grList : mainList);
        count = targetList.querySelectorAll(`img[data-card-name="${name}"]`).length;
    }
    document.getElementById('detail-qty').innerText = count;

    versionList.innerHTML = '';
    allVersions.forEach(v => {
        const vImg = document.createElement('img');
        vImg.src = getCardImagePath(v);
        if (v.card_id == selectedCardData.card_id) vImg.className = 'selected';
        vImg.onclick = () => {
            selectedCardData = v;
            if (activeClickedElement && activeClickedElement.parentNode !== resultsDiv) {
                activeClickedElement.src = vImg.src;
                activeClickedElement.dataset.cardId = v.card_id;
            }
            renderDetailModal();
        };
        versionList.appendChild(vImg);
    });
    modal.style.display = 'block';
}

function adjustQty(diff) {
    const name = selectedCardData.card_name;
    const isSpecial = name.includes('ドルマゲドン') || name.includes('零龍');

    if (isSpecial) {
        const slotId = name.includes('ドルマゲドン') ? 'slot-dolmagedon' : 'slot-zeroron';
        const slot = document.getElementById(slotId);
        
        if (diff > 0) {
            if (!slot.classList.contains('active')) addCardToDeck(selectedCardData, 'special');
        } else {
            removeSpecialCard(slotId);
            document.getElementById('detail-qty').innerText = "0";
        }
        return;
    }

    const type = determineZoneType(selectedCardData.char_ids);
    let targetList = mainList, limit = 4, totalLimit = 60, zoneName = "メインデッキ";

    if (type === 'super_dimensional') { targetList = superDimList; totalLimit = 8; zoneName = "超次元ゾーン"; }
    else if (type === 'gr') { targetList = grList; totalLimit = 12; limit = 2; zoneName = "超GRゾーン"; }

    const count = targetList.querySelectorAll(`img[data-card-name="${name}"]`).length;
    const totalCount = targetList.querySelectorAll('img').length;

    if (diff > 0) {
        if (count >= limit) return alert(`${name} は${zoneName}に最大${limit}枚までです。`);
        if (totalCount >= totalLimit) return alert(`${zoneName}は合計${totalLimit}枚までです。`);
        addCardToDeck(selectedCardData);
    } else {
        if (count <= 0) return;
        const target = targetList.querySelector(`img[data-card-name="${name}"]`);
        if (target) {
            target.remove();
            if (activeClickedElement === target) activeClickedElement = null;
        }
    }
    
    updateDeckDisplay();
    document.getElementById('detail-qty').innerText = targetList.querySelectorAll(`img[data-card-name="${name}"]`).length;
}

function closeDetailModal() { document.getElementById('cardDetailModal').style.display = 'none'; activeClickedElement = null; }

/**
 * メインデッキの重なり具合を自動調整する関数
 */
function adjustMainDeckRows() {
    const list = document.getElementById('main-deck-list');
    if (!list || !list.classList.contains('active')) return;

    list.style.height = '';
    list.style.width = '';

    const container = document.getElementById('container');
    const containerHeight = container.clientHeight;
    if (containerHeight <= 0) return;

    const parent = list.parentNode;
    const maxWidth = (parent.clientWidth || 900) - 20; 
    
    const searchSection = document.getElementById('search-section');
    const isSearchCollapsed = searchSection.classList.contains('collapsed');
    
    const paddingBottom = isSearchCollapsed ? 120 : 220;
    parent.style.paddingBottom = `${paddingBottom}px`;
    
    const excludeHeight = 35 + 40 + 10 + 20 + paddingBottom + 10;
    
    const maxContainerHeight = containerHeight - excludeHeight;
    if (maxWidth <= 0 || maxContainerHeight <= 0) return;

    const cards = list.querySelectorAll('img');
    const count = cards.length;
    const cols = 8;
    
    const h_byHeight = maxContainerHeight / 5;
    const h_byWidth = (maxWidth / cols) * (154 / 110);
    const baseCardHeight = Math.min(h_byHeight, h_byWidth);
    const cardWidth = baseCardHeight * (110 / 154);

    list.style.width = `${cardWidth * cols}px`;
    list.style.margin = '0 auto'; 

    const rows = Math.ceil(count / cols) || 1;

    if (rows <= 5) {
        const targetContainerHeight = baseCardHeight * 5;
        list.style.height = `${targetContainerHeight}px`;
        list.style.gridTemplateRows = `repeat(5, ${baseCardHeight}px)`;
        list.style.gridAutoRows = `${baseCardHeight}px`;

        cards.forEach(img => {
            img.style.height = `${baseCardHeight}px`;
            img.style.objectFit = 'contain'; 
        });
    } else {
        list.style.gridTemplateRows = 'none';
        
        const targetContainerHeight = baseCardHeight * 5;
        list.style.height = `${targetContainerHeight}px`;

        cards.forEach(img => {
            img.style.height = `${baseCardHeight}px`;
            img.style.objectFit = 'contain';
        });

        const availableHeight = targetContainerHeight - baseCardHeight - 2;
        let rowHeight = availableHeight / (rows - 1);

        list.style.gridAutoRows = `${rowHeight}px`;
    }
}

window.addEventListener('resize', adjustMainDeckRows);

/**
 * 検索セクションの開閉（アコーディオン）を切り替える
 */
function toggleSearchSection() {
    const searchSection = document.getElementById('search-section');
    const btn = document.getElementById('search-toggle-btn');
    
    const isCollapsed = searchSection.classList.toggle('collapsed');
    
    if (isCollapsed) {
        btn.innerHTML = '▲ カード検索を開く';
    } else {
        btn.innerHTML = '▼ 検索を隠す';
    }
    
    adjustMainDeckRows();
    setTimeout(adjustMainDeckRows, 300);
}


// --- G. 検索・無限スクロール ---
input.addEventListener('input', () => {
    currentFilters.q = input.value;
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(searchCards, 300);
});

document.querySelectorAll('.scope-check').forEach(el => {
    el.onchange = () => {
        currentFilters.scope = Array.from(document.querySelectorAll('.scope-check:checked')).map(c => c.value);
        searchCards(); 
    };
});

function searchCards() {
    if (abortController) abortController.abort();
    isFetching = false; currentOffset = 0; hasMoreCards = true; resultsDiv.innerHTML = '';
    
    const isEmpty = !currentFilters.q && !currentFilters.civs.length && !currentFilters.cost_min && !currentFilters.cost_max && 
                    !currentFilters.pow_min && !currentFilters.pow_max && !currentFilters.races.length && !currentFilters.abilities.length && !currentFilters.reg.length;

    if (isEmpty) return;
    fetchAndRender();
}

function loadMoreCards() { 
    if (isFetching || !hasMoreCards) return; 
    currentOffset += 50; 
    fetchAndRender(); 
}

function fetchAndRender() {
    if (isFetching) return;
    abortController = new AbortController();
    isFetching = true;
    if (currentOffset === 0) resultsDiv.innerHTML = '<div class="search-msg">検索中...</div>';

    const p = new URLSearchParams();
    if (currentFilters.q) p.append('q', currentFilters.q);
    p.append('scope', currentFilters.scope.join(',')); 
    if (currentFilters.civs.length) {
        p.append('civs', currentFilters.civs.join(','));
        p.append('civ_match_type', currentFilters.civ_match_type);
    };
    if (currentFilters.civ_type) {
        p.append('civ_type', currentFilters.civ_type);
    }   
    if (currentFilters.exclude_civs.length) {
        p.append('exclude_civs', currentFilters.exclude_civs.join(','));
    }  
    if (currentFilters.cost_min) p.append('cost_min', currentFilters.cost_min);
    if (currentFilters.cost_max) p.append('cost_max', currentFilters.cost_max);
    if (currentFilters.pow_min) p.append('pow_min', currentFilters.pow_min);
    if (currentFilters.pow_max) p.append('pow_max', currentFilters.pow_max);
    if (currentFilters.races.length) p.append('races', currentFilters.races.join(','));
    p.append('race_logic', currentFilters.race_logic);
    if (currentFilters.abilities.length) p.append('abilities', currentFilters.abilities.join(','));
    p.append('ability_logic', currentFilters.ability_logic);
    if (currentFilters.reg.length) p.append('reg', currentFilters.reg.join(','));
    p.append('offset', currentOffset);

    fetch('/api/cards?' + p.toString(), { signal: abortController.signal })
        .then(res => res.json())
        .then(data => {
            if (currentOffset === 0) resultsDiv.innerHTML = '';
            if (data.length === 0 && currentOffset === 0) {
                resultsDiv.innerHTML = '<div class="search-msg">検索条件に該当するカードがみつかりませんでした。</div>';
                hasMoreCards = false;
            } else {
                if (data.length < 50) hasMoreCards = false;
                data.forEach(card => {
                    const img = document.createElement('img');
                    img.src = getCardImagePath(card);
                    img.dataset.cardId = card.card_id;
                    img.dataset.cardName = card.card_name;
                    img.dataset.charIds = card.char_ids;
                    img.dataset.imagepath = card.imagepath;
                    // 並び替え用に文明とコストの情報を属性として保存
                    img.dataset.civ = card.civilization || card.civ || '';
                    img.dataset.cost = card.cost !== undefined ? card.cost : '';
                    img.onerror = () => handleImageError(img);
                    resultsDiv.appendChild(img);
                });
            }
        })
        .catch(err => { if (err.name !== 'AbortError') console.error(err); })
        .finally(() => { isFetching = false; });
}

resultsDiv.onscroll = () => { 
    if (resultsDiv.scrollLeft + resultsDiv.clientWidth >= resultsDiv.scrollWidth - 100) {
        loadMoreCards();
    }
};

// --- G. 絞り込みモーダル制御 ---
function toggleFilterModal() { 
    const m = document.getElementById('filterModal'); 
    m.style.display = (m.style.display === 'block') ? 'none' : 'block'; 
}

function openSubModal(type) { document.getElementById(`${type}SelectModal`).style.display = 'block'; }
function closeSubModal(type) { document.getElementById(`${type}SelectModal`).style.display = 'none'; }

function renderMasterList(type, list) {
    const container = document.getElementById(`${type}-list-container`);
    container.innerHTML = '';
    list.forEach(item => {
        const id = item[`${type}_id`], name = item[`${type}_name`];
        const div = document.createElement('label');
        div.className = 'list-item';
        div.dataset.search = (name + (item.reading || '')).toLowerCase();
        div.innerHTML = `<span>${name}</span><input type="checkbox" class="${type}-check" value="${id}" data-name="${name}" onchange="updateTriggerText('${type}')">`;
        container.appendChild(div);
    });
}

['race', 'ability'].forEach(type => {
    document.getElementById(`${type}-list-search`).addEventListener('input', function() {
        const q = this.value.toLowerCase();
        document.querySelectorAll(`#${type}-list-container .list-item`).forEach(el => {
            el.style.display = el.dataset.search.includes(q) ? 'flex' : 'none';
        });
    });
});

function updateTriggerText(type) {
    const checked = Array.from(document.querySelectorAll(`.${type}-check:checked`));
    const trigger = document.getElementById(`${type}-trigger`);
    trigger.innerText = checked.length > 0 ? checked.map(el => el.dataset.name).join(', ') : '選択';
    trigger.style.color = checked.length > 0 ? '#333' : '#666';
}

function clearSubSelection(type) {
    document.querySelectorAll(`.${type}-check`).forEach(el => el.checked = false);
    updateTriggerText(type);
}

/**
 * ★ 新設：文明の「単色/多色」の変更による除外エリアの表示制御
 */
function toggleFilterCivType() {
    const isMulti = document.getElementById('filter-civ-multi').checked;
    const excludeArea = document.getElementById('filter-exclude-civ-area');
    excludeArea.style.display = isMulti ? 'flex' : 'none';
}

function applyFilters() {
    currentFilters.civs = Array.from(document.querySelectorAll('.civ-check:checked')).map(el => el.value);
    
    // ★ 追加：新しい文明フィルター情報の取得
    const singleChecked = document.getElementById('filter-civ-single').checked;
    const multiChecked = document.getElementById('filter-civ-multi').checked;
    if (singleChecked && !multiChecked) currentFilters.civ_type = 'single';
    else if (!singleChecked && multiChecked) currentFilters.civ_type = 'multi';
    else if (!singleChecked && !multiChecked) currentFilters.civ_type = 'none';
    else currentFilters.civ_type = ''; // 両方ON

    currentFilters.civ_match_type = document.querySelector('input[name="filter-civ-match-type"]:checked').value;
    currentFilters.exclude_civs = Array.from(document.querySelectorAll('.filter-exclude-civ-check:checked')).map(el => el.value);

    currentFilters.cost_min = document.getElementById('cost-min').value;
    currentFilters.cost_max = document.getElementById('cost-max').value;
    currentFilters.pow_min = document.getElementById('pow-min').value;
    currentFilters.pow_max = document.getElementById('pow-max').value;
    currentFilters.races = Array.from(document.querySelectorAll('.race-check:checked')).map(el => el.value);
    currentFilters.race_logic = document.querySelector('input[name="race_logic"]:checked').value;
    currentFilters.abilities = Array.from(document.querySelectorAll('.ability-check:checked')).map(el => el.value);
    currentFilters.ability_logic = document.querySelector('input[name="ability_logic"]:checked').value;
    currentFilters.reg = Array.from(document.querySelectorAll('.reg-check:checked')).map(el => el.value);
    
    toggleFilterModal();
    searchCards();
}

function clearAllFilters() {
    document.querySelectorAll('#filterModal input[type="checkbox"], #filterModal input[type="number"]').forEach(el => {
        el.checked = false;
        el.value = '';
    });
    
    // ★ 追加：文明初期状態の復元
    document.getElementById('filter-civ-single').checked = true;
    document.getElementById('filter-civ-multi').checked = true;
    document.querySelector('input[name="filter-civ-match-type"][value="include"]').checked = true;
    toggleFilterCivType();

    input.value = '';
    currentFilters = { 
        q: '', scope: ['name'], civs: [], cost_min: '', cost_max: '', 
        pow_min: '', pow_max: '', races: [], abilities: [], 
        race_logic: 'OR', ability_logic: 'OR', reg: [],
        // ★ 追加
        civ_type: '', civ_match_type: 'include', exclude_civs: []
    };
    clearSubSelection('race');
    clearSubSelection('ability');
    searchCards();
}


// --- H. デッキ保存・設定モーダル制御と保存処理 ---

function openSaveModal() {
    const mainCardsCount = mainList.querySelectorAll('img').length;
    const extraCardsCount = grList.querySelectorAll('img').length + superDimList.querySelectorAll('img').length;
    const specialCardsCount = document.querySelectorAll('.special-box.active img').length;
    
    if (mainCardsCount === 0 && extraCardsCount === 0 && specialCardsCount === 0) {
        return alert("カードを1枚以上入れてください。");
    }

    const nameInput = document.getElementById('save-deck-name');
    if (!nameInput.value) {
        nameInput.value = isEdit ? initialDeckName : "マイデッキ";
    }

    // デフォルトサムネイルの自動設定
    const currentThumbId = document.getElementById('save-deck-thumbnail-id').value;
    if (!currentThumbId) {
        const firstCard = mainList.querySelector('img') || grList.querySelector('img') || superDimList.querySelector('img') || document.querySelector('.special-box.active img');
        if (firstCard) {
            setThumbnail(firstCard.dataset.cardId, firstCard.dataset.imagepath);
        }
    }

    document.getElementById('deckSaveModal').style.display = 'block';
}

function closeSaveModal() {
    document.getElementById('deckSaveModal').style.display = 'none';
}

function openThumbnailSelector() {
    const container = document.getElementById('thumbnail-cards-list');
    container.innerHTML = '';

    const selectors = [
        '#main-deck-list img',
        '#gr-list img',
        '#super-dim-list img',
        '.special-box.active img'
    ];
    const cards = document.querySelectorAll(selectors.join(','));
    
    const seenIds = new Set();
    const uniqueCards = [];
    cards.forEach(img => {
        const id = img.dataset.cardId;
        if (!seenIds.has(id)) {
            seenIds.add(id);
            uniqueCards.push({
                card_id: id,
                card_name: img.dataset.cardName,
                imagepath: img.dataset.imagepath
            });
        }
    });

    if (uniqueCards.length === 0) {
        alert("デッキ内に選択可能なカードがありません。");
        return;
    }

    uniqueCards.forEach(card => {
        const img = document.createElement('img');
        img.src = getCardImagePath(card);
        img.alt = card.card_name;
        img.style.width = '100%';
        img.style.cursor = 'pointer';
        img.style.borderRadius = '4px';
        img.style.border = '2px solid transparent';
        
        if (card.card_id === document.getElementById('save-deck-thumbnail-id').value) {
            img.style.borderColor = '#007bff';
        }

        img.onclick = () => {
            setThumbnail(card.card_id, card.imagepath);
            closeThumbnailSelector();
        };
        img.onerror = () => handleImageError(img);
        container.appendChild(img);
    });

    document.getElementById('thumbnailSelectModal').style.display = 'block';
}

function closeThumbnailSelector() {
    document.getElementById('thumbnailSelectModal').style.display = 'none';
}

function setThumbnail(cardId, imagepath) {
    document.getElementById('save-deck-thumbnail-id').value = cardId;
    const imgEl = document.getElementById('thumbnail-preview-img');
    const placeholder = document.getElementById('thumbnail-placeholder');
    
    imgEl.src = getCardImagePath({ imagepath: imagepath });
    imgEl.style.display = 'block';
    placeholder.style.display = 'none';
}

function submitDeckSave() {
    const name = document.getElementById('save-deck-name').value.trim();
    const formatSelect = document.getElementById('save-deck-format');
    const formatId = formatSelect ? formatSelect.value : null;
    const thumbnailCardId = document.getElementById('save-deck-thumbnail-id').value;

    const isPublic = document.getElementById('save-deck-public').checked ? 1 : 0;

    if (!name) {
        return alert("デッキ名を入力してください。");
    }
    // ★ フォーマットの選択バリデーションを追加
    if (!formatId || isNaN(parseInt(formatId, 10))) {
        return alert("有効なフォーマットを選択してください。");
    }
    
    const mainCards = Array.from(mainList.querySelectorAll('img')).map(img => ({ id: img.dataset.cardId, type: 'main' }));
    const superDimCards = Array.from(superDimList.querySelectorAll('img')).map(img => ({ id: img.dataset.cardId, type: 'super_dimensional' }));
    const grCards = Array.from(grList.querySelectorAll('img')).map(img => ({ id: img.dataset.cardId, type: 'gr' }));
    const specialCards = Array.from(document.querySelectorAll('.special-box.active img')).map(img => ({ id: img.dataset.cardId, type: 'special' }));

    const allCards = [...mainCards, ...superDimCards, ...grCards, ...specialCards];    
    if (!allCards.length) return alert("カードを1枚以上入れてください。");

    fetch('/api/decks', {
        method: isEdit ? 'PUT' : 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ 
            deck_id: deckId, 
            deck_name: name, 
            cards: allCards, 
            format_id: parseInt(formatId, 10),
            thumbnail_card_id: thumbnailCardId ? parseInt(thumbnailCardId, 10) : null,
            is_public: isPublic
        })
    })
    .then(res => res.text().then(text => {
        try { return JSON.parse(text); } 
        catch (e) { throw new Error("サーバー側でエラーが発生しました。"); }
    }))
    .then(data => {
        if (data.success) { 
            alert("保存が完了しました！"); 
            window.location.href = '/mydecks'; 
        } else { 
            alert("保存に失敗しました: " + data.error); 
        }
    })
    .catch(err => alert(err.message));
}


// --- 特殊スロットの操作ロジック ---
function toggleSpecial(slotId) {
    const slot = document.getElementById(slotId);
    if (slot.classList.contains('active')) removeSpecialCard(slotId);
    else fetchSpecialCard(slotId);
}

function fetchSpecialCard(slotId) {
    const cardName = slotId === 'slot-dolmagedon' ? '終焉の禁断 ドルマゲドンX' : '零龍';
    fetch(`/api/cards?q=${encodeURIComponent(cardName)}&limit=1`)
        .then(res => res.json())
        .then(data => {
            if (data.length > 0) {
                const slot = document.getElementById(slotId);
                slot.innerHTML = '';
                addCardToDeck(data[0], 'special');
            }
        });
}

function removeSpecialCard(slotId) {
    const slot = document.getElementById(slotId);
    slot.innerHTML = '';
    slot.classList.remove('active');
    slot.classList.add('empty');
    updateSaveButton(slotId, false);
    updateDeckDisplay();
}

function updateSaveButton(slotId, isActive) {
    const btn = document.getElementById(slotId === 'slot-dolmagedon' ? 'btn-doru' : 'btn-zero');
    if (btn) {
        btn.innerText = isActive ? "削除する" : "追加する";
        btn.style.backgroundColor = isActive ? "#dc3545" : "#007bff";
    }
}

// --- 並び替えモーダル制御とソートロジック ---
function toggleSortModal() {
    const m = document.getElementById('sortModal');
    m.style.display = (m.style.display === 'block') ? 'none' : 'block';
}

function applySort() {
    const key = document.getElementById('sort-key').value;
    const order = document.querySelector('input[name="sort-order"]:checked').value;
    const selectedKey = document.getElementById('sort-key').value;
    // 各カードリストを並び替え
    sortDeckList(mainList, key, order);
    sortDeckList(superDimList, key, order);
    sortDeckList(grList, key, order);

    if (selectedKey === 'free') {
        currentSort.key = null; // 自動整列を解除
    } else {
        currentSort.key = selectedKey;
        currentSort.order = document.querySelector('input[name="sort-order"]:checked').value;
    }
    
    updateDeckDisplay();
    toggleSortModal();
}

/**
 * 文明情報をソート用の数値（重み）に変換する
 */
function getCivWeight(civStr) {
    if (!civStr) return 999;
    // カンマ区切りの数値をパース
    const parts = civStr.toString().split(',').map(Number).filter(n => !isNaN(n)).sort((a, b) => a - b);
    if (parts.length === 0) return 999;
    if (parts.length === 1) return parts[0]; // 単色は1〜6の値
    
    // 多色は単色の後に並ぶように値を調整
    let weight = 10 * parts.length;
    parts.forEach((p, idx) => {
        weight += p * Math.pow(0.1, idx + 1);
    });
    return weight;
}

/**
 * キーに応じた2枚のカードの比較値を取得
 */
function compareCardsByKey(a, b, key) {
    if (key === 'civ') {
        const valA = getCivWeight(a.dataset.civ);
        const valB = getCivWeight(b.dataset.civ);
        return valA - valB;
    }
    if (key === 'cost') {
        const valA = parseInt(a.dataset.cost, 10) || 0;
        const valB = parseInt(b.dataset.cost, 10) || 0;
        return valA - valB;
    }
    if (key === 'name') {
        const valA = a.dataset.cardName || '';
        const valB = b.dataset.cardName || '';
        return valA.localeCompare(valB, 'ja');
    }
    return 0;
}

/**
 * 対象のカードリストを指定された条件で並び替えてDOMを再配置
 */
function sortDeckList(listElement, key, order) {
    const imgs = Array.from(listElement.querySelectorAll('img'));
    if (imgs.length === 0) return;

    // 同名カード数順ソート用の枚数マップを作成
    const counts = {};
    if (key === 'count') {
        imgs.forEach(img => {
            const name = img.dataset.cardName;
            counts[name] = (counts[name] || 0) + 1;
        });
    }

    const direction = order === 'asc' ? 1 : -1;

    imgs.sort((a, b) => {
        let res = 0;
        if (key === 'count') {
            const countA = counts[a.dataset.cardName] || 0;
            const countB = counts[b.dataset.cardName] || 0;
            res = countA - countB;
        } else {
            res = compareCardsByKey(a, b, key);
        }

        // メインのソート基準が異なる場合は、指定方向（昇順 / 降順）に従う
        if (res !== 0) return res * direction;

        // メイン基準が同じだった場合のタイブレーク（サブソート）。こちらは常に昇順固定にすることで、規則的な整列を維持します
        if (key !== 'civ') {
            res = compareCardsByKey(a, b, 'civ');
            if (res !== 0) return res;
        }
        if (key !== 'cost') {
            res = compareCardsByKey(a, b, 'cost');
            if (res !== 0) return res;
        }
        if (key !== 'name') {
            res = compareCardsByKey(a, b, 'name');
            if (res !== 0) return res;
        }
        return 0;
    });

    // ソート順に沿ってDOMを再登録する
    imgs.forEach(img => listElement.appendChild(img));
}
</script>