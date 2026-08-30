<?php
// セッションが開始されていない場合は開始する
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. 未ログインの場合は、ログイン画面（例: /login）へリダイレクト
if (!isset($_SESSION['user_id'])) {
    header("Location: /login");
    exit;
}

// 2. ログインはしているが、管理者（admin）または開発者（developer）の権限を持たない場合
if (!\Controllers\AuthController::checkAdminOrDeveloper()) {
    // 403 Forbidden のHTTPステータスコードを返却
    http_response_code(403);
    ?>
    <!DOCTYPE html>
    <html lang="ja">
    <head>
        <meta charset="UTF-8">
        <title>アクセス権限エラー</title>
        <style>
            body { font-family: sans-serif; background: #f0f0f0; margin: 0; padding: 50px 20px; display: flex; justify-content: center; align-items: center; min-height: 50vh; }
            .error-box { background: white; padding: 40px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); text-align: center; max-width: 450px; width: 100%; }
            h2 { color: #dc3545; margin-top: 0; font-size: 22px; }
            p { color: #555; font-size: 14px; line-height: 1.6; margin-bottom: 25px; }
            .btn-back { display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; font-weight: bold; font-size: 14px; transition: background 0.2s; }
            .btn-back:hover { background: #0056b3; }
        </style>
    </head>
    <body>
        <div class="error-box">
            <h2>アクセス権限エラー</h2>
            <p>このページ（カード情報の検索・編集）を閲覧、または操作するための権限がありません。管理者または開発者アカウントでログインし直してください。</p>
            <a href="/mydecks" class="btn-back">マイページ（マイデッキ）へ戻る</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}
?>
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
        .selected-badge {
            display: inline-flex;
            align-items: center;
            background: #e8f4fd;
            color: #007bff;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            margin-right: 6px;
            margin-bottom: 6px;
            font-weight: bold;
            border: 1px solid #bce0fd;
        }
        .selected-badge .badge-close {
            margin-left: 6px;
            cursor: pointer;
            font-weight: bold;
            color: #dc3545;
            font-size: 13px;
        }
        .selected-badge .badge-close:hover {
            color: #a71d2a;
        }
</style>
</head>
<body>

<div class="search-container">
    <h2>カード検索</h2>
    
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
        <!-- ★ 追加：最古バージョンのみ表示 -->
        <label style="margin-left: 15px; font-weight: bold; color: #333;">
            <input type="checkbox" id="oldest-only-check" onchange="triggerHelpSearch(true)"> 同名カードは最古のバージョンのみ
        </label>
    </div>

    <!-- ★ 追加：並び順選択セレクトボックス -->
    <div class="input-group" style="display: flex; align-items: center; gap: 10px; margin-top: 10px;">
        <label style="font-weight: bold; font-size: 14px; color: #333;">並び順:</label>
        <select id="help-sort-select" class="input-text" style="width: auto; padding: 6px 12px;" onchange="triggerHelpSearch(true)">
            <option value="newest" selected>発売日が新しい順</option>
            <option value="oldest">発売日が古い順</option>
        </select>
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
                <label style="color: #c9302c;"><input type="checkbox" class="civ-include-check" value="-1" onchange="triggerHelpSearch()"> 未設定</label>            </div>
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
        <!-- ★ 追加: レアリティ選択ボタン -->
        <button id="trigger-rarity" class="filter-btn" onclick="openHelpModal('rarity')">レアリティを選択</button>
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
<!-- 修正対象：カード詳細表示モーダル（helpDetailModal）の全体 -->
<div id="helpDetailModal" class="modal">
    <div class="modal-content detail-modal-content">
        <div class="modal-header">
            <h3>カード情報の編集（ヘルプ・仮）</h3>
            <span class="close-btn" onclick="closeHelpDetailModal()">&times;</span>
        </div>
        <div class="modal-body">
            <div class="detail-layout">
                <img id="detail-card-image" class="detail-img" src="/images/card/noimage.webp">
                <div class="detail-info">
                    <!-- 非表示でcard_idを保持 -->
                    <input type="hidden" id="edit-card-id">

                    <div class="info-row"><div class="info-label">カードID</div><div id="info-id" class="info-value" style="background:#eee; font-weight:bold;"></div></div>
                    
                    <div class="info-row">
                        <div class="info-label">カード名</div>
                        <input type="text" id="edit-name" class="input-text" style="font-size:14px; padding:6px;">
                    </div>
                    
                    <div class="info-row">
                        <div class="info-label">カードの読み</div>
                        <input type="text" id="edit-reading" class="input-text" style="font-size:14px; padding:6px;">
                    </div>
                    
                    <div class="info-row">
                        <div class="info-label">文明（複数選択）</div>
                        <div id="edit-civs-area" style="display:flex; gap:10px; flex-wrap:wrap; background:#f5f5f5; padding:8px; border-radius:4px;">
                            <label><input type="checkbox" class="edit-civ-check" value="1"> 光</label>
                            <label><input type="checkbox" class="edit-civ-check" value="2"> 水</label>
                            <label><input type="checkbox" class="edit-civ-check" value="3"> 闇</label>
                            <label><input type="checkbox" class="edit-civ-check" value="4"> 火</label>
                            <label><input type="checkbox" class="edit-civ-check" value="5"> 自然</label>
                            <label><input type="checkbox" class="edit-civ-check" value="6"> 無色</label>
                        </div>
                    </div>

                    <div class="info-row">
                        <div class="info-label">パワー (無限は 2147483647)</div>
                        <input type="number" id="edit-power" class="input-text" style="font-size:14px; padding:6px;">
                    </div>

                    <div class="info-row">
                        <div class="info-label">コスト (無限は 2147483647)</div>
                        <input type="number" id="edit-cost" class="input-text" style="font-size:14px; padding:6px;">
                    </div>

                    <div class="info-row">
                        <div class="info-label">テキスト</div>
                        <textarea id="edit-text" class="input-text" rows="4" style="font-size:14px; padding:6px; font-family:sans-serif; resize:vertical;"></textarea>
                    </div>

                    <div class="info-row">
                        <div class="info-label">フレーバーテキスト</div>
                        <textarea id="edit-flavor" class="input-text" rows="3" style="font-size:14px; padding:6px; font-family:sans-serif; resize:vertical;"></textarea>
                    </div>

                    <div class="info-row">
                        <div class="info-label">種族（複数選択）</div>
                        <!-- ★ 追加：選択中バッジ表示エリア -->
                        <div id="edit-selected-races" style="display: flex; flex-wrap: wrap; margin-bottom: 5px;"></div>
                        <!-- ★ 追加：モーダル内検索ボックス -->
                        <input type="text" id="edit-race-search" class="input-text" placeholder="種族を絞り込み検索..." style="font-size:12px; padding:5px; margin-bottom:5px;">
                        
                        <div id="edit-races-area" style="max-height:100px; overflow-y:auto; border:1px solid #ccc; padding:8px; border-radius:4px; background:#f5f5f5; font-size:13px;"></div>
                    </div>

                    <div class="info-row">
                        <div class="info-label">特殊能力（複数選択）</div>
                        <!-- ★ 追加：選択中バッジ表示エリア -->
                        <div id="edit-selected-abilities" style="display: flex; flex-wrap: wrap; margin-bottom: 5px;"></div>
                        <!-- ★ 追加：モーダル内検索ボックス -->
                        <input type="text" id="edit-ability-search" class="input-text" placeholder="特殊能力を絞り込み検索..." style="font-size:12px; padding:5px; margin-bottom:5px;">
                        
                        <div id="edit-abilities-area" style="max-height:100px; overflow-y:auto; border:1px solid #ccc; padding:8px; border-radius:4px; background:#f5f5f5; font-size:13px;"></div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">レアリティ（複数選択）</div>
                        <div id="edit-selected-rarities" style="display: flex; flex-wrap: wrap; margin-bottom: 5px;"></div>
                        <div id="edit-rarities-area" style="max-height:100px; overflow-y:auto; border:1px solid #ccc; padding:8px; border-radius:4px; background:#f5f5f5; font-size:13px;"></div>
                    </div>

                    <div class="info-row">
                        <div class="info-label">特殊タイプ（複数選択）</div>
                        <div id="edit-selected-characteristics" style="display: flex; flex-wrap: wrap; margin-bottom: 5px;"></div>
                        <div id="edit-characteristics-area" style="max-height:100px; overflow-y:auto; border:1px solid #ccc; padding:8px; border-radius:4px; background:#f5f5f5; font-size:13px;"></div>
                    </div>

                    <div class="info-row">
                        <div class="info-label">カードタイプ（複数選択）</div>
                        <div id="edit-selected-cardtypes" style="display: flex; flex-wrap: wrap; margin-bottom: 5px;"></div>
                        <div id="edit-cardtypes-area" style="max-height:100px; overflow-y:auto; border:1px solid #ccc; padding:8px; border-radius:4px; background:#f5f5f5; font-size:13px;"></div>
                    </div>
                    
                    <div class="info-row"><div class="info-label">収録商品（編集不可）</div><div id="info-goods" class="info-value" style="background:#eee;"></div></div>
                </div>
            </div>
        </div>
        <!-- 決定・キャンセルのフッターを追加 -->
        <div class="modal-footer" style="margin-top:10px;">
            <button onclick="closeHelpDetailModal()" style="padding: 10px 20px; background: #ccc; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; margin-right: 5px;">キャンセル</button>
            <button onclick="saveHelpDetail()" style="padding: 10px 20px; background: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: bold;">更新する</button>
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
    rarity: [], // ★ 追加: レアリティフィルターの初期状態
    goods: []
};

// マスターデータ（キャッシュ用）
let masterData = {
    race: [],
    ability: [],
    characteristic: [],
    cardtype: [],
    goods: [],
    rarity: []
};

// ページ読み込み完了時の初期化
window.addEventListener('DOMContentLoaded', () => {
    handleCivTypeChange(); // 文明の表示切り替えと初期検索
    loadAllMasterData();   // 各種マスターデータのロード
});

window.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        console.log("[Shortcut] Escapeキーが検出されました。モーダルを閉じます。");
        closeHelpModal();        // 絞り込みモーダルを閉じる
        closeHelpDetailModal();  // 詳細編集モーダルを閉じる
    }
});

