package models

import (
	"database/sql"
	"fmt"
	"strings"
	"sync"
	"time"
)

type Deck struct {
	db *sql.DB
}

func NewDeck(db *sql.DB) *Deck {
	return &Deck{db: db}
}

// 特定のユーザーが作成したデッキ一覧を取得する（フォーマット名、サムネイル画像付き）
func (d *Deck) GetByUserId(userId int) ([]map[string]interface{}, error) {
	query := `SELECT d.*, f.format_name, cd.imagepath AS thumbnail_imagepath
            FROM decks d 
            JOIN formats f ON d.format_id = f.format_id 
            LEFT JOIN card_detail cd ON d.thumbnail_card_id = cd.card_id
            WHERE d.user_id = ? 
            ORDER BY d.updated_at DESC`

	rows, err := d.db.Query(query, userId)
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	return fetchAllAssoc(rows)
}

// 特定のデッキに含まれるカードリストを取得する（モーダル表示用・ZIP出力用）
func (d *Deck) GetCardsByDeckId(deckId int) ([]map[string]interface{}, error) {
	// ★ 1. 後から追加された同名カードの自動置換 ＆ limit 超過分の自動カット調整（高速キャッシュ照合版）
	func() {
		defer func() {
			_ = recover()
		}()

		rowsDummy, errCheck := d.db.Query(`
			SELECT sort_order, dummy_name, COALESCE(dummy_card_type, 'normal'), COALESCE(dummy_partner_name, '')
			FROM deck_cards
			WHERE deck_id = ? AND dummy_name IS NOT NULL
			ORDER BY sort_order ASC
		`, deckId)
		if errCheck != nil {
			return
		}
		type dummyRow struct {
			sortOrder   int
			name        string
			cardType    string
			partnerName string
		}
		var dummies []dummyRow
		for rowsDummy.Next() {
			var dr dummyRow
			if err := rowsDummy.Scan(&dr.sortOrder, &dr.name, &dr.cardType, &dr.partnerName); err == nil {
				dummies = append(dummies, dr)
			}
		}
		rowsDummy.Close()

		if len(dummies) == 0 {
			return
		}

		cache := getOrBuildDummyMatchCache(d.db)
		if cache == nil {
			return
		}

		type CardGroup struct {
			limit  int
			orders []int
		}

		groupedByCard := make(map[int]*CardGroup)
		var groupedKeys []int

		dummyMatchCacheMu.RLock()
		for _, dr := range dummies {
			normMain := normalizeDummyMatchName(dr.name)
			normPartner := normalizeDummyMatchName(dr.partnerName)
			var matched dummyMatchTarget
			var ok bool

			if dr.cardType == "twinpact" && normPartner != "" {
				matched, ok = cache.comboTwinMap[normMain+"|||"+normPartner]
			} else if dr.cardType == "double" && normPartner != "" {
				matched, ok = cache.comboDoubleMap[normMain+"|||"+normPartner]
			} else if dr.cardType == "normal" {
				matched, ok = cache.normalMap[normMain]
			}
			if !ok || matched.cardID == 0 {
				continue
			}

			if _, exists := groupedByCard[matched.cardID]; !exists {
				groupedByCard[matched.cardID] = &CardGroup{
					limit:  matched.cardLimit,
					orders: []int{},
				}
				groupedKeys = append(groupedKeys, matched.cardID)
			}
			groupedByCard[matched.cardID].orders = append(groupedByCard[matched.cardID].orders, dr.sortOrder)
		}
		dummyMatchCacheMu.RUnlock()

		if len(groupedKeys) == 0 {
			return
		}

		for _, cid := range groupedKeys {
			info := groupedByCard[cid]
			limit := info.limit
			orders := info.orders

			// 制限枚数オーバー分の削除
			if len(orders) > limit {
				excessOrders := orders[limit:]
				if len(excessOrders) > 0 {
					inOrders := intSliceJoin(excessOrders, ",")
					d.db.Exec(fmt.Sprintf("DELETE FROM deck_cards WHERE deck_id = %d AND sort_order IN (%s)", deckId, inOrders))
				}
				orders = orders[:limit]
			}

			// 置き換え処理
			if limit > 0 && len(orders) > 0 {
				validOrders := intSliceJoin(orders, ",")
				d.db.Exec(fmt.Sprintf(`
                    UPDATE deck_cards 
                    SET card_id = %d, 
                        dummy_name = NULL, 
                        dummy_cost = NULL, 
                        dummy_civ_ids = NULL,
                        dummy_cardtype_ids = NULL,
                        dummy_characteristics_ids = NULL,
                        dummy_ability_ids = NULL,
                        dummy_card_type = 'normal',
                        dummy_partner_name = NULL,
                        dummy_partner_cost = NULL,
                        dummy_partner_civ_ids = NULL,
                        dummy_partner_cardtype_ids = NULL,
                        dummy_partner_characteristics_ids = NULL,
                        dummy_partner_ability_ids = NULL
                    WHERE deck_id = %d AND sort_order IN (%s)
                `, cid, deckId, validOrders))
			}
		}

		// ★追加: デッキのサムネイルが空の場合、置き換わった本物カードの先頭をサムネイルに自動設定
		var thumbnailCardId sql.NullInt64
		err := d.db.QueryRow("SELECT thumbnail_card_id FROM decks WHERE deck_id = ?", deckId).Scan(&thumbnailCardId)
		if err == nil && (!thumbnailCardId.Valid || thumbnailCardId.Int64 == 0) && len(groupedKeys) > 0 {
			firstReplacedCardId := groupedKeys[0]
			d.db.Exec("UPDATE decks SET thumbnail_card_id = ? WHERE deck_id = ?", firstReplacedCardId, deckId)
		}
	}()

	// ★ 2. カード一覧取得クエリ（ダミーのツインパクト・両面情報対応版）
	sqlCards := `SELECT 
                (SELECT format_id FROM decks WHERE deck_id = dc.deck_id) as format_id,
                (SELECT thumbnail_card_id FROM decks WHERE deck_id = dc.deck_id) as thumbnail_card_id,
                COALESCE(c.card_id, CONCAT('dummy_', dc.sort_order)) as card_id,
                CASE 
                    WHEN dc.dummy_card_type = 'twinpact' AND dc.dummy_partner_name IS NOT NULL AND dc.dummy_partner_name <> '' THEN 
                        CONCAT(dc.dummy_name, ' / ', dc.dummy_partner_name)
                    ELSE 
                        COALESCE(c.card_name, dc.dummy_name)
                END as card_name, 
                c.reading,
                COALESCE(c.cost, dc.dummy_cost) as cost, 
                c.pow,
                COALESCE(c.text, 
                    CASE WHEN dc.dummy_card_type = 'twinpact' THEN 
                        CONCAT('【上面】', dc.dummy_name, ' (コスト:', COALESCE(dc.dummy_cost, '?'), ')\n【下面】', COALESCE(dc.dummy_partner_name, ''), ' (コスト:', COALESCE(dc.dummy_partner_cost, '?'), ')')
                    ELSE NULL END
                ) as text,
                cd.modelnum, 
                cd.imagepath,
                cd.regulation,
                COALESCE(cd.twinpact, CASE WHEN dc.dummy_card_type = 'twinpact' THEN 1 ELSE 0 END) as twinpact,
                cd.hypermode,
                dc.quantity,
                dc.card_type_in_deck,
                CASE WHEN dc.dummy_name IS NOT NULL THEN 1 ELSE 0 END as is_dummy,
                COALESCE(dc.dummy_card_type, 'normal') as dummy_card_type,
                dc.dummy_name,
                dc.dummy_cardtype_ids,
                dc.dummy_characteristics_ids,
                dc.dummy_ability_ids,
                dc.dummy_partner_name,
                dc.dummy_partner_cost,
                dc.dummy_partner_civ_ids,
                dc.dummy_partner_cardtype_ids,
                dc.dummy_partner_characteristics_ids,
                dc.dummy_partner_ability_ids,
                cc.combination_id,
                COALESCE((SELECT GROUP_CONCAT(characteristics_id) FROM card_characteristics WHERE card_id = c.card_id), dc.dummy_characteristics_ids) as char_ids,
                -- ★ 追加: 超次元・両面カードの面構成名を確実に取得
                (SELECT GROUP_CONCAT(c_all.card_name ORDER BY cc_all.card_id ASC SEPARATOR '|||') 
                 FROM card_combination cc_ref 
                 JOIN card_combination cc_all ON cc_ref.combination_id = cc_all.combination_id 
                 JOIN card c_all ON cc_all.card_id = c_all.card_id 
                 WHERE cc_ref.card_id = c.card_id) as combo_names,
                
                -- ★ OR を排除してインデックスを完全適用（フルスキャン防止）
                COALESCE(NULLIF(TRIM(BOTH ',' FROM CONCAT_WS(',', 
                    (SELECT GROUP_CONCAT(DISTINCT ability_id) FROM card_ability WHERE card_id = c.card_id),
                    (SELECT GROUP_CONCAT(DISTINCT ca2.ability_id) FROM card_combination cc2 JOIN card_ability ca2 ON cc2.card_id = ca2.card_id WHERE cc2.combination_id = cc.combination_id AND cc2.card_id <> c.card_id)
                )), ''), dc.dummy_ability_ids) as ability_ids,

                COALESCE((SELECT GROUP_CONCAT(civilization_id) FROM card_civilization WHERE card_id = c.card_id), dc.dummy_civ_ids) as civ_ids,
                COALESCE((SELECT GROUP_CONCAT(cardtype_id) FROM card_cardtype WHERE card_id = c.card_id), dc.dummy_cardtype_ids) as cardtype_ids,
                (SELECT GROUP_CONCAT(typename SEPARATOR '/') FROM card_cardtype WHERE card_id = c.card_id) as typename,
                (SELECT GROUP_CONCAT(race_id) FROM card_race WHERE card_id = c.card_id) as race_ids,
                (SELECT GROUP_CONCAT(r.race_name SEPARATOR '/') FROM card_race cr JOIN race r ON cr.race_id = r.race_id WHERE cr.card_id = c.card_id) as race_names,
                
                -- 相方（下面・裏面）情報
                cc_p.partner_card_id,
                (SELECT hypermode FROM card_detail WHERE card_id = cc_p.partner_card_id LIMIT 1) as partner_hypermode,
                COALESCE(
                    (SELECT card_name FROM card WHERE card_id = cc_p.partner_card_id LIMIT 1),
                    dc.dummy_partner_name
                ) as partner_card_name,
                COALESCE(
                    (SELECT cost FROM card WHERE card_id = cc_p.partner_card_id LIMIT 1),
                    dc.dummy_partner_cost
                ) as partner_cost,
                (SELECT pow FROM card WHERE card_id = cc_p.partner_card_id LIMIT 1) as partner_pow,
                (SELECT text FROM card WHERE card_id = cc_p.partner_card_id LIMIT 1) as partner_text,
                COALESCE(
                    (SELECT GROUP_CONCAT(civilization_id) FROM card_civilization WHERE card_id = cc_p.partner_card_id),
                    dc.dummy_partner_civ_ids
                ) as partner_civ_ids,
                COALESCE(
                    (SELECT GROUP_CONCAT(cardtype_id) FROM card_cardtype WHERE card_id = cc_p.partner_card_id),
                    dc.dummy_partner_cardtype_ids
                ) as partner_cardtype_ids,
                (SELECT GROUP_CONCAT(typename SEPARATOR '/') FROM card_cardtype WHERE card_id = cc_p.partner_card_id) as partner_typename,
                (SELECT GROUP_CONCAT(race_id) FROM card_race WHERE card_id = cc_p.partner_card_id) as partner_race_ids,
                (SELECT GROUP_CONCAT(r.race_name SEPARATOR '/') FROM card_race cr JOIN race r ON cr.race_id = r.race_id WHERE cr.card_id = cc_p.partner_card_id) as partner_race_names
            FROM deck_cards dc 
            LEFT JOIN card c ON dc.card_id = c.card_id 
            LEFT JOIN card_detail cd ON c.card_id = cd.card_id 
            LEFT JOIN card_combination cc ON c.card_id = cc.card_id
            LEFT JOIN (
                SELECT combination_id, MIN(card_id) as partner_card_id 
                FROM card_combination 
                WHERE is_main_side = 0
                GROUP BY combination_id
            ) cc_p ON cc.combination_id = cc_p.combination_id AND c.card_id <> cc_p.partner_card_id
            WHERE dc.deck_id = ?
            ORDER BY dc.sort_order ASC`

	rows, err := d.db.Query(sqlCards, deckId)
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	return fetchAllAssoc(rows)
}

