<!-- app/Views/tools/ccfolia.php -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="/js/ccfolia_exporter.js"></script>

<style>
.cc-container {
    max-width: 1100px;
    margin: 0 auto;
    padding: 10px;
}
.cc-tabs {
    display: flex;
    gap: 4px;
    border-bottom: 2px solid #333;
    align-items: flex-end;
    flex-wrap: wrap;
}
.cc-tab {
    padding: 8px 16px;
    background: #e2e8f0;
    border-radius: 6px 6px 0 0;
    cursor: pointer;
    font-weight: bold;
    font-size: 0.9rem;
    color: #4a5568;
    user-select: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.cc-tab.active {
    background: #333;
    color: #fff;
}
.cc-tab-add {
    background: #28a745;
    color: #fff;
    font-size: 1.1rem;
    padding: 5px 14px;
}
.cc-tab-add:hover { background: #218838; }
.cc-tab-close {
    font-size: 0.9rem;
    cursor: pointer;
    margin-left: 4px;
    color: #cbd5e0;
}
.cc-tab-close:hover { color: #ff6b6b; }

.cc-dropzone {
    border: 2px dashed #a0aec0;
    border-radius: 8px;
    padding: 24px;
    text-align: center;
    background: #fafafa;
    cursor: pointer;
    margin: 15px 0;
    transition: background 0.2s, border-color 0.2s;
}
.cc-dropzone.dragover {
    background: #edf2f7;
    border-color: #3182ce;
}

.cc-card-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
    gap: 12px;
    margin-top: 15px;
}
.cc-card-item {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    padding: 8px;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.08);
    position: relative;
}
.cc-card-thumb {
    width: 100%;
    aspect-ratio: 51 / 73;
    object-fit: cover;
    border-radius: 4px;
    background: #eee;
}
.cc-card-delete {
    position: absolute;
    top: 4px;
    right: 4px;
    background: rgba(220, 53, 69, 0.85);
    color: #fff;
    border: none;
    border-radius: 50%;
    width: 22px;
    height: 22px;
    font-size: 12px;
    cursor: pointer;
    line-height: 1;
}
.cc-qty-control {
    display: flex;
    align-items: center;
    gap: 4px;
    width: 100%;
}
.cc-qty-btn {
    width: 26px;
    height: 26px;
    border: 1px solid #cbd5e0;
    background: #edf2f7;
    border-radius: 4px;
    cursor: pointer;
    font-weight: bold;
}
.cc-qty-input {
    flex: 1;
    width: 100%;
    text-align: center;
    padding: 2px;
    font-size: 0.9rem;
    border: 1px solid #cbd5e0;
    border-radius: 4px;
}
</style>

<div class="cc-container">
    <h2>ココフォリア出力データ作成ツール</h2>
    <p style="color: #666; font-size: 0.9rem;">
        任意の画像ファイルをカードとして登録し、ココフォリアに直接ドラッグ＆ドロップできるZIPファイルを生成します。<br>
        デュエマに限らず、あらゆるTCGやボードゲームのデッキ作成にお使いいただけます。
    </p>

    <!-- 基本設定 -->
    <div style="display: flex; gap: 15px; align-items: center; margin-bottom: 15px; flex-wrap: wrap;">
        <label style="font-weight: bold;">デッキ名:
            <input type="text" id="cc-deck-name" value="カスタムデッキ" style="padding: 6px 10px; font-size: 1rem; border: 1px solid #ccc; border-radius: 4px;">
        </label>
        <button type="button" class="create-btn" style="background: #28a745;" onclick="exportCustomZip()">
            ZIPを出力する
        </button>
    </div>

    <!-- タブエリア -->
    <div class="cc-tabs" id="cc-tabs">
        <div class="cc-tab active" data-target="main" onclick="switchCcTab('main')">
            メインデッキ (<span id="count-main">0</span>枚)
        </div>
        <div class="cc-tab" data-target="standalone" onclick="switchCcTab('standalone')">
            デッキ外カード (<span id="count-standalone">0</span>枚)
        </div>
        <button type="button" class="cc-tab cc-tab-add" onclick="addSubDeckTab()" title="サブデッキを追加">＋</button>
    </div>

    <!-- 画像アップロードドロップゾーン -->
    <div class="cc-dropzone" id="cc-dropzone" onclick="document.getElementById('cc-file-input').click()">
        <p style="margin: 0; font-weight: bold; color: #4a5568;">
            クリックして画像を選択、またはここに画像をドラッグ＆ドロップ
        </p>
        <span style="font-size: 0.8rem; color: #718096;">複数画像の一括アップロードに対応（PNG, JPG, WEBP）</span>
        <input type="file" id="cc-file-input" multiple accept="image/*" style="display: none;" onchange="handleFileSelect(this.files)">
    </div>

    <!-- カード一覧エリア -->
    <div id="cc-panels">
        <div class="cc-panel" id="panel-main">
            <div class="cc-card-grid" id="grid-main"></div>
        </div>
        <div class="cc-panel" id="panel-standalone" style="display: none;">
            <div class="cc-card-grid" id="grid-standalone"></div>
        </div>
    </div>
</div>

<script>
// 状態管理
const state = {
    activeTab: 'main',
    main: [],          // [{ id, file, url, count }]
    standalone: [],
    subDecks: {}       // { sub_1: { name: 'サブデッキ1', cards: [] } }
};
let subDeckSeq = 0;

function switchCcTab(targetId) {
    state.activeTab = targetId;
    document.querySelectorAll('.cc-tab').forEach(t => {
        if (!t.classList.contains('cc-tab-add')) {
            t.classList.toggle('active', t.dataset.target === targetId);
        }
    });
    document.querySelectorAll('.cc-panel').forEach(p => {
        p.style.display = (p.id === `panel-${targetId}`) ? 'block' : 'none';
    });
}

function addSubDeckTab() {
    subDeckSeq++;
    const key = `sub_${subDeckSeq}`;
    const defaultName = `サブデッキ${subDeckSeq}`;
    state.subDecks[key] = { name: defaultName, cards: [] };

    // タブ要素生成
    const addBtn = document.querySelector('.cc-tab-add');
    const tab = document.createElement('div');
    tab.className = 'cc-tab';
    tab.dataset.target = key;
    tab.onclick = () => switchCcTab(key);
    tab.innerHTML = `
        <span contenteditable="true" onblur="renameSubDeck('${key}', this.innerText)" onclick="event.stopPropagation()">${defaultName}</span>
        (<span id="count-${key}">0</span>枚)
        <span class="cc-tab-close" onclick="removeSubDeck('${key}', event)">×</span>
    `;
    addBtn.parentNode.insertBefore(tab, addBtn);

    // パネル生成
    const panel = document.createElement('div');
    panel.className = 'cc-panel';
    panel.id = `panel-${key}`;
    panel.style.display = 'none';
    panel.innerHTML = `<div class="cc-card-grid" id="grid-${key}"></div>`;
    document.getElementById('cc-panels').appendChild(panel);

    switchCcTab(key);
}

function removeSubDeck(key, e) {
    e.stopPropagation();
    if (!confirm('このサブデッキを削除しますか？')) return;
    delete state.subDecks[key];
    document.querySelector(`.cc-tab[data-target="${key}"]`)?.remove();
    document.getElementById(`panel-${key}`)?.remove();
    switchCcTab('main');
}

function renameSubDeck(key, newName) {
    if (state.subDecks[key]) {
        state.subDecks[key].name = newName.trim() || 'サブデッキ';
    }
}

// ドロップゾーン設定
const dropzone = document.getElementById('cc-dropzone');
['dragenter', 'dragover'].forEach(name => {
    dropzone.addEventListener(name, (e) => { e.preventDefault(); dropzone.classList.add('dragover'); });
});
['dragleave', 'drop'].forEach(name => {
    dropzone.addEventListener(name, (e) => { e.preventDefault(); dropzone.classList.remove('dragover'); });
});
dropzone.addEventListener('drop', (e) => {
    if (e.dataTransfer.files) handleFileSelect(e.dataTransfer.files);
});

function handleFileSelect(files) {
    const targetKey = state.activeTab;
    const targetList = (targetKey === 'main' || targetKey === 'standalone') 
        ? state[targetKey] 
        : state.subDecks[targetKey]?.cards;

    if (!targetList) return;

    Array.from(files).forEach(file => {
        if (!file.type.startsWith('image/')) return;
        const item = {
            id: 'c_' + Math.random().toString(36).substr(2, 9),
            file: file,
            url: URL.createObjectURL(file),
            count: 1
        };
        targetList.push(item);
    });

    renderCurrentGrid();
}

function renderCurrentGrid() {
    const key = state.activeTab;
    const targetList = (key === 'main' || key === 'standalone') 
        ? state[key] 
        : state.subDecks[key]?.cards;
    const grid = document.getElementById(`grid-${key}`);
    if (!grid || !targetList) return;

    grid.innerHTML = '';
    targetList.forEach(card => {
        const div = document.createElement('div');
        div.className = 'cc-card-item';
        div.innerHTML = `
            <button type="button" class="cc-card-delete" onclick="removeCard('${key}', '${card.id}')">×</button>
            <img src="${card.url}" class="cc-card-thumb" alt="">
            <div class="cc-qty-control">
                <button type="button" class="cc-qty-btn" onclick="updateQty('${key}', '${card.id}', -1)">-</button>
                <input type="number" class="cc-qty-input" value="${card.count}" min="1" onchange="setQty('${key}', '${card.id}', this.value)">
                <button type="button" class="cc-qty-btn" onclick="updateQty('${key}', '${card.id}', 1)">+</button>
            </div>
        `;
        grid.appendChild(div);
    });

    updateCounts();
}

function updateQty(key, cardId, diff) {
    const list = (key === 'main' || key === 'standalone') ? state[key] : state.subDecks[key]?.cards;
    const target = list?.find(c => c.id === cardId);
    if (target) {
        target.count = Math.max(1, target.count + diff);
        renderCurrentGrid();
    }
}

function setQty(key, cardId, val) {
    const list = (key === 'main' || key === 'standalone') ? state[key] : state.subDecks[key]?.cards;
    const target = list?.find(c => c.id === cardId);
    if (target) {
        target.count = Math.max(1, parseInt(val, 10) || 1);
        renderCurrentGrid();
    }
}

function removeCard(key, cardId) {
    const list = (key === 'main' || key === 'standalone') ? state[key] : state.subDecks[key]?.cards;
    if (list) {
        const idx = list.findIndex(c => c.id === cardId);
        if (idx !== -1) list.splice(idx, 1);
        renderCurrentGrid();
    }
}

function updateCounts() {
    const sumCount = (list) => list.reduce((acc, c) => acc + (parseInt(c.count, 10) || 0), 0);
    const mCnt = sumCount(state.main);
    const sCnt = sumCount(state.standalone);
    document.getElementById('count-main').innerText = mCnt;
    document.getElementById('count-standalone').innerText = sCnt;

    Object.keys(state.subDecks).forEach(k => {
        const el = document.getElementById(`count-${k}`);
        if (el) el.innerText = sumCount(state.subDecks[k].cards);
    });
}

async function exportCustomZip() {
    const deckName = document.getElementById('cc-deck-name').value.trim() || 'カスタムデッキ';
    const mainCards = state.main.map(c => ({ blob: c.file, count: c.count, memo: '' }));
    const standaloneCards = state.standalone.map(c => ({ blob: c.file, count: c.count, memo: '' }));
    const subDecks = Object.keys(state.subDecks).map(k => ({
        name: state.subDecks[k].name,
        cards: state.subDecks[k].cards.map(c => ({ blob: c.file, count: c.count, memo: '' }))
    }));

    if (mainCards.length === 0 && standaloneCards.length === 0 && subDecks.every(s => s.cards.length === 0)) {
        alert('出力対象のカードが1枚もありません。');
        return;
    }

    try {
        const zipBlob = await CcfoliaExporter.buildZip({
            deckName: deckName,
            mainDeck: mainCards,
            subDecks: subDecks,
            standalones: standaloneCards
        });

        const link = document.createElement('a');
        link.href = URL.createObjectURL(zipBlob);
        link.download = `${deckName}.zip`;
        link.click();
    } catch (e) {
        console.error(e);
        alert('ZIP生成中にエラーが発生しました。');
    }
}
</script>