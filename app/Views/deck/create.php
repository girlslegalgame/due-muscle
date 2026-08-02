<!-- app/Views/deck/create.php -->
<style>
    body {
        margin: 0;
        padding: 0;
        height: 100vh;
        overflow: hidden; /* body自体にスクロールバーを出さない */
    }

    /* 
      親レイアウトのmainタグの設定を上書き同期します。
    */
    main {
        padding-top: 75px !important;    
        padding-bottom: 20px !important; 
        height: 100vh;
        box-sizing: border-box;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
    }

    /* --- 1. 全体のコンテナ（mainの逃げ幅(75px)を引いた内寸いっぱいに完璧にフィット） --- */
    #container { 
        width: 100%; 
        max-width: 1280px; 
        height: 100%;      /* ★ mainのpaddingを除いた内寸100%いっぱいにフィットさせます */
        max-height: 100%; 
        margin: 0 auto;    /* ★ main自体がすでに上を避けているため、余分な margin-top は 0（不要）になります */
        display: flex; 
        flex-direction: row; 
        box-sizing: border-box;
        position: relative; 
        overflow: hidden;
        background-color: #fff; 
        box-shadow: 0 0 25px rgba(0,0,0,0.1); 
    }


    /* 左利き配置切り替え時 */
    #container.left-handed {
        flex-direction: row-reverse;
    }

    /* --- 2. デッキ作成エリア --- */
    #deck-area { 
        flex: 1; 
        min-width: 0; 
        min-height: 0; 
        display: flex; 
        flex-direction: column; 
        padding: 15px; 
        padding-bottom: 15px; 
        box-sizing: border-box;
        overflow-y: auto;
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
        margin-bottom: 20px !important; 
    }
    #main-deck-list img { 
        width: 100%; 
        height: 100%; 
        aspect-ratio: 110/154; 
        object-fit: contain; 
        cursor: pointer; 
        display: block; 
    }
    #main-deck-list.stacked-mode { grid-auto-rows: 45px; }

    /* --- 3. 超次元・GRエリア --- */
    #extra-deck-area { 
        display: none; 
        flex-direction: row; 
        gap: 32px; 
        padding: 20px; 
        border: 1px solid #ccc; 
        background: #f9f9f9; 
        width: 100%;
        box-sizing: border-box;
        justify-content: center; 
        align-items: flex-start;
    }
    #extra-deck-area.active { display: flex !important; }
    
    .zone-container { 
        display: flex; 
        flex-direction: column; 
        align-items: center; 
        width: 100%;
        max-width: 464px; 
        min-width: 0; 
        flex: 0 1 auto; 
    }
    .zone-container h4 {
        width: 100%;
        text-align: center;
        font-size: 1.1rem;
        margin: 0 0 10px 0;
        padding-bottom: 5px;
    }

    /* 共通可変リスト設定 */
    .extra-list { 
        display: grid !important; 
        grid-template-columns: repeat(4, 1fr) !important; 
        gap: 4px; 
        background: #fff; 
        border: 1px solid #ddd; 
        padding: 6px; 
        box-sizing: border-box;
        width: 100%; 
    }
    .extra-list img { 
        width: 100% !important; 
        height: auto !important; 
        aspect-ratio: 110/154; 
        object-fit: contain; 
        border-radius: 6px; 
        display: block;
    }

    /* 超GRリスト (最初から縦3×横4枠を維持) */
    #gr-list {
        width: 100%;
        aspect-ratio: 464 / 482; 
    }

    /* 超次元リスト (最初から縦2×横4枠を維持) */
    #super-dim-list {
        width: 100%;
        aspect-ratio: 464 / 324; 
    }

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
    
    /* 修正：幅に合わせて比率を維持したまま可変するよう変更 */
    .special-box {
        width: 80%;                   /* ゾーン幅に対して可変（必要に応じて 100% に近い値に調整してください） */
        max-width: 280px;             /* 拡大しすぎるのを防ぐ上限値 */
        aspect-ratio: 110 / 154;      /* カードの縦横比率を維持 */
        height: auto;                 /* 固定高さ（390px）を解除 */
        border: 2px dashed #bbb;
        display: flex; justify-content: center; align-items: center;
        border-radius: 8px; background: #fff; overflow: hidden;
        box-sizing: border-box;
    }
    .special-box img {
        width: 100%;
        height: 100%;
        object-fit: contain; 
        cursor: pointer;
    }
    .special-box.active { border: 2px solid #28a745; }
    /* --- 5. 検索セクション（右側固定・320px） --- */
    #search-section {
        position: relative;      
        width: 380px;            /* ★ 320px から 380px へ変更 */
        min-width: 380px;        /* ★ 320px から 380px へ変更 */
        height: 100%;        
        box-sizing: border-box;
        background: rgba(249, 249, 249, 0.98); 
        border-left: 3px solid #333; 
        box-shadow: -5px 0 20px rgba(0, 0, 0, 0.15); 
        padding: 10px; 
        display: flex; 
        flex-direction: column; 
        gap: 8px;
        transition: width 0.3s ease, min-width 0.3s ease, padding 0.3s ease;
    }

    #container.left-handed #search-section {
        border-left: none;
        border-right: 3px solid #333;
        box-shadow: 5px 0 20px rgba(0, 0, 0, 0.15);
    }

    #search-section.collapsed {
        width: 0px !important;
        min-width: 0px !important;
        padding: 0px !important;
        border: none !important;
        overflow: hidden;
    }

    /* 開閉トグルボタン */
    #search-toggle-btn {
        position: absolute;
        top: 50%;
        right: 380px; 
        transform: translateY(-50%);
        width: 32px;
        height: 64px;
        padding: 0;
        background: #333;
        color: #fff;
        border: none;
        border-radius: 8px 0 0 8px;
        cursor: pointer;
        font-weight: bold;
        font-size: 11px;
        display: flex;
        align-items: center;
        justify-content: center;
        writing-mode: vertical-rl; 
        text-orientation: mixed;
        box-shadow: -3px 0 10px rgba(0,0,0,0.15);
        z-index: 2001; 
        transition: right 0.3s ease, left 0.3s ease;
    }
    #search-toggle-btn:hover {
        background: #444;
    }

    /* 検索結果（縦スクロール・横2列グリッド） */
    #search-results { 
        flex: 1; 
        display: grid; 
        grid-template-columns: repeat(2, 1fr); 
        gap: 8px; 
        padding: 5px 0; 
        overflow-y: auto; 
        overflow-x: hidden;
        align-content: start;
    }
    #search-results img { 
        width: 100%; 
        height: auto; 
        aspect-ratio: 110/154; 
        object-fit: contain; 
        border: 1px solid #ccc; 
        border-radius: 6px;
        box-sizing: border-box;
        background-color: #fff; 
        transition: transform 0.15s ease;
    }
    #search-results img:hover {
        transform: scale(1.03);
    }
    
    /* 検索コントロール */
    #search-controls-wrapper { 
        display: flex; 
        flex-direction: column; 
        gap: 8px; 
        border-bottom: 1px solid #ddd;
        padding-bottom: 10px;
    }
    #search-controls { 
        display: flex; 
        flex-direction: row; /* 縦から横並びに変更 */
        align-items: center;
        gap: 6px; 
        height: auto; 
    }
    #search-buttons-row {
        display: flex;
        gap: 4px;
        flex-shrink: 0;
    }
    #search-buttons-row button {
        flex: 1;
    }

    #card-search-input { 
        flex: 1; 
        min-width: 0; /* 横幅縮小時の潰れ防止 */
        padding: 10px 8px; 
        border: 1px solid #ccc; 
        border-radius: 4px; 
        font-size: 14px; 
    }
    .btn-filter, .btn-sort, .btn-analysis { 
        padding: 10px 8px; 
        color: white; 
        border: none; 
        border-radius: 4px; 
        cursor: pointer; 
        font-weight: bold; 
        font-size: 13px;
        box-sizing: border-box;
        text-align: center;
    }
    .btn-filter { background: #17a2b8; }
    .btn-sort { background: #6c757d; }
    .btn-analysis { background: #28a745; }
    .btn-analysis:hover { background: #218838; }
    
    /* 検索範囲（チェックボックス）を左寄せに変更 */
    .search-scope { 
        display: flex; 
        gap: 12px;               /* 各項目の間隔に少し余裕を持たせます */
        font-size: 12px; 
        color: #666; 
        justify-content: flex-start; 
        padding-left: 2px;
        align-items: center;     
        flex-wrap: nowrap;       /* 親要素の改行を禁止 */
    }
    /* 利き手スイッチ */
    .hand-switch-btn {
        background: #e2e8f0;
        border: 1px solid #cbd5e1;
        color: #475569;
        padding: 4px 8px;        /* ★ 6px 12px から 4px 8px にしてコンパクトにします */
        font-size: 11px;         /* ★ 12px から 11px に微減 */
        border-radius: 4px;
        cursor: pointer;
        font-weight: bold;
        white-space: nowrap;     /* ★ ボタン内の文字が折り返すのを防ぎます */
    }
    .hand-switch-btn:hover {
        background: #cbd5e1;
    }

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
    .detail-grid { 
        display: grid; 
        grid-template-columns: 280px 1fr; 
        grid-template-rows: auto auto 1fr; 
        gap: 0 25px; 
        margin-bottom: 20px; 
    }
    #detail-name { 
        grid-column: 2 / 3; 
        grid-row: 1 / 2; 
        margin: 0 0 15px 0; 
        font-size: 1.5rem; 
        border-bottom: 2px solid #eee; 
        padding-bottom: 10px; 
    }
    .detail-image { 
        grid-column: 1 / 2; 
        grid-row: 1 / 3; 
        text-align: center; 
    }
    .detail-image img { 
        width: 100%; 
        border-radius: 8px; 
        box-shadow: 0 4px 10px rgba(0,0,0,0.2); 
    }
    .detail-qty { 
        grid-column: 1 / 2; 
        grid-row: 3 / 4; 
    }
    .detail-desc { 
        grid-column: 2 / 3; 
        grid-row: 2 / 4; 
        min-width: 0; 
    }
    #detail-text { 
        white-space: pre-wrap; 
        word-wrap: break-word; 
        background: #f8f9fa; 
        padding: 15px; 
        border-radius: 5px; 
        font-size: 0.95rem; 
        line-height: 1.6; 
        max-height: 250px; 
        overflow-y: auto; 
    }
    .version-list { display: flex; overflow-x: auto; gap: 12px; padding: 10px 5px; }
    .version-list img { height: 100px; cursor: pointer; border: 3px solid transparent; border-radius: 4px; flex-shrink: 0; }
    .version-list img.selected { border-color: #007bff; }
    .qty-controls { display: flex; align-items: center; gap: 20px; margin: 20px 0; justify-content: center; font-size: 1.2rem; }
    .btn-qty { width: 40px; height: 40px; border-radius: 50%; border: 1px solid #ccc; cursor: pointer; font-size: 1.5rem; background: #fff; }
    .detail-close-btn { position: absolute; right: 20px; top: 15px; cursor: pointer; font-size: 30px; z-index: 10; line-height: 1; }

    /* 絞り込みモーダル */
    #filterModal {
        display: none;
        align-items: center;
        justify-content: center;
    }
    #filterModal[style*="display: block"] {
        display: flex !important;
    }
    .filter-content { 
        width: 100%;
        max-width: 500px; 
        background: #fff;
        border-radius: 16px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        display: flex;
        flex-direction: column;
        max-height: 85vh;
        border: none;
        margin: 0;
        overflow: hidden;       
        padding: 0 !important;  
    }
    .filter-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: #333;       
        padding: 16px 20px;     
        margin-bottom: 0;
    }
    .filter-header h3 {
        margin: 0;
        font-size: 0.95rem;
        color: #fff;            
        font-weight: bold;
    }
    .filter-close {
        font-size: 24px;
        color: #ccc;            
        cursor: pointer;
        line-height: 1;
        transition: color 0.2s;
    }
    .filter-close:hover {
        color: #fff;
    }
    .filter-scroll { 
        flex: 1;
        overflow-y: auto; 
        padding: 20px 20px 0 20px; 
        margin-bottom: 0;
    }
    .filter-scroll::-webkit-scrollbar {
        width: 6px;
    }
    .filter-scroll::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 10px;
    }
    .filter-scroll::-webkit-scrollbar-thumb {
        background: #ccc;
        border-radius: 10px;
    }

    .filter-group {
        margin-bottom: 20px;
    }
    .filter-group > label {
        display: block;
        font-size: 0.85rem;
        font-weight: 600;
        color: #444;
        margin-bottom: 8px;
    }

    /* 文明チップス */
    .civ-checkboxes, #filter-exclude-civ-area { 
        display: grid; 
        grid-template-columns: repeat(3, 1fr); 
        gap: 8px; 
    }
    .civ-checkboxes {
        margin-bottom: 12px;
    }
    .civ-checkboxes label, #filter-exclude-civ-area label {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        padding: 8px 4px;
        background: #f5f5f7;
        border: 1px solid #e5e5ea;
        border-radius: 8px;
        font-size: 0.8rem;
        cursor: pointer;
        transition: all 0.2s;
        margin: 0;
        font-weight: normal;
        color: #555;
    }
    .civ-checkboxes input, #filter-exclude-civ-area input {
        margin: 0;
    }
    .civ-checkboxes label:has(input:checked) {
        background: #e6f2ff;
        border-color: #007bff;
        color: #007bff;
        font-weight: bold;
    }
    #filter-exclude-civ-area label:has(input:checked) {
        background: #ffebe6;
        border-color: #ff3b30;
        color: #ff3b30;
        font-weight: bold;
    }

    /* 範囲指定 */
    .range-inputs {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .range-inputs input[type="number"] {
        flex: 1;
        padding: 8px 12px;
        border: 1px solid #d1d1d6;
        border-radius: 8px;
        font-size: 0.9rem;
        outline: none;
        box-sizing: border-box;
        transition: border-color 0.2s;
    }
    .range-inputs input[type="number"]:focus {
        border-color: #007bff;
    }
    .range-separator {
        color: #8e8e93;
        font-size: 0.9rem;
    }

    /* セレクトトリガー */
    .select-trigger { 
        width: 100% !important; 
        padding: 10px 14px; 
        border: 1px solid #d1d1d6; 
        border-radius: 8px; 
        cursor: pointer; 
        background: #fff; 
        box-sizing: border-box; 
        min-height: 40px; 
        font-size: 0.85rem; 
        color: #333; 
        overflow: hidden; 
        text-overflow: ellipsis; 
        white-space: nowrap;
        transition: all 0.2s;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .select-trigger:hover {
        border-color: #007bff;
        background: #fafafa;
    }
    .select-trigger::after {
        content: "▼";
        font-size: 10px;
        color: #8e8e93;
    }

    /* AND / OR 切り替え */
    .logic-switch { 
        display: inline-flex; 
        border: 1px solid #d1d1d6; 
        border-radius: 8px; 
        overflow: hidden; 
        background: #f2f2f7; 
        padding: 2px;
    }
    .logic-switch label { 
        text-align: center; 
        cursor: pointer; 
        font-size: 0.75rem; 
        font-weight: bold;
        margin: 0; 
        padding: 4px 12px; 
        border-radius: 6px;
        transition: all 0.2s;
        color: #666;
    }
    .logic-switch input { display: none; }
    .logic-switch input:checked + span { 
        background: #fff; 
        color: #007bff; 
        box-shadow: 0 1px 3px rgba(0,0,0,0.15);
        display: block; 
        padding: 4px 12px;
        border-radius: 6px;
        margin: -4px -12px; 
    }

    /* 下部アクション */
    .filter-actions {
        display: flex;
        gap: 12px;
        padding: 15px 20px 20px 20px; 
        background: #fff;
    }
    .btn-filter-apply {
        flex: 2;
        padding: 12px;
        background: #007bff;
        color: white;
        border: none;
        border-radius: 10px;
        font-weight: bold;
        font-size: 0.9rem;
        cursor: pointer;
        transition: background 0.2s;
    }
    .btn-filter-apply:hover {
        background: #0056b3;
    }
    .btn-filter-clear {
        flex: 1;
        padding: 12px;
        background: #f2f2f7;
        color: #ff3b30;
        border: none;
        border-radius: 10px;
        font-weight: bold;
        font-size: 0.9rem;
        cursor: pointer;
        transition: all 0.2s;
    }
    .btn-filter-clear:hover {
        background: #ffebe6;
    }  
    
    /* --- サブモーダル --- */
    .sub-modal { 
        display: none; 
        position: fixed; 
        z-index: 3000; 
        left: 0; top: 0; 
        width: 100%; height: 100%; 
        background: rgba(0,0,0,0.8); 
        align-items: center;
        justify-content: center;
    }
    .sub-modal[style*="display: block"] {
        display: flex !important;
    }
    .sub-modal-content { 
        background: white; 
        width: 100%;
        max-width: 400px; 
        height: 75vh; 
        border-radius: 16px; 
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        display: flex; 
        flex-direction: column; 
        box-sizing: border-box;
        overflow: hidden; 
        margin: 0;
        padding: 0 !important;  
    }
    .sub-modal-header { 
        padding: 16px 20px; 
        background: #333; 
        color: #fff; 
        display: flex; 
        justify-content: space-between; 
        align-items: center;
    }
    .sub-modal-header span {
        font-weight: bold;
        font-size: 0.95rem;
    }
    .sub-modal-body { 
        flex: 1; 
        overflow-y: auto; 
        padding: 10px 20px; 
    }
    .sub-modal-content input[type="text"] { 
        width: 100% !important; 
        box-sizing: border-box; 
        padding: 10px 14px; 
        margin-bottom: 5px; 
        border: 1px solid #d1d1d6; 
        border-radius: 8px; 
        font-size: 0.9rem;
        outline: none;
        transition: border-color 0.2s;
    }
    .sub-modal-content input[type="text"]:focus {
        border-color: #007bff;
    }
    .list-item { 
        display: flex; 
        align-items: center; 
        justify-content: space-between; 
        padding: 12px 8px; 
        border-bottom: 1px solid #f2f2f7; 
        cursor: pointer; 
        font-size: 0.85rem; 
        color: #333;
        transition: background 0.2s;
    }
    .list-item:hover {
        background: #f5f5f7;
    }
    .list-item input[type="checkbox"] {
        margin: 0;
        cursor: pointer;
    }

    /* トグルスイッチ */
    .toggle-switch {
        position: relative;
        display: inline-block;
        width: 44px;
        height: 24px;
    }
    .toggle-switch input {
        opacity: 0;
        width: 0;
        height: 0;
    }
    .toggle-slider {
        position: absolute;
        cursor: pointer;
        top: 0; left: 0; right: 0; bottom: 0;
        background-color: #d1d1d6;
        transition: .3s;
        border-radius: 24px;
    }
    .toggle-slider:before {
        position: absolute;
        content: "";
        height: 18px;
        width: 18px;
        left: 3px;
        bottom: 3px;
        background-color: white;
        transition: .3s;
        border-radius: 50%;
        box-shadow: 0 1px 3px rgba(0,0,0,0.2);
    }
    .toggle-switch input:checked + .toggle-slider {
        background-color: #28a745; 
    }
    .toggle-switch input:checked + .toggle-slider:before {
        transform: translateX(20px); 
    }

    /* 選択バッジ */
    .selected-badges-container {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        margin-top: 5px;
        margin-bottom: 10px;
        max-height: 85px;
        overflow-y: auto;
        padding: 5px;
        background: #fdfdfd;
        border: 1px dashed #ccc;
        border-radius: 4px;
    }
    .selected-badge {
        background-color: #e0f2fe;
        color: #0369a1;
        border: 1px solid #bae6fd;
        border-radius: 16px;
        padding: 2px 10px;
        font-size: 0.8rem;
        display: flex;
        align-items: center;
        gap: 6px;
        font-weight: bold;
    }
    .selected-badge-remove {
        cursor: pointer;
        color: #ef4444;
        font-weight: bold;
        font-size: 0.95rem;
        line-height: 1;
        display: inline-block;
        margin-left: 2px;
    }
    .selected-badge-remove:hover {
        color: #b91c1c;
    }

    /* --- 7. スマートフォン環境（ボトムシート＆縦並びフォールバック） --- */
    @media (max-width: 768px) {
        /* タブバー自体の高さ制限を解除し、要素を均等幅にして改行を防ぐ */
        #deck-tabs {
            height: auto !important;
            gap: 4px !important;
        }
        .tab {
            font-size: 11px !important;
            padding: 6px 4px !important;
            white-space: nowrap !important; /* 改行を絶対に防止 */
            flex: 1; /* 横幅いっぱいに3等分で均等配置 */
            text-align: center;
        }

        /* スマホ時も分析・保存ボタンを右寄せに維持 */
        #deck-header {
            height: auto !important;
            margin: 8px 0 !important;
            display: flex !important;
            justify-content: flex-end !important; /* 右寄せにする */
        }
        #deck-header h3 {
            display: none !important; /* ボタンとの衝突を防ぐため非表示 */
        }
        #deck-header > div {
            justify-content: flex-end !important; /* 右寄せを維持 */
            gap: 8px !important;
        }
        
        /* ボタンを横に引き伸ばさず、コンパクトに右端へ配置 */
        .btn-analysis, #save-deck-btn {
            flex: none !important; /* 引き伸ばさない */
            font-size: 12px !important;
            padding: 6px 12px !important;
        }
        
        /* 既存の個別スタイル（padding-bottom）の競合を調整 */
        #deck-area {
            padding: 8px;
            padding-bottom: 210px !important; 
        }
        #deck-area.search-collapsed {
            padding-bottom: 110px !important;
        }

        #main-deck-list {
            grid-template-columns: repeat(8, 1fr) !important; 
            gap: 1px;
        }

        #extra-deck-area {
            flex-direction: column !important;
            gap: 15px;
            align-items: center;
            overflow-y: auto;
        }
        .zone-container {
            width: 100%;
        }
        
        /* スマホ時はアスペクト比固定をリセットし、横幅に応じて等倍縮小 */
        #gr-list, #super-dim-list {
            width: 100% !important;
            max-width: 380px; 
            height: auto !important;
            aspect-ratio: auto !important;
            grid-template-columns: repeat(4, 1fr) !important;
            grid-template-rows: none !important;
            grid-auto-rows: auto !important;
        }
        .extra-list img {
            width: 100% !important;
            height: auto !important;
        }

        /* 特殊タブ */
        #special-deck-list.active {
            flex-direction: row !important; 
            justify-content: space-around;
            align-items: center;
            gap: 10px;
            padding: 5px !important;
        }
        .special-zone {
            padding: 5px !important;
            flex: 1;
            min-width: 0;
        }
        .special-label {
            font-size: 11px !important; 
            margin: 0 0 4px 0 !important;
            text-align: center;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            width: 100%;
        }
        .btn-add-special {
            font-size: 11px !important;
            padding: 4px 10px !important; 
            margin-bottom: 8px !important;
        }
        .special-box {
            width: 38vw !important; 
            max-width: 160px !important; 
            height: auto !important; 
            aspect-ratio: 110 / 154 !important; 
            border-radius: 6px;
        }
        .special-v-line {
            display: block !important; 
            height: 120px; 
            margin: 5px 0 !important;
        }

        /* 検索セクションボトムシート（配置・順番の再定義） */
        #search-section {
            position: fixed !important;
            bottom: 0 !important;
            left: 50% !important;
            transform: translateX(-50%) translateY(0) !important;
            width: 100% !important;
            max-width: 900px !important;
            height: 200px !important;
            border-top: 3px solid #333 !important;
            border-left: none !important;
            border-right: none !important;
            box-shadow: 0 -5px 20px rgba(0, 0, 0, 0.15) !important;
            padding: 5px 10px !important;
            transition: transform 0.3s ease !important;
            display: flex !important;
            flex-direction: column !important; /* 上下配置 */
            gap: 4px !important;
        }
        #search-section.collapsed {
            width: 100% !important;
            min-width: 100% !important;
            padding: 5px 10px !important;
            border-top: 3px solid #333 !important;
            transform: translateX(-50%) translateY(200px) !important;
        }
        
        #search-toggle-btn {
            position: absolute !important;
            top: -32px !important;
            left: auto !important;
            right: 20px !important;
            transform: none !important;
            width: auto !important;
            height: 32px !important;
            writing-mode: horizontal-tb !important;
            border-radius: 8px 8px 0 0 !important;
            transition: none !important;
        }

        /* 1. 一番上にカード横並び(横スクロール) */
        #search-results {
            display: flex !important;
            flex-direction: row !important;
            overflow-x: auto !important;
            overflow-y: hidden !important;
            white-space: nowrap !important;
            height: 120px !important; 
            padding: 2px 0 !important;
            gap: 8px !important;
            order: 1 !important; 
            
            /* ★追加：ブラウザに横スクロールを最優先で処理させ、滑らかな動き（慣性）を強制します */
            touch-action: pan-x;
            -webkit-overflow-scrolling: touch; 
        }
        #search-results img {
            height: 110px !important; 
            width: auto !important; 
            aspect-ratio: 110/154 !important; 
            object-fit: contain !important; 
            background-color: #fff; 
            flex-shrink: 0;
            
            /* ★追加：画像単体にも横スワイプ時はスクロールを優先させます */
            touch-action: pan-x; 
        }

        /* コントロール群ラッパー */
        #search-controls-wrapper {
            border-bottom: none !important;
            padding-bottom: 0 !important;
            display: flex !important;
            flex-direction: column !important;
            gap: 4px !important;
            order: 2 !important; /* 二番目 */
            width: 100% !important;
        }

        /* 2. その下に検索ボックス、右隣にボタン */
        #search-controls {
            display: flex !important;
            flex-direction: row !important; /* 横並び */
            align-items: center !important;
            gap: 6px !important;
            width: 100% !important;
            height: auto !important;
        }
        
        #card-search-input {
            flex: 1 !important; /* 検索ボックスを広めに */
            padding: 8px !important;
            font-size: 14px !important;
            box-sizing: border-box !important;
        }

        #search-buttons-row {
            display: flex !important;
            gap: 4px !important;
            flex-shrink: 0 !important; /* ボタン幅が潰れないように制限 */
        }

        .btn-filter, .btn-sort {
            padding: 8px 12px !important;
            font-size: 12px !important;
            white-space: nowrap !important;
        }

        /* 3. 一番下にカード名・テキストのチェックボックス（左寄せ） */
        .search-scope {
            display: flex !important;
            justify-content: flex-start !important; /* 左寄せ */
            align-items: center !important;
            padding-left: 4px !important;
            gap: 15px !important;
            height: auto !important;
            margin-top: 2px !important;
        }
        .search-scope label {
            white-space: nowrap;     /* ★文字とチェックボックスが絶対に途中で折り返さないようにします */
            display: flex;
            align-items: center;
            gap: 4px;
            margin: 0;
            cursor: pointer;
        }

        /* 詳細モーダル */
        .detail-content {
            width: 92% !important;
            margin: 3vh auto !important;
            padding: 15px !important;
            max-height: 94vh;
            overflow-y: auto;
        }
        .detail-grid {
            display: flex !important;
            flex-direction: column !important;
            gap: 12px !important;
            margin-bottom: 15px !important;
        }
        #detail-name {
            order: 1;
            font-size: 1.2rem !important;
            margin: 15px 0 5px 0 !important;
            padding-bottom: 8px !important;
            padding-right: 25px; 
        }
        .detail-image {
            order: 2;
            width: 100%;
            max-width: 200px; 
            margin: 0 auto !important;
        }
        .detail-qty {
            order: 3;
        }
        .qty-controls {
            margin: 5px 0 !important;
        }
        .detail-desc {
            order: 4;
            width: 100%;
        }
        #detail-text {
            max-height: 160px !important;
            padding: 10px !important;
            font-size: 0.85rem !important;
        }
        .detail-close-btn {
            right: 15px !important;
            top: 12px !important;
            font-size: 28px !important;
        }
        .version-section h4 {
            margin: 10px 0 5px 0 !important;
            font-size: 0.9rem;
        }
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
                <!-- 利き手（検索エリアの左右）切り替えスイッチ -->
                <button class="btn-analysis" onclick="openAnalysisModal()">分析</button>
                <button id="save-deck-btn" onclick="openSaveModal()" style="padding: 8px 20px; background: #28a745; color: #fff; border: none; border-radius: 4px; cursor: pointer; font-weight: bold;">保存する</button>
            </div>
        </div>

        <!-- メインリスト -->
        <div id="main-deck-list" class="deck-content active"></div>

        <!-- GR/超次元エリア -->
        <div id="extra-deck-area" class="deck-content" style="display:none; flex-direction:row; gap:20px; padding:10px;">
            <div class="zone-container" style="flex:1;">
                <h4 style="margin:0 0 5px 0; border-bottom:2px solid #007bff;">超GR</h4>
                <div id="gr-list" class="extra-list gr-layout"></div>
            </div>
            <div class="zone-container" style="flex:1;">
                <h4 style="margin:0 0 5px 0; border-bottom:2px solid #dc3545;">超次元ゾーン</h4>
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
    <button id="search-toggle-btn" onclick="toggleSearchSection()">◀ 検索を隠す</button>
    <!-- 検索エリア（右側・左側縦長配置へ変更） -->
    <div id="search-section">
        <!-- 開閉トグルボタン（縦型テキスト仕様） -->        
        <div id="search-controls-wrapper">
            <div id="search-controls">
                <!-- 入力フォーム -->
                <input type="text" id="card-search-input" placeholder="カード名を入力..." autocomplete="off">
                <!-- 絞り込み & 並び替えボタンの横並び行 -->
                <div id="search-buttons-row">
                    <button class="btn-filter" onclick="toggleFilterModal()">絞り込み</button>
                    <button class="btn-sort" onclick="toggleSortModal()">並び替え</button>
                </div>
            </div>
            <div class="search-scope">
                <label><input type="checkbox" class="scope-check" value="name" checked> カード名</label>
                <label><input type="checkbox" class="scope-check" value="text"> テキスト</label>
                <!-- ★追加：種族のチェックボックス -->
                <label><input type="checkbox" class="scope-check" value="race"> 種族</label>
                <button class="hand-switch-btn" onclick="toggleSearchPosition()">⇄ 位置切り替え</button>
            </div>
        </div>
        
        <!-- カード検索結果（グリッドスクロール表示） -->
        <div id="search-results"></div>
    </div>
