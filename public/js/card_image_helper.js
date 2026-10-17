/**
 * カード画像・ダミーカード共通生成ヘルパー
 */
const CARD_CIV_COLORS = {
    '1': '#f1c40f', // 光
    '2': '#3498db', // 水
    '3': '#34495e', // 闇
    '4': '#e74c3c', // 火
    '5': '#2ecc71', // 自然
    '6': '#95a5a6'  // ゼロ
};

/**
 * ダミーカードのCanvas画像を生成してDataURLを返す
 */
function generateDummyCardImage(name, cost, civIdsStr, cardType = 'normal', pName = '', pCost = '', pCivIdsStr = '') {
    const canvas = document.createElement('canvas');
    canvas.width = 220; canvas.height = 308;
    const ctx = canvas.getContext('2d');

    // --- A. ツインパクト（実物準拠：斜め線 ＆ 右側下面コスト） ---
    if (cardType === 'twinpact') {
        const topCivs = (civIdsStr || '').toString().split(',').filter(Boolean);
        const btmCivs = (pCivIdsStr || civIdsStr || '').toString().split(',').filter(Boolean);
        const topColor = topCivs.length > 0 ? (CARD_CIV_COLORS[topCivs[0]] || '#95a5a6') : '#95a5a6';
        const btmColor = btmCivs.length > 0 ? (CARD_CIV_COLORS[btmCivs[0]] || '#95a5a6') : '#95a5a6';

        ctx.fillStyle = '#111'; ctx.fillRect(0, 0, 220, 308);
        const splitLeftY = 188, splitRightY = 152;

        // 上面
        ctx.save();
        ctx.beginPath();
        ctx.moveTo(8, 8); ctx.lineTo(212, 8); ctx.lineTo(212, splitRightY); ctx.lineTo(8, splitLeftY); ctx.closePath();
        ctx.fillStyle = topColor; ctx.fill();
        ctx.fillStyle = 'rgba(255,255,255,0.9)';
        ctx.beginPath(); ctx.moveTo(14, 14); ctx.lineTo(206, 14); ctx.lineTo(206, splitRightY - 5); ctx.lineTo(14, splitLeftY - 5); ctx.closePath(); ctx.fill();

        ctx.beginPath(); ctx.arc(38, 40, 17, 0, Math.PI * 2); ctx.fillStyle = '#2c3e50'; ctx.fill();
        ctx.fillStyle = '#fff'; ctx.font = 'bold 16px sans-serif'; ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
        ctx.fillText((cost !== '' && cost !== null && cost !== undefined) ? cost : '?', 38, 41);

        ctx.fillStyle = '#111'; ctx.font = 'bold 14px sans-serif'; ctx.textAlign = 'center'; ctx.textBaseline = 'top';
        _dummyCardWrapText(ctx, name || '上面カード名', 120, 68, 150, 18);
        ctx.restore();

        // 下面
        ctx.save();
        ctx.beginPath();
        ctx.moveTo(8, splitLeftY); ctx.lineTo(212, splitRightY); ctx.lineTo(212, 300); ctx.lineTo(8, 300); ctx.closePath();
        ctx.fillStyle = btmColor; ctx.fill();
        ctx.fillStyle = 'rgba(255,255,255,0.9)';
        ctx.beginPath(); ctx.moveTo(14, splitLeftY + 5); ctx.lineTo(206, splitRightY + 5); ctx.lineTo(206, 294); ctx.lineTo(14, 294); ctx.closePath(); ctx.fill();

        // ★下面コスト（右側・境界線下）：完全な円形サークル
        const btmCostVal = (pCost !== '' && pCost !== null && pCost !== undefined) ? pCost : '?';
        const btmCostX = 184;
        const btmCostY = splitRightY + 22;

        ctx.beginPath();
        ctx.arc(btmCostX, btmCostY, 17, 0, Math.PI * 2);
        ctx.fillStyle = btmColor;
        ctx.fill();
        ctx.lineWidth = 2.5;
        ctx.strokeStyle = '#fff';
        ctx.stroke();

        ctx.fillStyle = '#fff';
        ctx.font = 'bold 16px sans-serif';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText(btmCostVal, btmCostX, btmCostY);

        ctx.fillStyle = '#111'; ctx.font = 'bold 13px sans-serif'; ctx.textAlign = 'center'; ctx.textBaseline = 'top';
        _dummyCardWrapText(ctx, pName || '下面カード名', 95, splitLeftY + 28, 125, 18);
        ctx.restore();

        // 仕切り線 & 外枠
        ctx.lineWidth = 5; ctx.strokeStyle = '#111';
        ctx.beginPath(); ctx.moveTo(4, splitLeftY); ctx.lineTo(216, splitRightY); ctx.stroke();
        ctx.lineWidth = 8; ctx.strokeRect(4, 4, 212, 300);

        return canvas.toDataURL('image/png');
    }

    // --- B. 通常カード & 両面カード ---
    const civIds = (civIdsStr || '').toString().split(',').filter(Boolean);
    if (civIds.length === 1) {
        ctx.fillStyle = CARD_CIV_COLORS[civIds[0]] || '#95a5a6';
    } else if (civIds.length > 1) {
        const grad = ctx.createLinearGradient(0, 0, 220, 308);
        civIds.forEach((id, idx) => grad.addColorStop(idx / (civIds.length - 1), CARD_CIV_COLORS[id] || '#95a5a6'));
        ctx.fillStyle = grad;
    } else {
        ctx.fillStyle = '#95a5a6';
    }
    ctx.fillRect(0, 0, 220, 308);

    ctx.lineWidth = 8; ctx.strokeStyle = '#2c3e50'; ctx.strokeRect(4, 4, 212, 300);
    ctx.fillStyle = 'rgba(255, 255, 255, 0.9)'; ctx.fillRect(16, 16, 188, 276);

    // コストサークル
    const costVal = (cost !== undefined && cost !== null && cost !== '') ? cost : '?';
    ctx.beginPath(); ctx.arc(42, 46, 18, 0, Math.PI * 2); ctx.fillStyle = '#2c3e50'; ctx.fill();
    ctx.fillStyle = '#fff'; ctx.font = 'bold 18px sans-serif'; ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
    ctx.fillText(costVal, 42, 47);

    if (cardType === 'double') {
        ctx.fillStyle = '#17a2b8'; ctx.font = 'bold 11px sans-serif'; ctx.textAlign = 'right';
        ctx.fillText('⟲ 両面', 194, 46);
    }

    ctx.fillStyle = '#111'; ctx.font = 'bold 15px sans-serif'; ctx.textAlign = 'center'; ctx.textBaseline = 'top';
    _dummyCardWrapText(ctx, name || 'カード名未設定', 110, 82, 160, 22);

    return canvas.toDataURL('image/png');
}