// 特定のデッキの基本情報を取得する（再編集・上書き時、サムネイルパス付き）
func (d *Deck) GetByIdAndUser(deckId int, userId int) (map[string]interface{}, error) {
	query := `SELECT d.*, cd.imagepath AS thumbnail_imagepath 
            FROM decks d 
            LEFT JOIN card_detail cd ON d.thumbnail_card_id = cd.card_id
            WHERE d.deck_id = ? AND d.user_id = ?`

	rows, err := d.db.Query(query, deckId, userId)
	if err != nil {
		return nil, err
	}
	defer rows.Close()

	results, err := fetchAllAssoc(rows)
	if err != nil {
		return nil, err
	}
	if len(results) == 0 {
		return nil, nil
	}
	return results[0], nil
}

// ヘルパー: Rowsを[]map[string]interface{}（PDO::FETCH_ASSOC相当）に変換
func fetchAllAssoc(rows *sql.Rows) ([]map[string]interface{}, error) {
	columns, err := rows.Columns()
	if err != nil {
		return nil, err
	}

	var results []map[string]interface{}

	for rows.Next() {
		values := make([]interface{}, len(columns))
		valuePtrs := make([]interface{}, len(columns))
		for i := range values {
			valuePtrs[i] = &values[i]
		}

		if err := rows.Scan(valuePtrs...); err != nil {
			return nil, err
		}

		rowMap := make(map[string]interface{})
		for i, col := range columns {
			val := values[i]
			if b, ok := val.([]byte); ok {
				rowMap[col] = string(b)
			} else {
				rowMap[col] = val
			}
		}
		results = append(results, rowMap)
	}

	return results, nil
}