</div>

<!-- 詳細モーダル -->
<div id="cardDetailModal">
    <div class="detail-content">
        <!-- 右上の閉じるボタン -->
        <span class="detail-close-btn" onclick="closeDetailModal()">&times;</span>
        
        <!-- PC時はGrid、スマホ時はFlexbox(縦)で順序を制御 -->
        <div class="detail-grid">
            <h2 id="detail-name"></h2>
            
            <div class="detail-image">
                <img id="detail-main-img" src="">
            </div>
            
            <div class="detail-qty">
                <div class="qty-controls">
                    <button class="btn-qty" onclick="adjustQty(-1)">-</button>
                    <span id="detail-qty">0</span> / <span id="detail-limit">4</span>枚
                    <button class="btn-qty" onclick="adjustQty(1)">+</button>
                </div>
            </div>
            
            <div class="detail-desc">
                <div id="detail-text"></div>
            </div>
        </div>
        
        <div class="version-section">
            <h4>バージョン切り替え</h4>
            <div id="detail-version-list" class="version-list"></div>
        </div>
    </div>
</div>

<!-- 絞り込みモーダル -->
<div id="filterModal">
    <div class="filter-content">
        <div class="filter-header">
            <h3>詳細絞り込み</h3>
            <span class="filter-close" onclick="toggleFilterModal()">&times;</span>
        </div>
        
        <div class="filter-scroll">
            <!-- 文明グループ -->
            <div class="filter-group">
                <label>文明</label>
                <!-- 単色・多色選択 -->
                <div style="display: flex; gap: 15px; margin-bottom: 12px;">
                    <label style="font-size: 13px; font-weight: normal; margin: 0; cursor: pointer; display: flex; align-items: center; gap: 4px;">
                        <input type="checkbox" id="filter-civ-single" checked onchange="toggleFilterCivType()"> 単色
                    </label>
                    <label style="font-size: 13px; font-weight: normal; margin: 0; cursor: pointer; display: flex; align-items: center; gap: 4px;">
                        <input type="checkbox" id="filter-civ-multi" checked onchange="toggleFilterCivType()"> 多色
                    </label>
                </div>
                
                <div class="civ-checkboxes">
                    <label><input type="checkbox" class="civ-check" value="1"> 光</label>
                    <label><input type="checkbox" class="civ-check" value="2"> 水</label>
                    <label><input type="checkbox" class="civ-check" value="3"> 闇</label>
                    <label><input type="checkbox" class="civ-check" value="4"> 火</label>
                    <label><input type="checkbox" class="civ-check" value="5"> 自然</label>
                    <label><input type="checkbox" class="civ-check" value="6"> ゼロ</label>
                </div>

                <!-- 含む / のみ持つ ラジオボタン -->
                <div style="display: flex; gap: 15px; margin: 12px 0; font-size: 12px; color: #555;">
                    <label style="cursor: pointer; font-weight: normal; display: flex; align-items: center; gap: 4px;"><input type="radio" name="filter-civ-match-type" value="include" checked> 選択した文明を含む</label>
                    <label style="cursor: pointer; font-weight: normal; display: flex; align-items: center; gap: 4px;"><input type="radio" name="filter-civ-match-type" value="match"> 選択した文明のみ持つ</label>
                </div>

                <!-- 含まれない文明を指定（除外エリア） -->
                <div id="filter-exclude-civ-area" style="border-top: 1px solid #eaeaea; padding-top: 12px; margin-top: 12px;">
                    <span style="font-size: 11px; color: #666; grid-column: span 3; font-weight: bold; margin-bottom: 4px;">含まれない文明を指定（除外）</span>
                    <label><input type="checkbox" class="filter-exclude-civ-check" value="1"> 光</label>
                    <label><input type="checkbox" class="filter-exclude-civ-check" value="2"> 水</label>
                    <label><input type="checkbox" class="filter-exclude-civ-check" value="3"> 闇</label>
                    <label><input type="checkbox" class="filter-exclude-civ-check" value="4"> 火</label>
                    <label><input type="checkbox" class="filter-exclude-civ-check" value="5"> 自然</label>
                    <label><input type="checkbox" class="filter-exclude-civ-check" value="6"> ゼロ</label>
                </div>
            </div>

            <!-- 特殊タイプグループ -->
            <div class="filter-group">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 8px;">
                    <label style="font-size: 0.85rem; font-weight: 600; color: #444; margin: 0;">特殊タイプ</label>
                    <div class="logic-switch">
                        <label><input type="radio" name="characteristic_logic" value="AND"><span>AND</span></label>
                        <label><input type="radio" name="characteristic_logic" value="OR" checked><span>OR</span></label>
                    </div>
                </div>
                <div id="characteristics-trigger" class="select-trigger" onclick="openSubModal('characteristics')">特殊タイプを選択</div>
            </div>

            <!-- カードタイプグループ -->
            <div class="filter-group">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 8px;">
                    <label style="font-size: 0.85rem; font-weight: 600; color: #444; margin: 0;">カードタイプ</label>
                    <div class="logic-switch">
                        <label><input type="radio" name="cardtype_logic" value="AND"><span>AND</span></label>
                        <label><input type="radio" name="cardtype_logic" value="OR" checked><span>OR</span></label>
                    </div>
                </div>
                <div id="cardtype-trigger" class="select-trigger" onclick="openSubModal('cardtype')">カードタイプを選択</div>
            </div>

            <!-- コスト・パワーグループ -->
            <div style="display: grid; grid-template-columns: 1fr 1.2fr; gap: 16px; margin-bottom: 20px;">
                <div class="filter-group" style="margin: 0;">
                    <label>コスト</label>
                    <div class="range-inputs">
                        <input type="number" id="cost-min" placeholder="下限">
                        <span class="range-separator">〜</span>
                        <input type="number" id="cost-max" placeholder="上限">
                    </div>
                </div>
                <br>
                <div class="filter-group" style="margin: 0;">
                    <label>パワー</label>
                    <div class="range-inputs">
                        <input type="number" id="pow-min" placeholder="下限">
                        <span class="range-separator">〜</span>
                        <input type="number" id="pow-max" placeholder="上限">
                    </div>
                </div>
            </div>

            <!-- 種族グループ -->
            <div class="filter-group">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 8px;">
                    <label style="font-size: 0.85rem; font-weight: 600; color: #444; margin: 0;">種族</label>
                    <div class="logic-switch">
                        <label><input type="radio" name="race_logic" value="AND"><span>AND</span></label>
                        <label><input type="radio" name="race_logic" value="OR" checked><span>OR</span></label>
                    </div>
                </div>
                <div id="race-trigger" class="select-trigger" onclick="openSubModal('race')">種族を選択</div>
            </div>

            <!-- 特殊能力グループ -->
            <div class="filter-group">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 8px;">
                    <label style="font-size: 0.85rem; font-weight: 600; color: #444; margin: 0;">特殊能力</label>
                    <div class="logic-switch">
                        <label><input type="radio" name="ability_logic" value="AND"><span>AND</span></label>
                        <label><input type="radio" name="ability_logic" value="OR" checked><span>OR</span></label>
                    </div>
                </div>
                <div id="ability-trigger" class="select-trigger" onclick="openSubModal('ability')">特殊能力を選択</div>
            </div>
            <!-- 収録商品グループ -->
            <div class="filter-group">
                <label style="font-size: 0.85rem; font-weight: 600; color: #444; margin-bottom: 8px;">収録商品</label>
                <div id="goods-trigger" class="select-trigger" onclick="openSubModal('goods')">収録商品を選択</div>
            </div>            
            <!-- レギュレーショングループ -->
            <div class="filter-group" style="margin: 0;">
                <label>レギュレーション</label>
                <div style="display: flex; gap: 15px;">
                    <label style="font-size: 13px; font-weight: normal; cursor: pointer; display: flex; align-items: center; gap: 4px;"><input type="checkbox" class="reg-check" value="2"> 殿堂</label>
                    <label style="font-size: 13px; font-weight: normal; cursor: pointer; display: flex; align-items: center; gap: 4px;"><input type="checkbox" class="reg-check" value="3"> プレミアム殿堂</label>
                </div>
            </div>
        </div>

        <div class="filter-actions">
            <button class="btn-filter-clear" onclick="clearAllFilters()">クリア</button>
            <button class="btn-filter-apply" onclick="applyFilters()">適用する</button>
        </div>
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