function _dummyCardWrapText(ctx, text, x, y, maxWidth, lineHeight) {
    const words = (text || '').split('');
    let line = '';
    for (let n = 0; n < words.length; n++) {
        let testLine = line + words[n];
        if (ctx.measureText(testLine).width > maxWidth && n > 0) {
            ctx.fillText(line, x, y);
            line = words[n];
            y += lineHeight;
        } else { line = testLine; }
    }
    ctx.fillText(line, x, y);
}

/**
 * 任意のカードオブジェクトから正しい画像URL（正規画像 or ダミー生成画像）を返す共通関数
 */
function getCardImageUrl(card) {
    if (!card) return '/images/card/noimage.webp';
    if (card.image_data_url) return card.image_data_url;

    // ダミーカード判定
    const isDummy = card.is_dummy == 1 || (typeof card.card_id === 'string' && card.card_id.startsWith('dummy_')) || !card.imagepath;
    if (isDummy) {
        const type = card.dummy_card_type || (card.twinpact == 1 ? 'twinpact' : 'normal');
        return generateDummyCardImage(
            card.card_name,
            card.cost,
            card.civ_ids || card.civilization || '',
            type,
            card.partner_card_name || card.dummy_partner_name || '',
            card.partner_cost || card.dummy_partner_cost || '',
            card.partner_civ_ids || card.dummy_partner_civ_ids || ''
        );
    }

    if (card.imagepath.startsWith('data:image')) return card.imagepath;
    const path = card.imagepath.startsWith('/') ? card.imagepath : '/' + card.imagepath;
    return '/images/card' + path;
}