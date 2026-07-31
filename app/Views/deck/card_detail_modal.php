<!-- app/Views/deck/card_detail_modal.php -->
<style>
    /* --- カード詳細・全体制御スタイル --- */
    #cardDetailModal {
        display: none;
        position: fixed;
        z-index: 10000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.8);
    }

    .detail-content {
        background: white;
        margin: 5vh auto;
        padding: 25px;
        border-radius: 12px;
        position: relative;
        box-sizing: border-box;
        width: 850px;
        max-width: 90%;
        display: flex;
        flex-direction: column;
    }

    .detail-close-btn {
        position: absolute;
        right: 20px;
        top: 15px;
        cursor: pointer;
        font-size: 30px;
        z-index: 10;
        line-height: 1;
    }

    /* --- PC時のレイアウト (Gridシステム) --- */
    .detail-grid {
        display: grid;
        grid-template-columns: 280px 1fr;
        grid-template-rows: auto 1fr;
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

    .detail-image-wrapper {
        grid-column: 1 / 2;
        grid-row: 1 / 3;
        width: 280px;
        height: 390px;
        flex-shrink: 0;
        display: flex;
        justify-content: center;
        align-items: center;
        background: #fafafa;
        border-radius: 8px;
        overflow: hidden;
        box-sizing: border-box;
    }

    #detail-main-img {
        max-width: 100%;
        max-height: 100%;
        width: auto;
        height: auto;
        object-fit: contain;
        border-radius: 8px;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15);
    }

    .detail-desc {
        grid-column: 2 / 3;
        grid-row: 2 / 3;
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
        max-height: 320px;
        overflow-y: auto;
    }

    /* --- 両面カード・サムネイル切り替え --- */
    #combination-section {
        border-top: 1px solid #eee;
        padding-top: 15px;
    }

    #combination-section h4 {
        margin: 0 0 10px 0;
    }

    #detail-combination-list {
        display: flex;
        overflow-x: auto;
        gap: 12px;
        padding: 10px 5px;
    }

    .combination-thumb {
        height: 100px;
        width: auto;
        cursor: pointer;
        border: 3px solid transparent;
        border-radius: 4px;
        flex-shrink: 0;
        object-fit: contain;
    }

    .combination-thumb.selected {
        border-color: #007bff;
    }

    /* --- スマートフォン環境（レスポンシブ） --- */
    @media (max-width: 768px) {
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

        .detail-image-wrapper {
            order: 2;
            width: 100% !important;
            max-width: 200px !important; /* スマホ時は少し小さめに表示 */
            height: auto !important;
            aspect-ratio: 110/154;
            margin: 0 auto !important;
        }

        #detail-main-img {
            width: 100% !important;
            height: auto !important;
        }

        .detail-desc {
            order: 3;
            width: 100% !important;
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

        #combination-section h4 {
            margin: 10px 0 5px 0 !important;
            font-size: 0.9rem;
        }

        .combination-thumb {
            height: 80px; /* スマホ時は切り替え画像を少し縮小 */
        }
    }
</style>

<!-- カード詳細・表裏切り替えモーダル -->
<div id="cardDetailModal">
    <div class="detail-content">
        <!-- 閉じるボタン -->
        <span class="detail-close-btn" onclick="closeDetailModal()">&times;</span>
        
        <!-- PC時はGrid、スマホ時はFlexboxで並び順(order)を制御 -->
        <div class="detail-grid">
            <h2 id="detail-name"></h2>
            
            <div class="detail-image-wrapper">
                <img id="detail-main-img" src="">
            </div>
            
            <div class="detail-desc">
                <div id="detail-text"></div>
            </div>
        </div>
        
        <!-- 両面（裏表）切り替えエリア -->
        <div id="combination-section" style="display: none;">
            <h4>両面カード（表・裏）</h4>
            <div id="detail-combination-list"></div>
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