<!-- サブモーダル (種族選択) -->
<div id="raceSelectModal" class="sub-modal"><div class="sub-modal-content">
    <div style="padding:15px; background:#333; color:#fff; display:flex; justify-content:space-between;"><span>種族選択</span><span onclick="closeSubModal('race')" style="cursor:pointer;">&times;</span></div>
    <div style="padding:10px;">
        <input type="text" id="race-list-search" placeholder="検索..." style="width:100%; padding:8px; margin-bottom:5px;">
        <div id="race-selected-badges" class="selected-badges-container" style="display: none;"></div>
    </div>
    <div id="race-list-container" class="sub-modal-body"></div>
    <div style="padding:10px; border-top:1px solid #eee;"><button onclick="closeSubModal('race')" style="width:100%; padding:10px; background:#007bff; color:#fff; border:none; border-radius:4px;">決定</button></div>
</div></div>

<!-- サブモーダル (特殊能力選択) -->
<div id="abilitySelectModal" class="sub-modal"><div class="sub-modal-content">
    <div style="padding:15px; background:#333; color:#fff; display:flex; justify-content:space-between;"><span>特殊能力選択</span><span onclick="closeSubModal('ability')" style="cursor:pointer;">&times;</span></div>
    <div style="padding:10px;">
        <input type="text" id="ability-list-search" placeholder="検索..." style="width:100%; padding:8px; margin-bottom:5px;">
        <div id="ability-selected-badges" class="selected-badges-container" style="display: none;"></div>
    </div>
    <div id="ability-list-container" class="sub-modal-body"></div>
    <div style="padding:10px; border-top:1px solid #eee;"><button onclick="closeSubModal('ability')" style="width:100%; padding:10px; background:#007bff; color:#fff; border:none; border-radius:4px;">決定</button></div>
