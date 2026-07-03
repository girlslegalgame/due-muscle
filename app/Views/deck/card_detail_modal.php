<!-- app/Views/deck/card_detail_modal.php -->
<style>
    /* --- カード詳細・表裏切り替え用（共通スタイル） --- */
    .detail-left-common {
        width: 280px !important;
        height: 390px !important;      /* ★通常のカードの縦幅に固定 */
        flex-shrink: 0;
        display: flex !important;
        justify-content: center;
        align-items: center;
        background: #fafafa;
        border-radius: 8px;
        overflow: hidden;
        box-sizing: border-box;
    }
    #detail-main-img {
        max-width: 100% !important;
        max-height: 100% !important;   /* ★縦幅を100%に合わせる */
        width: auto !important;        /* ★横幅は比率に合わせて自動計算 */
        height: auto !important;
        object-fit: contain !important; /* ★比率を完全維持 */
        border-radius: 8px;
        box-shadow: 0 4px 10px rgba(0,0,0,0.15);
    }
    .combination-thumb {
        height: 100px !important;      /* 縦幅を100pxに固定 */
        width: auto !important;        /* ★横幅は画像の比率に自動追従 */
        cursor: pointer;
        border: 3px solid transparent;
        border-radius: 4px;
        flex-shrink: 0;
        object-fit: contain !important; /* ★引き伸ばしを禁止し、比率を維持 */
    }
    .combination-thumb.selected {
        border-color: #007bff;
    }
</style>
<!-- カード詳細・表裏切り替えモーダル（共通HTML） -->
<div id="cardDetailModal" style="display: none; position: fixed; z-index: 10000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8);">
    <div class="detail-content" style="background: white; margin: 5vh auto; padding: 25px; border-radius: 12px; position: relative; box-sizing: border-box; width: 850px; max-width: 90%; display: flex; flex-direction: column;">
        <span onclick="closeDetailModal()" style="position:absolute; right:20px; top:10px; cursor:pointer; font-size:30px; line-height: 1;">&times;</span>
        <div class="detail-top" style="display: flex; gap: 25px; margin-bottom: 20px;">
            <div class="detail-left-common">
                <img id="detail-main-img" src="">
            </div>
            <div class="detail-right" style="flex: 1; min-width: 0;">
                <h2 id="detail-name" style="margin: 0 0 15px 0; font-size: 1.5rem; border-bottom: 2px solid #eee; padding-bottom: 10px;"></h2>
                <div id="detail-text" style="white-space: pre-wrap; word-wrap: break-word; background: #f8f9fa; padding: 15px; border-radius: 5px; font-size: 0.95rem; line-height: 1.6; max-height: 250px; overflow-y: auto;"></div>
            </div>
        </div>
        <div id="combination-section" style="display: none; border-top: 1px solid #eee; padding-top: 15px;">
            <h4 style="margin: 0 0 10px 0;">両面カード（表・裏）</h4>
            <div id="detail-combination-list" style="display: flex; overflow-x: auto; gap: 12px; padding: 10px 5px;"></div>
        </div>
    </div>
</div>
<script>
// --- カード詳細（表裏切り替え付き）の共通制御JavaScript ---
let currentCombinationCards = [];

function openCardDetail(cardId) {
    // APIから表裏（combination）に紐づくカード群を一括取得
    fetch('/api/cards/combination?card_id=' + cardId)
        .then(res => res.json())
        .then(data => {
            currentCombinationCards = data;
            const target = data.find(c => c.card_id == cardId) || data[0];
            renderCardDetail(target);
            document.getElementById('cardDetailModal').style.display = 'block';
        })
        .catch(err => {
            alert('カード詳細の読み込みに失敗しました。');
            console.error(err);
        });
}

function renderCardDetail(card) {
    document.getElementById('detail-name').innerText = card.card_name;
    document.getElementById('detail-text').innerText = card.text || "効果なし";
    
    const imgEl = document.getElementById('detail-main-img');
    const path = card.imagepath.startsWith('/') ? card.imagepath : '/' + card.imagepath;
    imgEl.src = '/images/card' + path;
    imgEl.onerror = function() { this.src = '/images/card/noimage.webp'; this.onerror = null; };

    const comboSection = document.getElementById('combination-section');
    const comboList = document.getElementById('detail-combination-list');
    
    if (currentCombinationCards.length > 1) {
        comboSection.style.display = 'block';
        comboList.innerHTML = '';
        currentCombinationCards.forEach(c => {
            const vImg = document.createElement('img');
            const vPath = c.imagepath.startsWith('/') ? c.imagepath : '/' + c.imagepath;
            vImg.src = '/images/card' + vPath;
            vImg.className = 'combination-thumb';
            if (c.card_id == card.card_id) vImg.classList.add('selected');
            
            vImg.onclick = () => renderCardDetail(c); // クリックで切り替え
            vImg.onerror = function() { this.src = '/images/card/noimage.webp'; this.onerror = null; };
            comboList.appendChild(vImg);
        });
    } else {
        comboSection.style.display = 'none';
    }
}

function closeDetailModal() {
    document.getElementById('cardDetailModal').style.display = 'none';
}
</script>