func toInt(v interface{}) int {
	switch val := v.(type) {
	case int:
		return val
	case int64:
		return int(val)
	case []byte:
		var n int
		fmt.Sscanf(string(val), "%d", &n)
		return n
	case string:
		var n int
		fmt.Sscanf(val, "%d", &n)
		return n
	default:
		return 0
	}
}

func intSliceJoin(elems []int, sep string) string {
	strs := make([]string, len(elems))
	for i, v := range elems {
		strs[i] = fmt.Sprintf("%d", v)
	}
	return strings.Join(strs, sep)
}
type dummyMatchTarget struct {
	cardID    int
	cardLimit int
}

type dummyMatchCache struct {
	cardCount      int
	maxCardID      int
	loadedAt       time.Time
	normalMap      map[string]dummyMatchTarget
	comboTwinMap   map[string]dummyMatchTarget
	comboDoubleMap map[string]dummyMatchTarget
}

var (
	dummyMatchCacheMu   sync.RWMutex
	globalDummyMatchCache *dummyMatchCache
)

func normalizeDummyMatchName(s string) string {
	s = strings.ToLower(s)
	replacer := strings.NewReplacer(" ", "", "　", "", "・", "", "～", "", "〜", "", "ー", "")
	return replacer.Replace(s)
}

func getOrBuildDummyMatchCache(db *sql.DB) *dummyMatchCache {
	var cnt, maxID int
	if err := db.QueryRow("SELECT COUNT(*), COALESCE(MAX(card_id), 0) FROM card").Scan(&cnt, &maxID); err != nil {
		return nil
	}

	dummyMatchCacheMu.RLock()
	if globalDummyMatchCache != nil &&
		globalDummyMatchCache.cardCount == cnt &&
		globalDummyMatchCache.maxCardID == maxID &&
		time.Since(globalDummyMatchCache.loadedAt) < 5*time.Minute {
		c := globalDummyMatchCache
		dummyMatchCacheMu.RUnlock()
		return c
	}
	dummyMatchCacheMu.RUnlock()

	dummyMatchCacheMu.Lock()
	defer dummyMatchCacheMu.Unlock()

	if globalDummyMatchCache != nil &&
		globalDummyMatchCache.cardCount == cnt &&
		globalDummyMatchCache.maxCardID == maxID &&
		time.Since(globalDummyMatchCache.loadedAt) < 5*time.Minute {
		return globalDummyMatchCache
	}

	rows, err := db.Query(`
		SELECT 
			c.card_id,
			c.card_name,
			COALESCE(cd.limit, 4) as card_limit,
			COALESCE(cd.twinpact, 0) as twinpact,
			cc.combination_id,
			COALESCE(cc.is_main_side, 0) as is_main_side
		FROM card c
		LEFT JOIN card_detail cd ON c.card_id = cd.card_id
		LEFT JOIN card_combination cc ON c.card_id = cc.card_id
		ORDER BY COALESCE(cd.is_primary_version, 0) DESC, c.card_id ASC
	`)
	if err != nil {
		return nil
	}
	defer rows.Close()

	type comboMember struct {
		cardID     int
		normName   string
		cardLimit  int
		twinpact   int
		isMainSide int
	}

	newCache := &dummyMatchCache{
		cardCount:      cnt,
		maxCardID:      maxID,
		loadedAt:       time.Now(),
		normalMap:      make(map[string]dummyMatchTarget),
		comboTwinMap:   make(map[string]dummyMatchTarget),
		comboDoubleMap: make(map[string]dummyMatchTarget),
	}
	combos := make(map[int][]comboMember)

	for rows.Next() {
		var cid, limit, twinpact, isMain int
		var name string
		var combID sql.NullInt64
		if err := rows.Scan(&cid, &name, &limit, &twinpact, &combID, &isMain); err != nil {
			continue
		}
		norm := normalizeDummyMatchName(name)
		if !combID.Valid {
			if _, exists := newCache.normalMap[norm]; !exists {
				newCache.normalMap[norm] = dummyMatchTarget{cardID: cid, cardLimit: limit}
			}
		} else {
			cidInt := int(combID.Int64)
			combos[cidInt] = append(combos[cidInt], comboMember{
				cardID:     cid,
				normName:   norm,
				cardLimit:  limit,
				twinpact:   twinpact,
				isMainSide: isMain,
			})
		}
	}

	for _, members := range combos {
		for _, m := range members {
			if m.isMainSide != 1 {
				continue
			}
			for _, p := range members {
				if p.cardID == m.cardID {
					continue
				}
				key := m.normName + "|||" + p.normName
				if m.twinpact == 1 {
					if _, exists := newCache.comboTwinMap[key]; !exists {
						newCache.comboTwinMap[key] = dummyMatchTarget{cardID: m.cardID, cardLimit: m.cardLimit}
					}
				} else {
					if _, exists := newCache.comboDoubleMap[key]; !exists {
						newCache.comboDoubleMap[key] = dummyMatchTarget{cardID: m.cardID, cardLimit: m.cardLimit}
					}
				}
			}
		}
	}

	globalDummyMatchCache = newCache
	return newCache
}