</div></div>

<!-- サブモーダル (特殊タイプ選択) -->
<div id="characteristicsSelectModal" class="sub-modal">
    <div class="sub-modal-content">
        <div class="sub-modal-header">
            <span>特殊タイプ選択</span>
            <span onclick="closeSubModal('characteristics')" style="cursor:pointer; font-size: 20px;">&times;</span>
        </div>
        <div style="padding:10px 20px 0 20px;">
            <div id="characteristics-selected-badges" class="selected-badges-container" style="display: none;"></div>
        </div>
        <div id="characteristics-list-container" class="sub-modal-body"></div>
        <div style="padding:15px; border-top:1px solid #eee; background:#fff;">
            <button onclick="closeSubModal('characteristics')" style="width:100%; padding:10px; background:#007bff; color:#fff; border:none; border-radius:8px; font-weight:bold; cursor:pointer;">決定</button>
        </div>
    </div>
</div>

<!-- サブモーダル (カードタイプ選択) -->
<div id="cardtypeSelectModal" class="sub-modal">
    <div class="sub-modal-content">
        <div class="sub-modal-header">
            <span>カードタイプ選択</span>
            <span onclick="closeSubModal('cardtype')" style="cursor:pointer; font-size: 20px;">&times;</span>
        </div>
        <div style="padding:10px 20px 0 20px;">
            <div id="cardtype-selected-badges" class="selected-badges-container" style="display: none;"></div>
        </div>
        <div id="cardtype-list-container" class="sub-modal-body"></div>
        <div style="padding:15px; border-top:1px solid #eee; background:#fff;">
            <button onclick="closeSubModal('cardtype')" style="width:100%; padding:10px; background:#007bff; color:#fff; border:none; border-radius:8px; font-weight:bold; cursor:pointer;">決定</button>
        </div>
    </div>
</div>

<!-- サブモーダル (収録商品選択) -->
<div id="goodsSelectModal" class="sub-modal">
    <div class="sub-modal-content">
        <div class="sub-modal-header">
            <span>収録商品選択</span>
            <span onclick="closeSubModal('goods')" style="cursor:pointer; font-size: 20px;">&times;</span>
        </div>
        <!-- ★追加：シリーズ一括選択エリア -->
        <div style="padding: 12px 15px; border-bottom: 1px solid #f2f2f7; background-color: #fafafa;">
            <span style="font-size: 11px; color: #666; font-weight: bold; display: block; margin-bottom: 8px;">シリーズで一括選択</span>
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px;">
                <label style="font-size: 13px; font-weight: normal; color: #333; display: flex; align-items: center; gap: 6px; margin: 0; cursor: pointer;">
                    <input type="checkbox" id="era-shobu" class="era-check" value="shobu"> 勝舞編
                </label>
                <label style="font-size: 13px; font-weight: normal; color: #333; display: flex; align-items: center; gap: 6px; margin: 0; cursor: pointer;">
                    <input type="checkbox" id="era-katta" class="era-check" value="katta"> 勝太編
                </label>
                <label style="font-size: 13px; font-weight: normal; color: #333; display: flex; align-items: center; gap: 6px; margin: 0; cursor: pointer;">
                    <input type="checkbox" id="era-joe" class="era-check" value="joe"> ジョー編
                </label>
                <label style="font-size: 13px; font-weight: normal; color: #333; display: flex; align-items: center; gap: 6px; margin: 0; cursor: pointer;">
                    <input type="checkbox" id="era-win" class="era-check" value="win"> ウィン編
                </label>
            </div>
        </div>
        <!-- ここまで -->
         
        <div style="padding:10px;">
            <input type="text" id="goods-list-search" placeholder="商品を検索..." style="width:100%; padding:8px; margin-bottom:5px;">
            <div id="goods-selected-badges" class="selected-badges-container" style="display: none;"></div>
        </div>
        <div id="goods-list-container" class="sub-modal-body"></div>
        <div style="padding:15px; border-top:1px solid #eee; background:#fff;">
            <button onclick="closeSubModal('goods')" style="width:100%; padding:10px; background:#007bff; color:#fff; border:none; border-radius:8px; font-weight:bold; cursor:pointer;">決定</button>
        </div>
    </div>