/**
 * 文明の「単色/多色」の変更監視
 */
function handleCivTypeChange() {
    const isMulti = document.getElementById('civ-multi').checked;
    const excludeArea = document.getElementById('exclude-civ-area');
    excludeArea.style.display = isMulti ? 'flex' : 'none';
    triggerHelpSearch(true);
}

/**
 * 検索入力のデバウンス
 */
function debounceSearch() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        triggerHelpSearch(true);
    }, 350);
}

/**
 * ページ変更処理
 */
function changeHelpPage(diff) {
    if (window.event) {
        window.event.stopPropagation();
    }
    helpCurrentPage += diff;
    if (helpCurrentPage < 1) helpCurrentPage = 1;
    triggerHelpSearch(false);
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

    // triggerHelpSearch 関数内、パラメータ構築部分に追加
    const sortOrder = document.getElementById('help-sort-select').value;
    if (sortOrder) {
        params.append('sort', sortOrder);
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
    if (activeFilters.rarity.length) params.append('rarities', activeFilters.rarity.join(',')); // ★ 追加
    if (activeFilters.goods.length) params.append('goods', activeFilters.goods.join(','));

    // 最古バージョンフラグの取得
    const oldestOnly = document.getElementById('oldest-only-check').checked;

    // ページングパラメータを追加
    params.append('limit', helpPageLimit);
    params.append('offset', offset);
    if (oldestOnly) {
        params.append('oldest_only', '1');
    }

    // APIへリクエスト送信
    fetch('/api/cards/help-search?' + params.toString())
        .then(res => {
            // ステータスが正常（200〜299）でない場合はエラーをスローして catch に流す
            if (!res.ok) {
                throw new Error(`HTTP error! status: ${res.status}`);
            }
            return res.json();
        })
        .then(cards => {
            // 受信データが配列であることを確認
            if (!Array.isArray(cards)) {
                throw new TypeError("Received data is not an array");
            }

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
        .catch(err => {
            console.error("[Help API Response Error] データの取得に失敗しました:", err);
            // エラー時はグリッドにエラーメッセージを表示して処理を停止する
            const grid = document.getElementById('help-results-grid');
            if (grid) {
                grid.innerHTML = '<div style="grid-column: 1/-1; text-align: center; color: #dc3545; padding: 40px 0;">データの取得中にエラーが発生しました。</div>';
            }
        });
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
// loadAllMasterData() 関数を以下に差し替え

function loadAllMasterData() {
    // 1. 種族と特殊能力をロード
    const p1 = fetch('/api/master-data')
        .then(res => res.json())
        .then(data => {
            const racesList = data.races || [];
            masterData.race = [{ race_id: -1, race_name: "（未設定 / なし）" }, ...racesList];

            const abilitiesList = data.abilities || [];
            masterData.ability = [{ ability_id: -1, ability_name: "（未設定 / なし）" }, ...abilitiesList];
        })
        .catch(err => console.error("種族・能力マスタ取得エラー:", err));

    // 2. カードタイプ、特殊タイプ、収録商品、レアリティを一括ロード
    const p2 = fetch('/api/master-data-extended')
        .then(res => res.json())
        .then(data => {
            // カードタイプ：IDの昇順
            if (data.cardtypes) {
                const sorted = data.cardtypes.sort((a, b) => a.cardtype_id - b.cardtype_id);
                masterData.cardtype = [{ cardtype_id: -1, cardtype_name: "（未設定 / なし）" }, ...sorted];
            }
            // 特殊タイプ：IDの昇順
            if (data.characteristics) {
                const sorted = data.characteristics.sort((a, b) => a.characteristics_id - b.characteristics_id);
                masterData.characteristic = [{ characteristics_id: -1, characteristics_name: "（未設定 / なし）" }, ...sorted];
            }
            // 収録商品
            if (data.goods) {
                const sorted = data.goods.sort((a, b) => b.goods_id - a.goods_id);
                masterData.goods = [{ goods_id: -1, goods_name: "（未設定 / なし）" }, ...sorted];
            }
            // レアリティ：IDの昇順でロード
            if (data.rarities) {
                const sorted = data.rarities.sort((a, b) => a.rarity_id - b.rarity_id);
                masterData.rarity = [{ rarity_id: -1, rarity_name: "（未設定 / なし）" }, ...sorted];
            }
        })
        .catch(err => console.error("拡張マスタデータ取得エラー:", err));

    // 両方のAPI通信が完了した段階で、詳細モーダル内のHTML要素を動的に生成する
    Promise.all([p1, p2])
        .then(() => {
            renderEditRacesAndAbilities();
        })
        .catch(err => console.error("マスタデータの初期描画に失敗しました:", err));
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
    else if (type === 'rarity') { title.innerText = 'レアリティ選択'; searchWrapper.style.display = 'none'; } // ★ 追加
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
        const id = item.race_id || item.ability_id || item.characteristics_id || item.cardtype_id || item.rarity_id || item.goods_id || item.id;
        const name = item.race_name || item.ability_name || item.characteristics_name || item.cardtype_name || item.rarity_name || item.goods_name || item.name;        
        // ★ 追加：よみがなを取得して検索用キーワードを作成します
        const reading = item.reading || '';
        const searchKeyword = (name + reading).toLowerCase();

        const isChecked = activeFilters[currentModalType].includes(id);

        const div = document.createElement('label');
        div.className = 'list-item';
        div.dataset.name = name;
        div.dataset.search = searchKeyword; // ★ 検索用キーワードを保持
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
        const labels = { 
            race: '種族を選択', 
            ability: '特殊能力を選択', 
            characteristic: '特殊タイプを選択', 
            cardtype: 'カードタイプを選択', 
            rarity: 'レアリティを選択', // ★ 追加
            goods: '収録商品を選択' 
        };
        
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
    const modal = document.getElementById('helpDetailModal');
    
    // 【改善：体感速度向上】
    // データを取得する前に即座にモーダルを表示し、読み込み中であることを視覚的に伝える
    modal.style.display = 'block';
    document.getElementById('detail-card-image').src = '/images/card/noimage.webp';
    document.getElementById('info-id').innerText = '読み込み中...';
    document.getElementById('edit-name').value = '読み込み中...';
    document.getElementById('edit-reading').value = '読み込み中...';
    document.getElementById('edit-power').value = '';
    document.getElementById('edit-cost').value = '';
    document.getElementById('edit-text').value = '';
    document.getElementById('edit-flavor').value = '';
    document.getElementById('info-goods').innerText = '';

    // すべての入力チェック群を事前に一度リセット（DOM操作を最小化）
    document.querySelectorAll('.edit-civ-check, .edit-race-check, .edit-ability-check, .edit-rarity-check, .edit-characteristic-check, .edit-cardtype-check').forEach(el => el.checked = false);

    fetch('/api/cards/help-detail?card_id=' + cardId)
        .then(res => res.json())
        .then(card => {
            const path = card.imagepath.startsWith('/') ? card.imagepath : '/' + card.imagepath;
            document.getElementById('detail-card-image').src = '/images/card' + path;
            
            document.getElementById('edit-card-id').value = card.card_id;
            document.getElementById('info-id').innerText = card.card_id;
            document.getElementById('edit-name').value = card.card_name || '';
            document.getElementById('edit-reading').value = card.reading || '';
            document.getElementById('edit-power').value = card.pow !== null ? card.pow : '';
            document.getElementById('edit-cost').value = card.cost !== null ? card.cost : '';
            document.getElementById('edit-text').value = card.text ? card.text.replace(/\\n/g, '\n') : '';
            document.getElementById('edit-flavor').value = card.flavortext ? card.flavortext.replace(/\\n/g, '\n') : '';
            document.getElementById('info-goods').innerText = card.goods_name || 'なし';

            // 1. 文明チェックボックスの復元
            if (card.civilizations_ids) {
                const civIds = card.civilizations_ids.split(',').map(Number);
                civIds.forEach(id => {
                    const chk = document.querySelector(`.edit-civ-check[value="${id}"]`);
                    if (chk) chk.checked = true;
                });
            }

            // 2. 種族チェックボックスの復元
            if (card.race_ids) {
                const raceIds = card.race_ids.split(',').map(Number);
                raceIds.forEach(id => {
                    const chk = document.querySelector(`.edit-race-check[value="${id}"]`);
                    if (chk) chk.checked = true;
                });
            }

            // 3. 特殊能力チェックボックスの復元
            if (card.ability_ids) {
                const abilityIds = card.ability_ids.split(',').map(Number);
                abilityIds.forEach(id => {
                    const chk = document.querySelector(`.edit-ability-check[value="${id}"]`);
                    if (chk) chk.checked = true;
                });
            }

            // 4. レアリティチェックボックスの復元
            if (card.rarity_ids) {
                const rarityIds = card.rarity_ids.split(',').map(Number);
                rarityIds.forEach(id => {
                    const chk = document.querySelector(`.edit-rarity-check[value="${id}"]`);
                    if (chk) chk.checked = true;
                });
            } else if (card.rarity_id) {
                const chk = document.querySelector(`.edit-rarity-check[value="${card.rarity_id}"]`);
                if (chk) chk.checked = true;
            }

            // 5. 特殊タイプチェックボックスの復元
            if (card.characteristic_ids) {
                const charIds = card.characteristic_ids.split(',').map(Number);
                charIds.forEach(id => {
                    const chk = document.querySelector(`.edit-characteristic-check[value="${id}"]`);
                    if (chk) chk.checked = true;
                });
            }

            // 6. カードタイプチェックボックスの復元
            if (card.cardtype_ids) {
                const typeIds = card.cardtype_ids.split(',').map(Number);
                typeIds.forEach(id => {
                    const chk = document.querySelector(`.edit-cardtype-check[value="${id}"]`);
                    if (chk) chk.checked = true;
                });
            }

            updateSelectedBadges('race');
            updateSelectedBadges('ability');
            updateSelectedBadges('rarity');
            updateSelectedBadges('characteristic');
            updateSelectedBadges('cardtype'); 
        })
        .catch(err => {
            console.error("カード詳細の取得に失敗しました:", err);
            closeHelpDetailModal();
            alert("カードデータの取得に失敗しました。");
        });
}

function closeHelpDetailModal() {
    document.getElementById('helpDetailModal').style.display = 'none';
}
/**
 * 新規追加：詳細モーダル内に種族と特殊能力のチェックボックスリストを動的に出力
 */
// renderEditRacesAndAbilities() 関数を以下に差し替え

/**
 * ページ初期化時に、種族、特殊能力、その他のチェックボックスリストをあらかじめ動的に出力する
 */
function renderEditRacesAndAbilities() {
    // 1. 種族チェックボックスの生成
    const raceContainer = document.getElementById('edit-races-area');
    raceContainer.innerHTML = '';
    const races = masterData.race || [];
    races.forEach(r => {
        if (r.race_id === -1) return;
        const lbl = document.createElement('label');
        lbl.className = 'edit-checkbox-label';
        lbl.style.display = 'block';
        const reading = r.reading || '';
        lbl.dataset.search = (r.race_name + reading).toLowerCase();
        lbl.innerHTML = `<input type="checkbox" class="edit-race-check" value="${r.race_id}" data-name="${r.race_name}" onchange="updateSelectedBadges('race')"> ${r.race_name}`;
        raceContainer.appendChild(lbl);
    });

    // 2. 特殊能力チェックボックスの生成
    const abilityContainer = document.getElementById('edit-abilities-area');
    abilityContainer.innerHTML = '';
    const abilities = masterData.ability || [];
    abilities.forEach(a => {
        if (a.ability_id === -1) return;
        const lbl = document.createElement('label');
        lbl.className = 'edit-checkbox-label';
        lbl.style.display = 'block';
        const reading = a.reading || '';
        lbl.dataset.search = (a.ability_name + reading).toLowerCase();
        lbl.innerHTML = `<input type="checkbox" class="edit-ability-check" value="${a.ability_id}" data-name="${a.ability_name}" onchange="updateSelectedBadges('ability')"> ${a.ability_name}`;
        abilityContainer.appendChild(lbl);
    });

    // 3. レアリティチェックボックスの生成
    const rarityContainer = document.getElementById('edit-rarities-area');
    rarityContainer.innerHTML = '';
    const rarities = masterData.rarity || [];
    rarities.forEach(r => {
        if (r.rarity_id === -1) return;
        const lbl = document.createElement('label');
        lbl.className = 'edit-checkbox-label';
        lbl.style.display = 'block';
        lbl.dataset.search = (r.rarity_name || '').toLowerCase();
        lbl.innerHTML = `<input type="checkbox" class="edit-rarity-check" value="${r.rarity_id}" data-name="${r.rarity_name}" onchange="updateSelectedBadges('rarity')"> ${r.rarity_name}`;
        rarityContainer.appendChild(lbl);
    });

    // 4. 特殊タイプチェックボックスの生成
    const charContainer = document.getElementById('edit-characteristics-area');
    charContainer.innerHTML = '';
    const chars = masterData.characteristic || [];
    chars.forEach(c => {
        if (c.characteristics_id === -1) return;
        const lbl = document.createElement('label');
        lbl.className = 'edit-checkbox-label';
        lbl.style.display = 'block';
        lbl.dataset.search = (c.characteristics_name || '').toLowerCase();
        lbl.innerHTML = `<input type="checkbox" class="edit-characteristic-check" value="${c.characteristics_id}" data-name="${c.characteristics_name}" onchange="updateSelectedBadges('characteristic')"> ${c.characteristics_name}`;
        charContainer.appendChild(lbl);
    });

    // 5. カードタイプチェックボックスの生成
    const typeContainer = document.getElementById('edit-cardtypes-area');
    typeContainer.innerHTML = '';
    const types = masterData.cardtype || [];
    types.forEach(t => {
        if (t.cardtype_id === -1) return;
        const lbl = document.createElement('label');
        lbl.className = 'edit-checkbox-label';
        lbl.style.display = 'block';
        lbl.dataset.search = (t.cardtype_name || '').toLowerCase();
        lbl.innerHTML = `<input type="checkbox" class="edit-cardtype-check" value="${t.cardtype_id}" data-name="${t.cardtype_name}" onchange="updateSelectedBadges('cardtype')"> ${t.cardtype_name}`;
        typeContainer.appendChild(lbl);
    });

    setupModalSearchFilter('race');
    setupModalSearchFilter('ability');
}

/**
 * 新規追加：選択されたチェックボックスから「×」付きのバッジエリアを同期描画する
 */
// updateSelectedBadges(type) 関数を以下に差し替え

function updateSelectedBadges(type) {
    const areaMap = {
        race: 'edit-selected-races',
        ability: 'edit-selected-abilities',
        rarity: 'edit-selected-rarities',
        characteristic: 'edit-selected-characteristics',
        cardtype: 'edit-selected-cardtypes'
    };

    const selectedArea = document.getElementById(areaMap[type]);
    if (!selectedArea) return;
    
    selectedArea.innerHTML = '';

    const checkedBoxes = Array.from(document.querySelectorAll(`.edit-${type}-check:checked`));
    
    if (checkedBoxes.length === 0) {
        selectedArea.innerHTML = '<span style="color:#999; font-size:11px;">未選択</span>';
        return;
    }

    checkedBoxes.forEach(chk => {
        const id = chk.value;
        const name = chk.dataset.name;

        const badge = document.createElement('span');
        badge.className = 'selected-badge';
        badge.innerHTML = `
            ${name}
            <span class="badge-close" onclick="removeEditSelection('${type}', ${id})">&times;</span>
        `;
        selectedArea.appendChild(badge);
    });
}

/**
 * 新規追加：バッジの「×」が押されたときにチェックボックスを外し、バッジを再描画する
 */
function removeEditSelection(type, id) {
    const chk = document.querySelector(`.edit-${type}-check[value="${id}"]`);
    if (chk) {
        chk.checked = false;
        updateSelectedBadges(type);
    }
}

/**
 * 新規追加：検索テキストボックスの入力に合わせて、リストをリアルタイムに絞り込む
 */
function setupModalSearchFilter(type) {
    const searchInput = document.getElementById(`edit-${type}-search`);
    searchInput.value = '';
    
    searchInput.oninput = () => {
        const q = searchInput.value.toLowerCase().trim();
        const labels = document.querySelectorAll(`#edit-${type === 'race' ? 'races' : 'abilities'}-area .edit-checkbox-label`);
        
        labels.forEach(lbl => {
            const searchKey = lbl.dataset.search || '';
            lbl.style.display = searchKey.includes(q) ? 'block' : 'none';
        });
    };
}

/**
 * 新規追加：編集したカード情報をサーバーに送信して更新
 */
// saveHelpDetail() 関数のリクエストデータの取得部分を以下のように修正

function saveHelpDetail() {
    const cardId = document.getElementById('edit-card-id').value;
    if (!cardId) return;

    const cardNameInput = document.getElementById('edit-name').value.trim();
    const readingInput = document.getElementById('edit-reading').value.trim();

    const data = {
        card_id: cardId,
        card_name: cardNameInput,
        reading: readingInput,
        pow: document.getElementById('edit-power').value,
        cost: document.getElementById('edit-cost').value,
        text: document.getElementById('edit-text').value.trim(), 
        flavortext: document.getElementById('edit-flavor').value.trim().replace(/\n/g, '\\n'),
        civilizations: Array.from(document.querySelectorAll('.edit-civ-check:checked')).map(el => parseInt(el.value, 10)),
        races: Array.from(document.querySelectorAll('.edit-race-check:checked')).map(el => parseInt(el.value, 10)),
        abilities: Array.from(document.querySelectorAll('.edit-ability-check:checked')).map(el => parseInt(el.value, 10)),
        rarities: Array.from(document.querySelectorAll('.edit-rarity-check:checked')).map(el => parseInt(el.value, 10)),
        characteristics: Array.from(document.querySelectorAll('.edit-characteristic-check:checked')).map(el => parseInt(el.value, 10)),
        cardtypes: Array.from(document.querySelectorAll('.edit-cardtype-check:checked')).map(el => parseInt(el.value, 10))
    };

    if (!data.card_name) {
        return alert("カード名は必須です。");
    }

    // 更新中ボタンの活性／非活性化（二重送信の防止）
    const updateBtn = document.querySelector('button[onclick="saveHelpDetail()"]');
    if (updateBtn) {
        updateBtn.disabled = true;
        updateBtn.innerText = "更新中...";
    }

    fetch('/api/cards/help-update', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
    .then(res => res.json())
    .then(resData => {
        if (resData.success) {
            // 【改善：高速化のための重要変更】
            // 重い全体の再検索「triggerHelpSearch」を行わず、変更された該当カードのデータを
            // フロントエンド(DOM)上で部分的に即時書き換えます。これにより遅延を感じさせず完了します。
            const targetImg = document.getElementById(`card-img-${cardId}`);
            if (targetImg) {
                targetImg.alt = cardNameInput;
                targetImg.dataset.cardName = cardNameInput;
                targetImg.dataset.reading = readingInput;
            }

            closeHelpDetailModal();
            alert("カード情報を更新しました！");
        } else {
            alert("更新に失敗しました: " + resData.error);
        }
    })
    .catch(err => {
        console.error("更新エラー:", err);
        alert("通信中にエラーが発生しました。");
    })
    .finally(() => {
        if (updateBtn) {
            updateBtn.disabled = false;
            updateBtn.innerText = "更新する";
        }
    });
}
</script>
</body>
</html>
