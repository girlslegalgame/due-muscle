// public/js/regulation_badge.js
const regulationBadgeCache = new Map();

function applyRegulationBadge(img, regulation) {
    if (!regulation) return;

    // ★ 日本語文字列・数値どちらでも判定できるように正規化
    let regType = 0;
    if (regulation === '殿堂' || regulation === 2 || regulation === '2') {
        regType = 2; // 殿堂
    } else if (regulation === 'プレミアム殿堂' || regulation === 3 || regulation === '3') {
        regType = 3; // P殿
    }

    if (regType === 0) return;

    const doDraw = () => {
        if (!img.src || img.src.startsWith('data:image/png') || img.dataset.regApplied === String(regType)) return;

        const cacheKey = img.src + '_' + regType;
        if (regulationBadgeCache.has(cacheKey)) {
            img.dataset.regApplied = String(regType);
            img.src = regulationBadgeCache.get(cacheKey);
            return;
        }

        try {
            const canvas = document.createElement('canvas');
            const w = img.naturalWidth || 220;
            const h = img.naturalHeight || 308;
            if (w === 0 || h === 0) return;

            canvas.width = w;
            canvas.height = h;
            const ctx = canvas.getContext('2d');

            ctx.drawImage(img, 0, 0, w, h);

            const scale = w / 220;
            ctx.save();

            // ★ 縦幅を広げ、両端も長めに確保（角のハミ出しを完全防止）
            const ribbonH = 56 * scale;
            const ribbonW = 180 * scale;

            // ★ 原点(0, 0)を完全に超えて角を100%覆うオフセット
            const offset = 18 * scale;
            ctx.translate(offset, offset);
            ctx.rotate(-Math.PI / 4);

            // ★ リボン背景（P殿: 黒 #1a1a1a, 殿堂: 赤 #d9232e）
            ctx.fillStyle = (regType === 3) ? '#1a1a1a' : '#d9232e';
            ctx.fillRect(-ribbonW / 2, -ribbonH / 2, ribbonW, ribbonH);

            // ★ カード内側の境界ライン（P殿: 金色 #ffd700, 殿堂: 白）
            ctx.strokeStyle = (regType === 3) ? '#ffd700' : 'rgba(255, 255, 255, 0.85)';
            ctx.lineWidth = 2.5 * scale;
            ctx.beginPath();
            ctx.moveTo(-ribbonW / 2, ribbonH / 2);
            ctx.lineTo(ribbonW / 2, ribbonH / 2);
            ctx.stroke();

            // ★ テキスト設定
            ctx.fillStyle = (regType === 3) ? '#ffd700' : '#ffffff';
            // ★ 文字サイズを大きく（約20px相当）
            ctx.font = `bold ${Math.round(20 * scale)}px "Hiragino Kaku Gothic ProN", "Meiryo", sans-serif`;
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            
            ctx.shadowColor = 'rgba(0, 0, 0, 0.7)';
            ctx.shadowBlur = 3 * scale;

            // ★ 拡大した帯の中央に美しく収まる位置
            const textOffsetY = 8 * scale;
            ctx.fillText(regType === 3 ? 'P殿' : '殿堂', 0, textOffsetY);

            ctx.restore();

            const dataUrl = canvas.toDataURL('image/png');
            regulationBadgeCache.set(cacheKey, dataUrl);
            img.dataset.regApplied = String(regType);
            img.src = dataUrl;
        } catch (e) {
            console.warn('[regulation_badge] バッジ描画エラー:', e);
        }
    };

    if (img.complete && img.naturalWidth > 0) {
        doDraw();
    } else {
        img.addEventListener('load', () => {
            if (img.dataset.regApplied === String(regType)) return;
            doDraw();
        }, { once: true });
    }
}