</div>

<!-- デッキ保存・設定モーダル -->
<div id="deckSaveModal" class="sub-modal">
    <div class="sub-modal-content" style="max-width: 460px; height: auto; max-height: 90vh;">
        <!-- 画面上部角までぴったり #333 のヘッダー -->
        <div class="sub-modal-header">
            <span>デッキ保存設定</span>
            <span onclick="closeSaveModal()" style="cursor:pointer; font-size:24px;">&times;</span>
        </div>
        
        <!-- 余白を綺麗に持たせたスクロールエリア -->
        <div class="sub-modal-body" style="padding: 20px; display: flex; flex-direction: column; gap: 16px;">
            <!-- デッキ名 -->
            <div>
                <label style="font-weight: bold; font-size: 13px; display: block; margin-bottom: 6px; color: #444;">デッキ名</label>
                <input type="text" id="save-deck-name" placeholder="デッキ名を入力してください" style="width: 100%; padding: 10px; border: 1px solid #d1d1d6; border-radius: 8px; box-sizing: border-box; outline: none; font-size: 14px;">
            </div>
            
            <!-- フォーマット選択 -->
            <div>
                <label style="font-weight: bold; font-size: 13px; display: block; margin-bottom: 6px; color: #444;">フォーマット</label>
                <select id="save-deck-format" style="width: 100%; padding: 10px; border: 1px solid #d1d1d6; border-radius: 8px; box-sizing: border-box; background: white; font-size: 14px; outline: none; cursor: pointer;">
                    <!-- JSで動的にオプション生成 -->
                </select>
            </div>
            
            <!-- サムネイル選択 -->
            <div>
                <label style="font-weight: bold; font-size: 13px; display: block; margin-bottom: 6px; color: #444;">デッキサムネイル</label>
                <div style="display: flex; gap: 15px; align-items: center;">
                    <div id="thumbnail-preview-box" style="width: 80px; height: 112px; border: 2px dashed #d1d1d6; border-radius: 8px; display: flex; align-items: center; justify-content: center; overflow: hidden; background: #f9f9f9; flex-shrink: 0;">
                        <span id="thumbnail-placeholder" style="font-size: 11px; color: #999; text-align: center; padding: 5px;">未選択</span>
                        <img id="thumbnail-preview-img" style="display: none; width: 100%; height: 100%; object-fit: contain;">
                    </div>
                    <div style="flex: 1;">
                        <p style="font-size: 11px; color: #666; margin: 0 0 8px 0; line-height: 1.4;">デッキに採用されているカードの中から、1枚をサムネイル画像（看板）として登録できます。</p>
                        <button type="button" onclick="openThumbnailSelector()" style="padding: 8px 16px; background: #17a2b8; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 12px; font-weight: bold; transition: background 0.2s;">カードを選択する</button>
                    </div>
                </div>
                <input type="hidden" id="save-deck-thumbnail-id" value="">
            </div>

            <!-- 公開設定（シンプルなスライドトグル） -->
            <div style="margin-top: 10px; border-top: 1px solid #f2f2f7; padding-top: 15px; display: flex; justify-content: space-between; align-items: center;">
                <div style="padding-right: 15px; flex: 1;">
                    <label style="font-weight: bold; font-size: 13px; display: block; margin-bottom: 2px; color: #444;">デッキを公開する</label>
                    <span style="font-size: 11px; color: #666; display: block; line-height: 1.4;">公開すると、他のユーザーがデッキ検索で閲覧・コピーできるようになります。</span>
                </div>
                
                <!-- スタイリッシュなスライド式トグル -->
                <label class="toggle-switch" style="flex-shrink: 0;">
                    <input type="checkbox" id="save-deck-public">
                    <span class="toggle-slider"></span>
                </label>
            </div>
        </div>
        
        <!-- フッターボタンエリア -->
        <div style="padding: 15px 20px 20px 20px; border-top: 1px solid #f2f2f7; display: flex; gap: 12px; background: #fff;">
            <button onclick="closeSaveModal()" style="flex: 1; padding: 12px; background: #f2f2f7; color: #555; border: none; border-radius: 8px; cursor: pointer; font-weight: bold; font-size: 14px;">キャンセル</button>
            <button onclick="submitDeckSave()" style="flex: 1; padding: 12px; background: #28a745; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: bold; font-size: 14px;">保存する</button>
        </div>
    </div>
</div>

<!-- サムネイルカード選択サブモーダル -->
<div id="thumbnailSelectModal" class="sub-modal">
    <div class="sub-modal-content" style="max-width: 400px; height: 75vh;">
        <!-- 画面上部角までぴったり #333 のヘッダー -->
        <div class="sub-modal-header">
            <span>サムネイルにするカードを選択</span>
            <span onclick="closeThumbnailSelector()" style="cursor:pointer; font-size: 20px;">&times;</span>
        </div>
        <!-- ボディエリア -->
        <div id="thumbnail-cards-list" class="sub-modal-body" style="padding: 15px; display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; align-content: start;">
            <!-- JSでデッキ内カード画像を動的に配置 -->
        </div>
    </div>
</div>
<!-- 既存のモーダル類の直後や、最下部に配置 -->
<?php include __DIR__ . '/analysis_modal.php'; ?>



<script>
// --- A. グローバル状態管理 ---
const isEdit = <?php echo !empty($isEdit) ? 'true' : 'false'; ?>;
const deckId = <?php echo isset($deck['deck_id']) ? $deck['deck_id'] : 'null'; ?>;
const initialDeckName = <?php echo isset($deck['deck_name']) ? json_encode($deck['deck_name']) : '"マイデッキ"'; ?>;
const initialCards = <?php echo !empty($initialCards) ? json_encode($initialCards) : '[]'; ?>;
const currentSort = { key: null, order: 'asc' };

const cardCache = {};


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
    characteristics: [], cardtypes: [], // ★特殊タイプとカードタイプを追加
    goods: [],
    race_logic: 'OR', ability_logic: 'OR', 
    characteristic_logic: 'OR', cardtype_logic: 'OR', // ★ロジックを追加
    reg: [] ,
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
            cardCache[card.card_id] = card; // ★追加：既存カードをキャッシュに格納
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
            const isPublicVal = <?php echo $deck['is_public'] ? 'true' : 'false'; ?>;
            document.getElementById('save-deck-public').checked = isPublicVal;
            // ★追記：ラジオボタン（トグルボタン）のアクティブ側も初期同期
            const toggleRadio = document.querySelector(`input[name="deck-public-toggle"][value="${isPublicVal ? '1' : '0'}"]`);
            if (toggleRadio) toggleRadio.checked = true;
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
            
            // ★ 特殊タイプの描画（ID昇順ソート）
            if (data.characteristics) {
                data.characteristics.sort((a, b) => a.characteristics_id - b.characteristics_id);
                renderMasterList('characteristics', data.characteristics);
            }
            // ★ カードタイプの描画（ID昇順ソート）
            if (data.cardtypes) {
                data.cardtypes.sort((a, b) => a.cardtype_id - b.cardtype_id);
                renderMasterList('cardtype', data.cardtypes);
            }
        });

    // 拡張マスターデータAPIから収録商品（goods）を取得して一覧を描画
    fetch('/api/master-data-extended')
        .then(res => res.json())
        .then(data => {
            if (data.goods) {
                renderMasterList('goods', data.goods);
            }
        })
        .catch(err => console.error("商品データの取得に失敗しました", err));

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

    // ★ 追記：画面を開いた直後に自動で発売日が新しいカードを表示する
    searchCards();
    const nameInput = document.getElementById('save-deck-name');
    const formatSelect = document.getElementById('save-deck-format');
    if (nameInput) nameInput.addEventListener('input', saveDraftToLocalStorage);
    if (formatSelect) formatSelect.addEventListener('change', saveDraftToLocalStorage);

    // ★追加：マスタ取得や描画が少し落ち着いたタイミングで復元チェックを実行
    setTimeout(checkAndRestoreDraft, 250);
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
function addCardToDeck(card, forcedType = null, forcedSlotId = null) {
    cardCache[card.card_id] = card;
    const img = document.createElement('img');
    img.src = getCardImagePath(card);
    img.dataset.cardId = card.card_id;
    img.dataset.cardName = card.card_name;
    img.dataset.comboNames = card.combo_names || '';
    img.dataset.charIds = card.char_ids;
    img.dataset.imagepath = card.imagepath;
    img.dataset.cardLimit = card.card_limit !== undefined && card.card_limit !== null ? card.card_limit : '';
    img.dataset.civ = card.civilization || card.civ || card.civ_ids || '';
    img.dataset.cost = card.cost !== undefined ? card.cost : '';
    img.alt = card.card_name;
    img.onerror = () => handleImageError(img);

    const name = card.card_name;
    // 名前が完全一致する場合のみ特殊カードとして判定
    const isDoru = (name === '終焉の禁断 ドルマゲドンX' || name === 'FORBIDDEN STAR ～世界最後の日～');
    const isZero = (name === '零龍' || name === '滅亡の起源 零無');
    const isSpecial = isDoru || isZero;

    const type = forcedType || (isSpecial ? 'special' : determineZoneType(card.char_ids));

    if (type === 'special') {
        // 明示的な指定があればそれを使い、なければカード名から判別
        const slotId = forcedSlotId || (isDoru ? 'slot-dolmagedon' : 'slot-zeroron');
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
    if (e.target.tagName === 'IMG') {
        const cardId = e.target.dataset.cardId;
        openCardDetail(cardId, e.target);
    }
});  


