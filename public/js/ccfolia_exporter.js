/**
 * public/js/ccfolia_exporter.js
 * ココフォリア用ZIP生成共通ライブラリ
 */
const CcfoliaExporter = (function () {
    function generateId(length = 20) {
        const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        let res = '';
        for (let i = 0; i < length; i++) res += chars.charAt(Math.floor(Math.random() * chars.length));
        return res;
    }

    async function calculateSha256(blob) {
        const buffer = await blob.arrayBuffer();
        if (window.crypto && window.crypto.subtle) {
            try {
                const hashBuffer = await crypto.subtle.digest('SHA-256', buffer);
                return Array.from(new Uint8Array(hashBuffer)).map(b => b.toString(16).padStart(2, '0')).join('');
            } catch (e) {}
        }
        // Pure JS Fallback (省略なし正規実装)
        function r(v, a) { return (v >>> a) | (v << (32 - a)); }
        let w = [], ascii = new Uint8Array(buffer), len = ascii.length, words = [];
        let hash = [0x6a09e667, 0xbb67ae85, 0x3c6ef372, 0xa54ff53a, 0x510e527f, 0x9b05688c, 0x1f83d9ab, 0x5be0cd19];
        let k = [
            0x428a2f98, 0x71374491, 0xb5c0fbcf, 0xe9b5dba5, 0x3956c25b, 0x59f111f1, 0x923f82a4, 0xab1c5ed5,
            0xd807aa98, 0x12835b01, 0x243185be, 0x550c7dc3, 0x72be5d74, 0x80deb1fe, 0x9bdc06a7, 0xc19bf174,
            0xe49b69c1, 0xefbe4786, 0x0fc19dc6, 0x240ca1cc, 0x2de92c6f, 0x4a7484aa, 0x5cb0a9dc, 0x76f988da,
            0x983e5152, 0xa831c66d, 0xb00327c8, 0xbf597fc7, 0xc6e00bf3, 0xd5a79147, 0x06ca6351, 0x14292967,
            0x27b70a85, 0x2e1b2138, 0x4d2c6dfc, 0x53380d13, 0x650a7354, 0x766a0abb, 0x81c2c92e, 0x92722c85,
            0xa2bfe8a1, 0xa81a664b, 0xc24b8b70, 0xc76c51a3, 0xd192e819, 0xd6990624, 0xf40e3585, 0x106aa070,
            0x19a4c116, 0x1e376c08, 0x2748774c, 0x34b0bcb5, 0x391c0cb3, 0x4ed8aa4a, 0x5b9cca4f, 0x682e6ff3,
            0x748f82ee, 0x78a5636f, 0x84c87814, 0x8cc70208, 0x90befffa, 0xa4506ceb, 0xbef9a3f7, 0xc67178f2
        ];
        for (let i = 0; i < len; i++) words[i >> 2] |= ascii[i] << (24 - (i % 4) * 8);
        words[len >> 2] |= 0x80 << (24 - (len % 4) * 8);
        words[(((len + 8) >> 6) << 4) + 15] = len * 8;
        for (let i = 0; i < words.length; i += 16) {
            let [a, b, c, d, e, f, g, h] = hash;
            for (let j = 0; j < 64; j++) {
                if (j < 16) w[j] = words[j + i] | 0;
                else {
                    let g0 = r(w[j - 15], 7) ^ r(w[j - 15], 18) ^ (w[j - 15] >>> 3);
                    let g1 = r(w[j - 2], 17) ^ r(w[j - 2], 19) ^ (w[j - 2] >>> 10);
                    w[j] = (w[j - 16] + g0 + w[j - 7] + g1) | 0;
                }
                let t1 = (h + (r(e, 6) ^ r(e, 11) ^ r(e, 25)) + ((e & f) ^ (~e & g)) + k[j] + w[j]) | 0;
                let t2 = ((r(a, 2) ^ r(a, 13) ^ r(a, 22)) + ((a & b) ^ (a & c) ^ (b & c))) | 0;
                h = g; g = f; f = e; e = (d + t1) | 0; d = c; c = b; b = a; a = (t1 + t2) | 0;
            }
            hash = [(hash[0] + a)|0, (hash[1] + b)|0, (hash[2] + c)|0, (hash[3] + d)|0, (hash[4] + e)|0, (hash[5] + f)|0, (hash[6] + g)|0, (hash[7] + h)|0];
        }
        let res = '';
        for (let i = 0; i < 8; i++) for (let j = 3; j >= 0; j--) res += ((hash[i] >> (8 * j)) & 255).toString(16).padStart(2, '0');
        return res;
    }

    async function registerImage(zip, resourcesObj, blob) {
        const hash = await calculateSha256(blob);
        let ext = 'png';
        if (blob.type === 'image/webp') ext = 'webp';
        else if (blob.type === 'image/jpeg') ext = 'jpeg';
        const filename = `${hash}.${ext}`;
        if (!resourcesObj[filename]) {
            const buffer = await blob.arrayBuffer();
            zip.file(filename, buffer);
            resourcesObj[filename] = { type: blob.type || 'image/png' };
        }
        return filename;
    }

    /**
     * 単一カードのmemo文字列を組み立てる（index.htmlと完全同一仕様）
     */
    function buildSingleCardMemo(cardData) {
        if (!cardData) return '';
        let line1Parts = [];
        const cardName = cardData.card_name ? cardData.card_name.trim() : '';
        if (cardName) line1Parts.push(cardName);

        let civs = [];
        const rawCivs = cardData.civ_ids || cardData.civilization || '';
        if (rawCivs) {
            civs = String(rawCivs).split(',').map(id => parseInt(id.trim())).filter(id => !isNaN(id));
        }
        const civNamesMap = { 1: '光', 2: '水', 3: '闇', 4: '火', 5: '自然', 6: 'ゼロ' };
        if (civs.length > 0) {
            civs.sort((a, b) => a - b);
            let civStr = civs.map(id => civNamesMap[id]).filter(Boolean).join('/');
            if (civStr) line1Parts.push(civStr + '文明');
        }

        if (cardData.cost !== null && cardData.cost !== undefined && cardData.cost !== '') {
            line1Parts.push(`(${cardData.cost})`);
        }
        let line1 = line1Parts.join('　');

        let cardTypeStr = cardData.typename || cardData.cardtype_names || cardData.cardtype_name || '';
        let raceStr = cardData.race_names || cardData.race_name || '';
        let cardTypeIds = cardData.cardtype_ids ? String(cardData.cardtype_ids).split(',').map(id => parseInt(id.trim())).filter(id => !isNaN(id)) : [];
        let raceIds = cardData.race_ids ? String(cardData.race_ids).split(',').map(id => parseInt(id.trim())).filter(id => !isNaN(id)) : [];

        let isNonCreatureWithNoRace = (cardTypeIds.length > 0 && !cardTypeIds.includes(1)) && (raceIds.length === 0 || (raceIds.length === 1 && raceIds[0] === 1));
        let line2Head = isNonCreatureWithNoRace ? cardTypeStr : [cardTypeStr, raceStr || (cardTypeIds.length > 0 && !cardTypeIds.includes(1) ? '(種族なし)' : '')].filter(Boolean).join('：');
        let line2Pow = (cardData.pow !== null && cardData.pow !== undefined && cardData.pow !== '') ? cardData.pow : '';
        let line2 = (line2Head && line2Pow) ? `${line2Head}　${line2Pow}` : (line2Head || line2Pow);

        let text = cardData.text ? cardData.text.trim() : '';
        let memoLines = [];
        if (line1) memoLines.push(line1);
        if (line2) memoLines.push(line2);
        if (text) memoLines.push(text);

        return memoLines.join('\n');
    }

    /**
     * ツインパクト・ハイパーモード等の面構成を考慮した完成版memoを生成
     */
    function createCardMemo(card) {
        if (!card) return '';

        // 面配列（combination_sides）が存在する場合（create.html用）
        if (Array.isArray(card.combination_sides) && card.combination_sides.length >= 2) {
            const isTwin = card.twinpact == 1 || card.twinpact === '1' || card.combination_sides.some(s => s.twinpact == 1);
            if (isTwin) {
                const topMemo = buildSingleCardMemo(card.combination_sides[0]);
                const btmMemo = buildSingleCardMemo(card.combination_sides[1]);
                return `上面\n${topMemo}\n\n下面\n${btmMemo}`;
            }
        }

        // ダミーカードのツインパクトの場合
        if (card.is_dummy && (card.dummy_card_type === 'twinpact' || card.dummy_partner_name)) {
            const topData = {
                card_name: card.card_name,
                cost: card.cost,
                civ_ids: card.civ_ids,
                typename: card.typename || '',
                race_names: card.race_names || '',
                text: ''
            };
            const btmData = {
                card_name: card.dummy_partner_name,
                cost: card.dummy_partner_cost,
                civ_ids: card.dummy_partner_civ_ids || card.civ_ids,
                typename: card.partner_typename || '',
                race_names: card.partner_race_names || '',
                text: ''
            };
            return `上面\n${buildSingleCardMemo(topData)}\n\n下面\n${buildSingleCardMemo(btmData)}`;
        }

        // index.html互換形式（partner_* プロパティが存在する場合）
        const isTwinpact = card.twinpact == 1 || card.twinpact === '1' || card.twinpact === true;
        const hasCombination = !!card.combination_id && !!card.partner_card_id;
        const isSelfHyper = card.hypermode == 1 || card.hypermode === '1';
        const isPartnerHyper = card.partner_hypermode == 1 || card.partner_hypermode === '1';
        const isHypermode = hasCombination && (isSelfHyper || isPartnerHyper);

        if (hasCombination && (isTwinpact || isHypermode)) {
            const selfId = parseInt(card.card_id);
            const partnerId = parseInt(card.partner_card_id);

            const selfData = { ...card, card_id: selfId, is_hyper: isSelfHyper };
            const partnerData = {
                card_id: partnerId,
                card_name: card.partner_card_name,
                cost: card.partner_cost,
                pow: card.partner_pow,
                text: card.partner_text || '',
                civ_ids: card.partner_civ_ids,
                typename: card.partner_typename,
                cardtype_ids: card.partner_cardtype_ids,
                race_ids: card.partner_race_ids,
                race_names: card.partner_race_names,
                is_hyper: isPartnerHyper
            };

            const minCard = selfId < partnerId ? selfData : partnerData;
            const otherCard = selfId < partnerId ? partnerData : selfData;

            if (isHypermode) {
                const hyperCard = minCard.is_hyper ? minCard : otherCard;
                const baseCard = minCard.is_hyper ? otherCard : minCard;
                const baseMemo = buildSingleCardMemo(minCard);
                let hyperOnlyText = hyperCard.text || '';
                if (baseCard.text) hyperOnlyText = hyperOnlyText.split(baseCard.text).join('').trim();
                const hyperPowStr = hyperCard.pow ? `　${hyperCard.pow}` : '';
                return `${baseMemo}\n\nハイパーモード${hyperPowStr}${hyperOnlyText ? '\n' + hyperOnlyText : ''}`;
            }

            if (isTwinpact) {
                return `上面\n${buildSingleCardMemo(minCard)}\n\n下面\n${buildSingleCardMemo(otherCard)}`;
            }
        }

        return buildSingleCardMemo(card);
    }

    /**
     * @param {Object} options
     * @param {string} options.deckName
     * @param {Array<{blob: Blob, memo?: string, count: number}>} options.mainDeck
     * @param {Array<{name: string, cards: Array<{blob: Blob, memo?: string, count: number}>}>} options.subDecks
     * @param {Array<{blob: Blob, memo?: string, count: number, width?: number, height?: number}>} options.standalones
     */
    async function buildZip(options) {
        const defaultWidth = parseFloat(options.cardWidth) || 4;
        const defaultHeight = parseFloat(options.cardHeight) || 6;

        const zip = new JSZip();

        try {
            const tokenRes = await fetch('/images/.token');
            if (tokenRes.ok) {
                const tokenBlob = await tokenRes.blob();
                if (tokenBlob.size > 0) {
                    zip.file('.token', await tokenBlob.arrayBuffer());
                }
            }
        } catch (e) {}

        const resourcesObj = {};
        const decksObj = {};
        const standaloneItemsObj = {};

        // 1. メインデッキ
        if (options.mainDeck && options.mainDeck.length > 0) {
            const items = {};
            for (const item of options.mainDeck) {
                const imgFilename = await registerImage(zip, resourcesObj, item.blob);
                const count = parseInt(item.count, 10) || 1;
                for (let i = 0; i < count; i++) {
                    items[generateId()] = { imageUrl: imgFilename, memo: item.memo || "" };
                }
            }
            if (Object.keys(items).length > 0) {
                decksObj[generateId()] = {
                    x: -2, y: -3, z: 99, zIndex: 1, 
                    width: defaultWidth, height: defaultHeight,
                    locked: false, freezed: true, coverImageUrl: null, items: items
                };
            }
        }

        // 2. サブデッキ群
        let posX = 3;
        if (options.subDecks && options.subDecks.length > 0) {
            for (const sub of options.subDecks) {
                if (!sub.cards || sub.cards.length === 0) continue;
                const items = {};
                for (const item of sub.cards) {
                    const imgFilename = await registerImage(zip, resourcesObj, item.blob);
                    const count = parseInt(item.count, 10) || 1;
                    for (let i = 0; i < count; i++) {
                        items[generateId()] = { imageUrl: imgFilename, memo: item.memo || "" };
                    }
                }
                if (Object.keys(items).length > 0) {
                    decksObj[generateId()] = {
                        x: posX, y: -3, z: 99, zIndex: 1, 
                        width: defaultWidth, height: defaultHeight,
                        locked: false, freezed: true, coverImageUrl: null, items: items
                    };
                    posX += 5;
                }
            }
        }

        // 3. デッキ外の独立カード (standalones)
        const standaloneX = -8;
        const standaloneY = -3;

        if (options.standalones && options.standalones.length > 0) {
            for (const item of options.standalones) {
                const imgFilename = await registerImage(zip, resourcesObj, item.blob);
                const count = parseInt(item.count, 10) || 1;
                const w = item.width || defaultWidth;
                const h = item.height || defaultHeight;
                for (let i = 0; i < count; i++) {
                    const id = generateId();
                    standaloneItemsObj[id] = {
                        x: standaloneX, y: standaloneY, z: 999, angle: 0, width: w, height: h,
                        deckId: null, locked: false, visible: true, closed: false,
                        withoutOwner: false, freezed: true, type: "object", active: true,
                        memo: item.memo || "", imageUrl: imgFilename, coverImageUrl: null,
                        clickAction: null, order: 1
                    };
                }
            }
        }

        const dataJson = {
            meta: { version: "1.1.0" },
            entities: {
                room: {}, items: standaloneItemsObj, decks: decksObj,
                notes: {}, characters: {}, effects: {}, scenes: {},
                savedatas: {}, snapshots: {}
            },
            resources: resourcesObj
        };

        zip.file('__data.json', JSON.stringify(dataJson, null, 2));
        return await zip.generateAsync({ type: 'blob' });
    }

    return { 
        buildZip,
        generateId,
        calculateSha256,
        createCardMemo,
        buildSingleCardMemo
    };
})();