// --- D. SortableJS 設定 ---
const deckSortableConfig = {
    // グループ形式をオブジェクトで明示し、検索側と完全に同期
    group: { 
        name: 'shared', 
        pull: true, 
        put: true 
    }, 
    animation: 150,
    // ★ 長押し設定（delayおよびdelayOnTouchOnly）を排除し、即座に掴めるようにしました
    touchStartThreshold: 10, 
    
    // Fallbackの設定を検索エリア側と同期させてドロップを可能にします
    forceFallback: true,      
    fallbackTolerance: 5,     // わずかな動きでもドラッグを開始させます
    fallbackOnBody: true,

    onEnd: function(evt) {
        // ドラッグ終了時のカーソル位置を取得
        const e = evt.originalEvent;
        if (!e) {
            updateDeckDisplay();
            return;
        }

        // スマホの指を離した瞬間の座標を取得（changedTouchesも参照するよう補正）
        const touch = (e.touches && e.touches[0]) || (e.changedTouches && e.changedTouches[0]);
        const clientX = e.clientX || (touch ? touch.clientX : 0);
        const clientY = e.clientY || (touch ? touch.clientY : 0);

        // ドロップ座標が検索セクション（#search-section）の上であれば、その場で削除を実行
        const searchSection = document.getElementById('search-section');
        if (searchSection) {
            const sRect = searchSection.getBoundingClientRect();
            const isOverSearch = (clientX >= sRect.left && clientX <= sRect.right &&
                                  clientY >= sRect.top && clientY <= sRect.bottom);
            
            if (isOverSearch && evt.from !== resultsDiv) {
                evt.item.remove();
                updateDeckDisplay();
                return;
            }
        }

        // 有効なドロップ先（メイン・超次元・GRの各リスト）
        const validLists = [mainList, superDimList, grList];
        
        // 特殊エリアのスロット（アクティブ・非アクティブ問わず枠内であれば検知）
        document.querySelectorAll('.special-box').forEach(box => {
            validLists.push(box);
        });

        // いずれかの有効リストの範囲内にドロップされたか判定
        let isInside = false;
        for (const list of validLists) {
            const rect = list.getBoundingClientRect();
            if (clientX >= rect.left && clientX <= rect.right &&
                clientY >= rect.top && clientY <= rect.bottom) {
                isInside = true;
                break;
            }
        }

        // デッキの有効領域（枠内）のいずれにも属さず、かつ検索結果からの新規ドラッグでない場合は削除
        if (!isInside && evt.from !== resultsDiv) {
            evt.item.remove();
        }

        updateDeckDisplay();
    },
    onAdd: function(evt) {
        const item = evt.item;
        const name = item.dataset.cardName;
        const charIds = (item.dataset.charIds || '').split(',');
        
        const limitVal = item.dataset.cardLimit;
        const nameLimit = (limitVal !== undefined && limitVal !== "" && limitVal !== null) ? parseInt(limitVal, 10) : 99;

        const isDoru = (name === '終焉の禁断 ドルマゲドンX' || name === 'FORBIDDEN STAR ～世界最後の日～');
        const isZero = (name === '零龍' || name === '滅亡の起源 零無');

        if (isDoru || isZero) {
            const slotId = isDoru ? 'slot-dolmagedon' : 'slot-zeroron';
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

        const comboNames = item.dataset.comboNames || '';

        if (charIds.includes('3') || charIds.includes('6')) {
            superDimList.appendChild(item);
            if (!checkLimit(name, nameLimit, superDimList, item, 8, "超次元ゾーン", comboNames)) {
                item.remove();
            }
        } else if (charIds.includes('10')) {
            grList.appendChild(item);
            if (!checkLimit(name, nameLimit, grList, item, 12, "超GRゾーン", comboNames)) {
                item.remove();
            }
        } else {
            mainList.appendChild(item);
            if (!checkLimit(name, nameLimit, mainList, item, 60, "メインデッキ", comboNames)) {
                item.remove();
            }
        }
        updateDeckDisplay();
    }
};

// 各デッキリストのSortable定義（1回のみ記述します）
new Sortable(mainList, deckSortableConfig);
new Sortable(superDimList, deckSortableConfig);
new Sortable(grList, deckSortableConfig);

// 検索結果エリアのSortable設定
// ★ 長押しなしで瞬時にドラッグできるようディレイ（delay）を排除した設定のみを残します
const searchSortable = new Sortable(resultsDiv, { 
    group: { 
        name: 'shared', 
        pull: 'clone', 
        put: false 
    }, 
    sort: false, 
    animation: 150,
    forceFallback: true,      
    fallbackTolerance: 5,     // わずかな動きでもドラッグを開始させる設定
    fallbackOnBody: true      
});

document.querySelectorAll('.special-box').forEach(box => {
    new Sortable(box, {
        group: {
            name: 'shared',
            pull: false, // ★ このスロットからカードをドラッグして取り出すことを禁止します
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
function checkLimit(name, nameLimit, listElement, itemElement, totalLimit, zoneName, comboNames = '') {
    const isPreAdd = (itemElement === null);
    
    const totalCount = listElement.querySelectorAll('img').length + (isPreAdd ? 1 : 0);
    if (totalCount > totalLimit) {
        alert(`${zoneName}は合計${totalLimit}枚までです。`);
        return false;
    }
    
    // getCardSelector を用いてツインパクト等の面構成が完全に同一のカードのみをカウントする
    const selector = getCardSelector(name, comboNames);
    const nameCount = listElement.querySelectorAll(selector).length + (isPreAdd ? 1 : 0);
    
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

    // ★改善：画面幅が狭いスマホ時は、文字をコンパクトにして改行によるレイアウト崩れを防ぐ
    const isMobile = window.innerWidth <= 768;
    document.getElementById('tab-main').innerText = `メイン ${mCount}`;
    if (isMobile) {
        document.getElementById('tab-extra').innerText = `GR/次元 ${gCount}/${sCount}`;
    } else {
        document.getElementById('tab-extra').innerText = `GR ${gCount}/12 超次元 ${sCount}/8`;
    }

    adjustMainDeckRows();

    // 状態が変わるたびにローカルに自動保存する（既存処理）
    saveDraftToLocalStorage();
}

// --- F. カード詳細モーダル ---
function openCardDetail(cardId, el) {
    activeClickedElement = el;
    
    // 1. キャッシュの取得、またはクリックされたDOM（img）のデータ属性から基本情報をその場で復元（0ms化）
    let cachedCard = cardCache[cardId];
    
    // キャッシュがない、またはキャッシュ内に詳細テキスト(text)情報が不足している場合
    if ((!cachedCard || !cachedCard.text) && el && el.dataset) {
        cachedCard = {
            card_id: cardId,
            card_name: el.dataset.cardName || '',
            combo_names: el.dataset.comboNames || '',
            char_ids: el.dataset.charIds || '',
            imagepath: el.dataset.imagepath || '',
            card_limit: el.dataset.cardLimit || '',
            civilization: el.dataset.civ || '',
            cost: el.dataset.cost || '',
            // テキスト情報のみバックグラウンド通信で更新されるまで「読み込み中」にしておく
            text: cachedCard && cachedCard.text ? cachedCard.text : "詳細効果を読み込み中..."
        };
        cardCache[cardId] = cachedCard;
    }

    if (cachedCard) {
        // キャッシュ（または自己復元データ）を使って瞬時にモーダルを描画（0ms表示）
        selectedCardData = cachedCard;
        allVersions = [cachedCard]; // バージョン取得までは自身のみを表示
        renderDetailModal();
        
        // バージョン切り替えエリアを一時的にローディング表示にする
        document.getElementById('detail-version-list').innerHTML = '<span style="font-size:12px;color:#999;padding:5px;">読み込み中...</span>';
    } else {
        // 万が一データが全く取得できない場合のフォールバック表示
        document.getElementById('detail-name').innerText = "読み込み中...";
        document.getElementById('detail-text').innerText = "";
        document.getElementById('cardDetailModal').style.display = 'block';
    }

    // 2. 非同期で完全な詳細情報（全バージョン情報）をバックグラウンド取得して上書き
    fetch('/api/cards/versions?card_id=' + cardId)
        .then(res => res.json())
        .then(versions => {
            allVersions = versions;
            
            // 完全な情報をキャッシュにマージ・更新
            const freshData = versions.find(v => v.card_id == cardId);
            if (freshData) {
                selectedCardData = freshData;
                cardCache[cardId] = freshData;
            }
            
            // バージョン画像を含めて再描画
            renderDetailModal();
        })
        .catch(err => {
            console.error("詳細情報のバックグラウンド取得に失敗しました:", err);
        });
}

/* app/Views/deck/create.php 内の renderDetailModal() 関数を差し替え */
function renderDetailModal() {
    const modal = document.getElementById('cardDetailModal');
    const versionList = document.getElementById('detail-version-list');
    document.getElementById('detail-name').innerText = selectedCardData.card_name;
    document.getElementById('detail-text').innerText = selectedCardData.text || "効果なし";
    document.getElementById('detail-main-img').src = getCardImagePath(selectedCardData);

    const name = selectedCardData.card_name;
    let count = 0;
    
    // 変更箇所
    const comboNames = selectedCardData.combo_names || '';
    const selector = getCardSelector(name, comboNames);

    const isDoru = (name === '終焉の禁断 ドルマゲドンX' || name === 'FORBIDDEN STAR ～世界最後の日～');
    const isZero = (name === '零龍' || name === '滅亡の起源 零無');

    if (isDoru || isZero) {
        const slotId = isDoru ? 'slot-dolmagedon' : 'slot-zeroron';
        count = document.getElementById(slotId).classList.contains('active') ? 1 : 0;
    }
    else {
        const type = determineZoneType(selectedCardData.char_ids);
        let targetList = type === 'super_dimensional' ? superDimList : (type === 'gr' ? grList : mainList);
        count = targetList.querySelectorAll(selector).length; // ★ 修正（selector変数を使用）
    }
    
    // 現在の採用枚数の描画
    document.getElementById('detail-qty').innerText = count;

    // ★個別上限（分母）の動的描画（値がない、またはnullの場合は「∞」）
    const limitVal = selectedCardData.card_limit;
    const limitText = (limitVal !== undefined && limitVal !== "" && limitVal !== null) ? limitVal : "∞";
    document.getElementById('detail-limit').innerText = limitText;

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
    const isDoru = (name === '終焉の禁断 ドルマゲドンX' || name === 'FORBIDDEN STAR ～世界最後の日～');
    const isZero = (name === '零龍' || name === '滅亡の起源 零無');
    const isSpecial = isDoru || isZero;

    if (isSpecial) {
        const slotId = isDoru ? 'slot-dolmagedon' : 'slot-zeroron';
        const slot = document.getElementById(slotId);
        
        if (diff > 0) {
            if (!slot.classList.contains('active')) {
                // slotIdを明示的に渡して追加します
                addCardToDeck(selectedCardData, 'special', slotId);
            }
            document.getElementById('detail-qty').innerText = "1"; 
        } else {
            removeSpecialCard(slotId);
            document.getElementById('detail-qty').innerText = "0";
        }
        return;
    }

    const type = determineZoneType(selectedCardData.char_ids);
    let targetList = mainList, limit = 4, totalLimit = 60, zoneName = "メインデッキ";

    const limitVal = selectedCardData.card_limit;
    limit = (limitVal !== undefined && limitVal !== "" && limitVal !== null) ? parseInt(limitVal, 10) : 99;

    if (type === 'super_dimensional') { targetList = superDimList; totalLimit = 8; zoneName = "超次元ゾーン"; }
    else if (type === 'gr') { targetList = grList; totalLimit = 12; zoneName = "超GRゾーン"; }

    const comboNames = selectedCardData.combo_names || '';
    const selector = getCardSelector(name, comboNames);
    const count = targetList.querySelectorAll(selector).length;
    const totalCount = targetList.querySelectorAll('img').length;

    if (diff > 0) {
        if (count >= limit) {
            return alert(`${name} は${zoneName}に最大${limit}枚までです。`);
        }
        if (totalCount >= totalLimit) return alert(`${zoneName}は合計${totalLimit}枚までです。`);
        addCardToDeck(selectedCardData);
    } else {
        if (count <= 0) return;
        const target = targetList.querySelector(selector);
        if (target) {
            target.remove();
            if (activeClickedElement === target) activeClickedElement = null;
        }
    }
    
    updateDeckDisplay();
    document.getElementById('detail-qty').innerText = targetList.querySelectorAll(selector).length;
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
    
    let paddingBottom = 10;
    if (window.innerWidth <= 768) {
        paddingBottom = isSearchCollapsed ? 110 : 210;
    }
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
    const deckArea = document.getElementById('deck-area');
    
    const isCollapsed = searchSection.classList.toggle('collapsed');
    
    if (isCollapsed) {
        if (deckArea) deckArea.classList.add('search-collapsed');
    } else {
        if (deckArea) deckArea.classList.remove('search-collapsed');
    }
    
    // ボタンの位置・テキストを現在の開閉状態に基づいて再計算して同期
    syncSearchToggleButton();
    
    adjustMainDeckRows();
    setTimeout(adjustMainDeckRows, 300);
}

function syncSearchToggleButtonText() {
    const searchSection = document.getElementById('search-section');
    const container = document.getElementById('container');
    const btn = document.getElementById('search-toggle-btn');
    if (!btn) return;

    const isCollapsed = searchSection.classList.contains('collapsed');
    const isLeftHanded = container.classList.contains('left-handed');

    // スマホ表示時（ウィンドウ幅が768px以下）
    if (window.innerWidth <= 768) {
        btn.innerHTML = isCollapsed ? '▲ カード検索を開く' : '▼ 検索を隠す';
        return;
    }

    // デスクトップ表示時の左右判定に応じた文字矢印制御
    if (isLeftHanded) {
        // 左利き配置時（検索枠が左側）
        btn.style.right = 'auto';
        btn.style.left = isCollapsed ? '0px' : '380px'; // ★ 340px から 380px へ変更
        btn.style.borderRadius = '0 8px 8px 0'; // 角丸を右側に
        btn.innerHTML = isCollapsed ? '開く ▶' : '◀ 隠す';
    } else {
        // 通常配置時（検索枠が右側）
        btn.style.left = 'auto';
        btn.style.right = isCollapsed ? '0px' : '380px'; // ★ 340px から 380px へ変更
        btn.style.borderRadius = '8px 0 0 8px'; // 角丸を左側に
        btn.innerHTML = isCollapsed ? '◀ 開く' : '隠す ▶';
    }
}
// 画面幅リサイズ時にもボタン表示テキストを最適に更新する
window.addEventListener('resize', syncSearchToggleButtonText);

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
    if (currentFilters.q) {
        // 入力された文字列（生のクエリ）をそのまま送信します
        p.append('q', currentFilters.q);
    }
    
    p.append('scope', currentFilters.scope.join(',')); 
    if (currentFilters.civs.length) {
        p.append('civs', currentFilters.civs.join(','));
        p.append('civ_match_type', currentFilters.civ_match_type);
    }
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

    // ★追記：選択された商品ID配列をパラメータに結合して追加
    if (currentFilters.goods && currentFilters.goods.length) {
        p.append('goods', currentFilters.goods.join(','));
    }

    if (currentFilters.characteristics.length) p.append('characteristics', currentFilters.characteristics.join(','));
    p.append('characteristic_logic', currentFilters.characteristic_logic);
    if (currentFilters.cardtypes.length) p.append('cardtypes', currentFilters.cardtypes.join(','));
    p.append('cardtype_logic', currentFilters.cardtype_logic);

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
                    cardCache[card.card_id] = card; // ★追加：検索結果のカードをキャッシュに格納
                    const img = document.createElement('img');
                    img.src = getCardImagePath(card);
                    img.dataset.cardId = card.card_id;
                    img.dataset.cardName = card.card_name;
                    img.dataset.comboNames = card.combo_names || ''; // ★ 追記：コンビネーション名をデータ属性に格納
                    img.dataset.charIds = card.char_ids;
                    img.dataset.imagepath = card.imagepath;
                    img.dataset.cardLimit = card.card_limit !== undefined && card.card_limit !== null ? card.card_limit : ''; 
                    img.dataset.civ = card.civilization || card.civ || card.civ_ids || '';
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
    // PCなどの縦スクロール時に末尾（下部）に達したか判定、またはスマホ横スクロール時にも動作するように両対応
    const isMobile = window.innerWidth <= 768;
    
    if (isMobile) {
        if (resultsDiv.scrollLeft + resultsDiv.clientWidth >= resultsDiv.scrollWidth - 100) {
            loadMoreCards();
        }
    } else {
        if (resultsDiv.scrollTop + resultsDiv.clientHeight >= resultsDiv.scrollHeight - 100) {
            loadMoreCards();
        }
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

['race', 'ability', 'goods'].forEach(type => {
    const searchInput = document.getElementById(`${type}-list-search`);
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const q = this.value.toLowerCase();
            document.querySelectorAll(`#${type}-list-container .list-item`).forEach(el => {
                el.style.display = el.dataset.search.includes(q) ? 'flex' : 'none';
            });
        });
    }
});

function updateTriggerText(type) {
    const checked = Array.from(document.querySelectorAll(`.${type}-check:checked`));
    const trigger = document.getElementById(`${type}-trigger`);
    trigger.innerText = checked.length > 0 ? checked.map(el => el.dataset.name).join(', ') : '選択';
    trigger.style.color = checked.length > 0 ? '#333' : '#666';

    // 選択された項目のバッジ描画処理
    const badgesContainer = document.getElementById(`${type}-selected-badges`);
    if (badgesContainer) {
        badgesContainer.innerHTML = '';
        if (checked.length === 0) {
            badgesContainer.style.display = 'none';
        } else {
            badgesContainer.style.display = 'flex';
            checked.forEach(el => {
                const badge = document.createElement('div');
                badge.className = 'selected-badge';
                badge.innerHTML = `
                    <span>${escapeHTML(el.dataset.name)}</span>
                    <span class="selected-badge-remove" onclick="removeSubModalSelection('${type}', '${escapeSelectorValue(el.value)}')">×</span>
                `;
                badgesContainer.appendChild(badge);
            });
        }
    }
}

function clearSubSelection(type) {
    document.querySelectorAll(`.${type}-check`).forEach(el => el.checked = false);
    // ★追加：商品クリア時にシリーズチェックボックスも解除
    if (type === 'goods') {
        document.querySelectorAll('.era-check').forEach(el => el.checked = false);
    }
    updateTriggerText(type);
}
/**
 * ★ 新設：文明の「単色/多色」の変更による除外エリアの表示制御
 */
function toggleFilterCivType() {
    const isMulti = document.getElementById('filter-civ-multi').checked;
    const excludeArea = document.getElementById('filter-exclude-civ-area');
    excludeArea.style.display = isMulti ? 'grid' : 'none'; // ★ 'flex' から 'grid' に修正
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
    currentFilters.goods = Array.from(document.querySelectorAll('.goods-check:checked')).map(el => el.value);
    currentFilters.characteristics = Array.from(document.querySelectorAll('.characteristics-check:checked')).map(el => el.value);
    currentFilters.characteristic_logic = document.querySelector('input[name="characteristic_logic"]:checked').value;
    currentFilters.cardtypes = Array.from(document.querySelectorAll('.cardtype-check:checked')).map(el => el.value);
    currentFilters.cardtype_logic = document.querySelector('input[name="cardtype_logic"]:checked').value;

    currentFilters.reg = Array.from(document.querySelectorAll('.reg-check:checked')).map(el => el.value);
    
    toggleFilterModal();
    searchCards();
}

function clearAllFilters() {
    document.querySelectorAll('#filterModal input[type="checkbox"], #filterModal input[type="number"]').forEach(el => {
        el.checked = false;
        el.value = '';
    });
    
    // 文明初期状態の復元
    document.getElementById('filter-civ-single').checked = true;
    document.getElementById('filter-civ-multi').checked = true;
    document.querySelector('input[name="filter-civ-match-type"][value="include"]').checked = true;
    toggleFilterCivType();

    input.value = '';
    currentFilters = { 
        q: '', scope: ['name'], civs: [], cost_min: '', cost_max: '', 
        pow_min: '', pow_max: '', races: [], abilities: [], 
        characteristics: [], cardtypes: [],
        goods: [], // ★追記: 初期状態に空配列をセット
        race_logic: 'OR', ability_logic: 'OR', reg: [],
        characteristic_logic: 'OR', cardtype_logic: 'OR',
        civ_type: '', civ_match_type: 'include', exclude_civs: []
    };
    clearSubSelection('race');
    clearSubSelection('ability');
    clearSubSelection('characteristics');
    clearSubSelection('cardtype');
    clearSubSelection('goods'); // ★追記: 商品選択をクリア
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
        nameInput.value = isEdit ? initialDeckName : "";
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
            // ★追加：保存に成功したため、ローカルの下書きデータを消去
            localStorage.removeItem('unsaved_deck_draft');

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
                // 引数に slotId を追加
                addCardToDeck(data[0], 'special', slotId);
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
        const nameA = a.dataset.cardName || '';
        const nameB = b.dataset.cardName || '';

        // 「禁断 ～封印されしX～」の場合はコスト99として扱い、それ以外は元々のコストを取得
        const valA = nameA.includes('禁断 ～封印されしX～') ? 99 : (parseInt(a.dataset.cost, 10) || 0);
        const valB = nameB.includes('禁断 ～封印されしX～') ? 99 : (parseInt(b.dataset.cost, 10) || 0);

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
 * 対象 of カードリストを指定された条件で並び替えてDOMを再配置
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

/**
 * ひらがなをカタカナに変換する
 */
function hiraToKata(str) {
    return str.replace(/[\u3041-\u3096]/g, function(match) {
        const chr = match.charCodeAt(0) + 0x60;
        return String.fromCharCode(chr);
    });
}
function escapeSelectorValue(str) {
    if (!str) return '';
    return str.replace(/"/g, '\\"');
}
/**
 * 公開設定トグルボタンの値変更を、非表示チェックボックスに同期する
 */
function updatePublicValue(val) {
    document.getElementById('save-deck-public').checked = (val === 1);
}
/**
 * 検索結果からタップされたカードを適切なゾーンに自動追加する
 */
function addCardToDeckFromSearch(cardData) {
    const name = cardData.card_name;
    const charIds = (cardData.char_ids || '').split(',');
    
    // 上限値の解決（空またはnullなら 99枚＝無制限）
    const limitVal = cardData.card_limit;
    const nameLimit = (limitVal !== undefined && limitVal !== "" && limitVal !== null) ? parseInt(limitVal, 10) : 99;

    const isDoru = (name === '終焉の禁断 ドルマゲドンX' || name === 'FORBIDDEN STAR ～世界最後の日～');
    const isZero = (name === '零龍' || name === '滅亡の起源 零無');

    if (isDoru || isZero) {
        const slotId = isDoru ? 'slot-dolmagedon' : 'slot-zeroron';
        const slot = document.getElementById(slotId);
        if (slot && slot.querySelectorAll('img').length > 0) {
            alert("既に登録されています。");
            return;
        }
        addCardToDeck(cardData, 'special', slotId); // slotId を渡す
        return;
    }

    const comboNames = cardData.combo_names || '';

    if (charIds.includes('3') || charIds.includes('6')) {
        if (checkLimit(name, nameLimit, superDimList, null, 8, "超次元ゾーン", comboNames)) {
            addCardToDeck(cardData, 'super_dimensional');
        }
    } else if (charIds.includes('10')) {
        if (checkLimit(name, nameLimit, grList, null, 12, "超GRゾーン", comboNames)) {
            addCardToDeck(cardData, 'gr');
        }
    } else {
        if (checkLimit(name, nameLimit, mainList, null, 60, "メインデッキ", comboNames)) {
            addCardToDeck(cardData, 'main');
        }
    }
}
/**
 * バッジの「×」クリック時にチェックボックスの状態を連動して解除する処理
 */
function removeSubModalSelection(type, val) {
    const cb = document.querySelector(`.${type}-check[value="${val}"]`);
    if (cb) {
        cb.checked = false;
        updateTriggerText(type);
    }
}

/**
 * 特殊文字をエスケープするヘルパー関数
 */
function escapeHTML(str) {
    if (!str) return '';
    return str.replace(/&/g, '&amp;')
              .replace(/</g, '&lt;')
              .replace(/>/g, '&gt;')
              .replace(/"/g, '&quot;')
              .replace(/'/g, '&#39;');
}
/**
 * 同一カードを特定するための属性セレクタを取得
 * ツインパクトなど（comboNamesがある場合）は全カード名の一致を基準とし、通常カードは上面名一致かつcomboNamesが空を基準とする
 */
function getCardSelector(cardName, comboNames) {
    if (comboNames) {
        return `img[data-combo-names="${escapeSelectorValue(comboNames)}"]`;
    }
    return `img[data-card-name="${escapeSelectorValue(cardName)}"][data-combo-names=""]`;
}
// 利き手設定を保持して表示位置（左右）を切り替える
function toggleSearchPosition() {
    const container = document.getElementById('container');
    const isLeftHanded = container.classList.toggle('left-handed');
    
    localStorage.setItem('deck_builder_left_handed', isLeftHanded ? 'true' : 'false');
    
    // 配置切り替え時にもボタンの位置・テキストを同期
    syncSearchToggleButton();
}

// ボタンの位置・テキストを統合制御する処理
function syncSearchToggleButton() {
    const searchSection = document.getElementById('search-section');
    const container = document.getElementById('container');
    const btn = document.getElementById('search-toggle-btn');
    if (!btn) return;

    const isCollapsed = searchSection.classList.contains('collapsed');
    const isLeftHanded = container.classList.contains('left-handed');

    // スマホ表示時（幅768px以下）のスタイル復元
    if (window.innerWidth <= 768) {
        btn.style.left = '';
        btn.style.right = '20px';
        btn.style.top = '-32px';
        btn.style.transform = '';
        btn.style.borderRadius = '8px 8px 0 0';
        btn.innerHTML = isCollapsed ? '▲ カード検索を開く' : '▼ 検索を隠す';
        return;
    }

    // デスクトップ表示時の一括スタイル管理
    btn.style.top = '50%';
    btn.style.transform = 'translateY(-50%)';

    if (isLeftHanded) {
        // 左利き配置時（検索枠が左側）
        btn.style.right = 'auto';
        btn.style.left = isCollapsed ? '0px' : '380px'; // ★ 320px から 340px へ変更
        btn.style.borderRadius = '0 8px 8px 0'; // 角丸を右側に
        btn.innerHTML = isCollapsed ? '開く ▶' : '◀ 隠す';
    } else {
        // 通常配置時（検索枠が右側）
        btn.style.left = 'auto';
        btn.style.right = isCollapsed ? '0px' : '380px'; // ★ 320px から 340px へ変更
        btn.style.borderRadius = '8px 0 0 8px'; // 角丸を左側に
        btn.innerHTML = isCollapsed ? '◀ 開く' : '隠す ▶';
    }
}

// ページ読み込み完了時、リサイズ時にもボタンの状態を常に同期させる
window.addEventListener('DOMContentLoaded', () => {
    const isLeftHanded = localStorage.getItem('deck_builder_left_handed') === 'true';
    if (isLeftHanded) {
        document.getElementById('container').classList.add('left-handed');
    }
    syncSearchToggleButton();
});

window.addEventListener('resize', syncSearchToggleButton);
// ★追加：シリーズ一括選択（勝舞・勝太・ジョー・ウィン）のコントロール
document.querySelectorAll('.era-check').forEach(el => {
    el.addEventListener('change', function() {
        const era = this.value;
        const isChecked = this.checked;

        if (isChecked) {
            // 1. 他のシリーズチェックボックスをすべて解除（排他制御）
            document.querySelectorAll('.era-check').forEach(other => {
                if (other !== this) other.checked = false;
            });

            // 2. それまでにチェックされていた商品のチェックボックスをすべてクリア
            document.querySelectorAll('.goods-check').forEach(g => {
                g.checked = false;
            });

            // 3. 選択されたシリーズの範囲に合致する商品を一括でチェック
            document.querySelectorAll('.goods-check').forEach(g => {
                const goodsId = parseInt(g.value, 10);
                if (isNaN(goodsId)) return;

                let shouldCheck = false;
                if (era === 'shobu') {
                    shouldCheck = (goodsId >= 1 && goodsId <= 124);
                } else if (era === 'katta') {
                    shouldCheck = (goodsId >= 125 && goodsId <= 218);
                } else if (era === 'joe') {
                    shouldCheck = (goodsId >= 219 && goodsId <= 325) || goodsId === 340;
                } else if (era === 'win') {
                    shouldCheck = (goodsId >= 326);
                }

                if (shouldCheck) {
                    g.checked = true;
                }
            });
        } else {
            // シリーズチェックボックス自体が手動で解除された場合、すべての商品を一括解除
            document.querySelectorAll('.goods-check').forEach(g => {
                g.checked = false;
            });
        }

        // バッジ表示とセレクト表示（トリガーテキスト）の更新
        updateTriggerText('goods');
    });
});

// ★追加：ユーザーが商品を個別でチェック操作した場合、シリーズの選択を連動して解除する設定
const goodsContainer = document.getElementById('goods-list-container');
if (goodsContainer) {
    goodsContainer.addEventListener('change', function(e) {
        if (e.target.classList.contains('goods-check')) {
            document.querySelectorAll('.era-check').forEach(el => {
                el.checked = false;
            });
        }
    });
}
// --- オートセーブ（下書き保存・復元）ロジック ---

/**
 * デッキの現在の構成カードをシリアライズして配列で取得
 */
function getSerializedCards() {
    const cards = [];
    const serializeImg = (img, type) => {
        const cardId = img.dataset.cardId;
        const cached = cardCache[cardId];
        return {
            card_id: cardId,
            card_name: img.dataset.cardName || (cached ? cached.card_name : ''),
            combo_names: img.dataset.comboNames || (cached ? cached.combo_names : ''),
            char_ids: img.dataset.charIds || (cached ? cached.char_ids : ''),
            imagepath: img.dataset.imagepath || (cached ? cached.imagepath : ''),
            card_limit: img.dataset.cardLimit || (cached ? cached.card_limit : ''),
            civilization: img.dataset.civ || (cached ? cached.civilization : ''),
            cost: img.dataset.cost || (cached ? cached.cost : ''),
            card_type_in_deck: type
        };
    };

    mainList.querySelectorAll('img').forEach(img => cards.push(serializeImg(img, 'main')));
    superDimList.querySelectorAll('img').forEach(img => cards.push(serializeImg(img, 'super_dimensional')));
    grList.querySelectorAll('img').forEach(img => cards.push(serializeImg(img, 'gr')));
    document.querySelectorAll('.special-box.active img').forEach(img => {
        const item = serializeImg(img, 'special');
        item.slotId = img.parentNode.id; // スロットID（ドルマゲドンか零龍か）を保持
        cards.push(item);
    });

    return cards;
}

/**
 * ローカルストレージに下書きを保存
 */
function saveDraftToLocalStorage() {
    const cards = getSerializedCards();
    const nameEl = document.getElementById('save-deck-name');
    const formatEl = document.getElementById('save-deck-format');
    const deckName = nameEl ? nameEl.value : '';
    const formatId = formatEl ? formatEl.value : null;

    // デッキにカードが1枚もない場合は、古い下書きをクリーンアップ
    if (cards.length === 0) {
        localStorage.removeItem('unsaved_deck_draft');
        return;
    }

    const draft = {
        deckId: deckId, // グローバル変数deckId（新規はnull、編集は数値）
        deckName: deckName,
        formatId: formatId,
        cards: cards,
        updatedAt: Date.now()
    };
    localStorage.setItem('unsaved_deck_draft', JSON.stringify(draft));
}

/**
 * 起動時に自動セーブされた下書きの存在を確認し、復元を打診
 */
function checkAndRestoreDraft() {
    const draftStr = localStorage.getItem('unsaved_deck_draft');
    if (!draftStr) return;

    try {
        const draft = JSON.parse(draftStr);
        // 現在開いているデッキ（新規同士、あるいは同じデッキIDの編集同士）と一致するか判定
        const isSameDeck = (draft.deckId === deckId);

        if (isSameDeck && draft.cards && draft.cards.length > 0) {
            const confirmRestore = confirm("前回の未保存データが見つかりました。\n途中から作成・編集を再開しますか？");
            if (confirmRestore) {
                restoreDraft(draft);
            } else {
                // 不要と判断された場合は下書きをクリア
                localStorage.removeItem('unsaved_deck_draft');
            }
        }
    } catch (e) {
        console.error("ドラフトの復元処理中にエラーが発生しました", e);
    }
}

/**
 * 下書きからカードおよび入力フォームの値を復元
 */
function restoreDraft(draft) {
    // 現在の各スロットをクリア
    mainList.innerHTML = '';
    superDimList.innerHTML = '';
    grList.innerHTML = '';
    document.querySelectorAll('.special-box').forEach(slot => {
        slot.innerHTML = '';
        slot.classList.remove('active');
        slot.classList.add('empty');
        updateSaveButton(slot.id, false);
    });

    // カードの再配置
    draft.cards.forEach(card => {
        const reconstructedCard = {
            card_id: card.card_id,
            card_name: card.card_name,
            combo_names: card.combo_names,
            char_ids: card.char_ids,
            imagepath: card.imagepath,
            card_limit: card.card_limit,
            civilization: card.civilization,
            cost: card.cost
        };
        
        cardCache[card.card_id] = reconstructedCard;

        if (card.card_type_in_deck === 'special') {
            addCardToDeck(reconstructedCard, 'special', card.slotId);
        } else {
            addCardToDeck(reconstructedCard, card.card_type_in_deck);
        }
    });

    // デッキ名、フォーマットの復元
    if (draft.deckName) {
        const nameInput = document.getElementById('save-deck-name');
        if (nameInput) nameInput.value = draft.deckName;
    }
    if (draft.formatId) {
        const formatSelect = document.getElementById('save-deck-format');
        if (formatSelect) formatSelect.value = draft.formatId;
    }

    updateDeckDisplay();
    alert("未保存の作業データを復元しました。");
}
</script>