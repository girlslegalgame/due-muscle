package controllers

import (
	"database/sql"
	models "deck-maker/app/Models"
	"encoding/json"
	"fmt"
	"log" // ★ 追加（未インポートの場合）
	"net/http"
	"net/url"
	"regexp"
	"strconv"
	"strings"
)

type CardController struct {
	db *sql.DB
}

func NewCardController(db *sql.DB) *AuthController {
	return &AuthController{
		db: db,
	}
}

// mb_convert_kana の簡易互換関数 ("C": ひらがな->カタカナ, "c": カタカナ->ひらがな)
func convertKana(s string, mode string) string {
	var sb strings.Builder
	for _, r := range s {
		if mode == "C" { // ひらがな -> カタカナ
			if r >= 0x3041 && r <= 0x3096 {
				sb.WriteRune(r + 0x60)
				continue
			}
		} else if mode == "c" { // カタカナ -> ひらがな
			if r >= 0x30A1 && r <= 0x30F6 {
				sb.WriteRune(r - 0x60)
				continue
			}
		}
		sb.WriteRune(r)
	}
	return sb.String()
}

// 文字列内のスペース・中黒を除去
func cleanSearchString(s string) string {
	replacer := strings.NewReplacer("・", "", " ", "", "　", "")
	return replacer.Replace(s)
}

// 名前付きパラメータ(:param)を ? に変換し、対応する値スライスを生成するヘルパー関数
func buildNamedQuery(query string, params map[string]interface{}) (string, []interface{}) {
	re := regexp.MustCompile(`:[a-zA-Z0-9_]+`)
	var args []interface{}

	convertedQuery := re.ReplaceAllStringFunc(query, func(param string) string {
		if val, ok := params[param]; ok {
			args = append(args, val)
			return "?"
		}
		return param
	})

	return convertedQuery, args
}

// sql.Rows を []map[string]interface{} に変換するヘルパー
func rowsToMapSlice(rows *sql.Rows) ([]map[string]interface{}, error) {
	defer rows.Close()
	columns, err := rows.Columns()
	if err != nil {
		return nil, err
	}

	results := make([]map[string]interface{}, 0)
	for rows.Next() {
		values := make([]interface{}, len(columns))
		valuePtrs := make([]interface{}, len(columns))
		for i := range values {
			valuePtrs[i] = &values[i]
		}

		if err := rows.Scan(valuePtrs...); err != nil {
			return nil, err
		}

		entry := make(map[string]interface{})
		for i, col := range columns {
			var v interface{}
			val := values[i]
			b, ok := val.([]byte)
			if ok {
				v = string(b)
			} else {
				v = val
			}
			entry[col] = v
		}
		results = append(results, entry)
	}
	return results, nil
}

func parseStringSlice(s string) []string {
	if s == "" {
		return []string{}
	}
	return strings.Split(s, ",")
}

func parseIntSlice(strs []string) []int {
	var ints []int
	for _, s := range strs {
		if val, err := strconv.Atoi(strings.TrimSpace(s)); err == nil {
			ints = append(ints, val)
		}
	}
	return ints
}

func joinInts(ints []int) string {
	var strs []string
	for _, i := range ints {
		strs = append(strs, strconv.Itoa(i))
	}
	return strings.Join(strs, ",")
}

func containsInt(slice []int, item int) bool {
	for _, v := range slice {
		if v == item {
			return true
		}
	}
	return false
}

func containsString(slice []string, item string) bool {
	for _, v := range slice {
		if v == item {
			return true
		}
	}
	return false
}

/**
 * カード検索API (爆速フラグ・無限スクロール・詳細フィルタ・ゾーン振り分け対応)
 */
func (c *CardController) CardsApi(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	query := r.URL.Query()

	q := query.Get("q")
	var scope []string
	if val, ok := query["scope"]; ok && len(val) > 0 && val[0] != "" {
		scope = strings.Split(val[0], ",")
	} else {
		scope = []string{"name"}
	}

	costMin := query.Get("cost_min")
	costMax := query.Get("cost_max")
	powMin := query.Get("pow_min")
	powMax := query.Get("pow_max")
	civs := parseIntSlice(parseStringSlice(query.Get("civs")))
	races := parseIntSlice(parseStringSlice(query.Get("races")))
	abilities := parseIntSlice(parseStringSlice(query.Get("abilities")))
	regulations := parseIntSlice(parseStringSlice(query.Get("reg")))
	civType := query.Get("civ_type")
	excludeCivs := parseIntSlice(parseStringSlice(query.Get("exclude_civs")))
	civMatchType := query.Get("civ_match_type")
	if civMatchType == "" {
		civMatchType = "include"
	}

	raceLogic := query.Get("race_logic")
	if raceLogic == "" {
		raceLogic = "OR"
	}
	abilityLogic := query.Get("ability_logic")
	if abilityLogic == "" {
		abilityLogic = "OR"
	}

	characteristics := parseIntSlice(parseStringSlice(query.Get("characteristics")))
	cardtypes := parseIntSlice(parseStringSlice(query.Get("cardtypes")))
	characteristicLogic := query.Get("characteristic_logic")
	if characteristicLogic == "" {
		characteristicLogic = "OR"
	}
	cardtypeLogic := query.Get("cardtype_logic")
	if cardtypeLogic == "" {
		cardtypeLogic = "OR"
	}
	goods := parseIntSlice(parseStringSlice(query.Get("goods")))
	twinpact := query.Get("twinpact")
	sort := query.Get("sort")
	if sort == "" {
		sort = "release_desc"
	}
	rawQuery := query.Get("raw_query") == "1"
	limit := 50
	offset := 0
	if val := query.Get("offset"); val != "" {
		if o, err := strconv.Atoi(val); err == nil {
			offset = o
		}
	}

	db, err := models.DatabaseConnect()
	if err != nil {
		w.WriteHeader(http.StatusInternalServerError)
		json.NewEncoder(w).Encode(map[string]string{"error": err.Error()})
		return
	}
	// defer db.Close()

	params := make(map[string]interface{})

	searchSql := `
        SELECT DISTINCT 
            CASE 
                WHEN ccb_search.card_id IS NOT NULL AND c_search.card_id <> c_front.card_id THEN c_front.card_name
                ELSE c_search.card_name
            END as target_name,
            IF(ccb_search.card_id IS NOT NULL, 1, 0) as is_combo
        FROM card c_search
        LEFT JOIN card_detail cd_search ON c_search.card_id = cd_search.card_id
        LEFT JOIN card_combination ccb_search ON c_search.card_id = ccb_search.card_id
        LEFT JOIN card_combination ccb_front ON ccb_search.combination_id = ccb_front.combination_id AND ccb_front.is_main_side = 1
        LEFT JOIN card c_front ON ccb_front.card_id = c_front.card_id
        WHERE 1=1`

	if q != "" {
		var conds []string
		if rawQuery {
			if containsString(scope, "name") {
				conds = append(conds, "c_search.card_name LIKE :q_raw_name")
				params[":q_raw_name"] = "%" + q + "%"
			}
			if containsString(scope, "name") || containsString(scope, "reading") {
				conds = append(conds, "c_search.reading LIKE :q_raw_read")
				params[":q_raw_read"] = "%" + q + "%"
			}
			if containsString(scope, "text") {
				conds = append(conds, "c_search.text LIKE :q_raw_text")
				params[":q_raw_text"] = "%" + q + "%"
			}
			if containsString(scope, "race") {
				conds = append(conds, `c_search.card_id IN (
                    SELECT cr_search.card_id 
                    FROM card_race cr_search
                    JOIN race r_search ON cr_search.race_id = r_search.race_id
                    WHERE r_search.race_name LIKE :q_raw_race
                       OR r_search.reading LIKE :q_raw_race_read
                )`)
				params[":q_raw_race"] = "%" + q + "%"
				params[":q_raw_race_read"] = "%" + q + "%"
			}
		} else {
			qKata := convertKana(q, "C")
			qHira := convertKana(q, "c")
			qClean := cleanSearchString(q)
			qKataClean := cleanSearchString(qKata)
			qHiraClean := cleanSearchString(qHira)

			if containsString(scope, "name") {
				conds = append(conds, `(
                    REPLACE(REPLACE(REPLACE(c_search.card_name, '・', ''), ' ', ''), '　', '') LIKE :q_name_clean
                    OR REPLACE(REPLACE(REPLACE(c_search.card_name, '・', ''), ' ', ''), '　', '') LIKE :q_name_kata_clean
                    OR REPLACE(REPLACE(REPLACE(c_search.card_name, '・', ''), ' ', ''), '　', '') LIKE :q_name_hira_clean
                )`)
				params[":q_name_clean"] = "%" + qClean + "%"
				params[":q_name_kata_clean"] = "%" + qKataClean + "%"
				params[":q_name_hira_clean"] = "%" + qHiraClean + "%"
			}

			if containsString(scope, "name") || containsString(scope, "reading") {
				conds = append(conds, `(
                    REPLACE(REPLACE(REPLACE(c_search.reading, '・', ''), ' ', ''), '　', '') LIKE :q_read_kata_clean
                    OR REPLACE(REPLACE(REPLACE(c_search.reading, '・', ''), ' ', ''), '　', '') LIKE :q_read_hira_clean
                )`)
				params[":q_read_kata_clean"] = "%" + qKataClean + "%"
				params[":q_read_hira_clean"] = "%" + qHiraClean + "%"
			}

			if containsString(scope, "text") {
				conds = append(conds, "c_search.text LIKE :q_text")
				params[":q_text"] = "%" + q + "%"
			}

			if containsString(scope, "race") {
				conds = append(conds, `c_search.card_id IN (
                    SELECT cr_search.card_id 
                    FROM card_race cr_search
                    JOIN race r_search ON cr_search.race_id = r_search.race_id
                    WHERE REPLACE(REPLACE(REPLACE(r_search.race_name, '・', ''), ' ', ''), '　', '') LIKE :q_race_clean
                       OR REPLACE(REPLACE(REPLACE(r_search.reading, '・', ''), ' ', ''), '　', '') LIKE :q_race_read_kata_clean
                       OR REPLACE(REPLACE(REPLACE(r_search.reading, '・', ''), ' ', ''), '　', '') LIKE :q_race_read_hira_clean
                )`)
				params[":q_race_clean"] = "%" + qClean + "%"
				params[":q_race_read_kata_clean"] = "%" + qKataClean + "%"
				params[":q_race_read_hira_clean"] = "%" + qHiraClean + "%"
			}
		}

		if len(conds) > 0 {
			searchSql += " AND (" + strings.Join(conds, " OR ") + ")"
		}
	}

	if costMin != "" {
		if val, err := strconv.Atoi(costMin); err == nil {
			searchSql += " AND c_search.cost >= :cMin"
			params[":cMin"] = val
		}
	}
	if costMax != "" {
		if val, err := strconv.Atoi(costMax); err == nil {
			searchSql += " AND c_search.cost <= :cMax"
			params[":cMax"] = val
		}
	}
	if powMin != "" {
		if val, err := strconv.Atoi(powMin); err == nil {
			searchSql += " AND c_search.pow >= :pMin"
			params[":pMin"] = val
		}
	}
	if powMax != "" {
		if val, err := strconv.Atoi(powMax); err == nil {
			searchSql += " AND c_search.pow <= :pMax"
			params[":pMax"] = val
		}
	}

	if civType == "single" {
		searchSql += " AND (SELECT COUNT(*) FROM card_civilization cc WHERE cc.card_id = c_search.card_id) <= 1"
	} else if civType == "multi" {
		searchSql += " AND (SELECT COUNT(*) FROM card_civilization cc WHERE cc.card_id = c_search.card_id) >= 2"
	} else if civType == "none" {
		searchSql += " AND 1=0"
	}

	if len(civs) > 0 {
		hasZero := containsInt(civs, 6)
		var otherCivs []int
		for _, v := range civs {
			if v != 6 {
				otherCivs = append(otherCivs, v)
			}
		}

		if civMatchType == "match" {
			if hasZero && len(otherCivs) == 0 {
				searchSql += ` AND (
                    EXISTS (SELECT 1 FROM card_civilization cc WHERE cc.card_id = c_search.card_id AND cc.civilization_id = 6)
                    OR NOT EXISTS (SELECT 1 FROM card_civilization cc WHERE cc.card_id = c_search.card_id)
                )`
				searchSql += " AND NOT EXISTS (SELECT 1 FROM card_civilization cc WHERE cc.card_id = c_search.card_id AND cc.civilization_id != 6)"
			} else {
				for _, cId := range civs {
					if cId == 6 {
						searchSql += ` AND (
                            EXISTS (SELECT 1 FROM card_civilization cc WHERE cc.card_id = c_search.card_id AND cc.civilization_id = 6)
                            OR NOT EXISTS (SELECT 1 FROM card_civilization cc WHERE cc.card_id = c_search.card_id)
                        )`
					} else {
						searchSql += fmt.Sprintf(" AND EXISTS (SELECT 1 FROM card_civilization cc WHERE cc.card_id = c_search.card_id AND cc.civilization_id = %d)", cId)
					}
				}
				civList := joinInts(civs)
				searchSql += fmt.Sprintf(" AND NOT EXISTS (SELECT 1 FROM card_civilization cc WHERE cc.card_id = c_search.card_id AND cc.civilization_id NOT IN (%s))", civList)
			}
		} else {
			if hasZero {
				if len(otherCivs) > 0 {
					otherCivList := joinInts(otherCivs)
					searchSql += fmt.Sprintf(` AND (
                        EXISTS (SELECT 1 FROM card_civilization cc WHERE cc.card_id = c_search.card_id AND cc.civilization_id IN (%s, 6))
                        OR NOT EXISTS (SELECT 1 FROM card_civilization cc WHERE cc.card_id = c_search.card_id)
                    )`, otherCivList)
				} else {
					searchSql += ` AND (
                        EXISTS (SELECT 1 FROM card_civilization cc WHERE cc.card_id = c_search.card_id AND cc.civilization_id = 6)
                        OR NOT EXISTS (SELECT 1 FROM card_civilization cc WHERE cc.card_id = c_search.card_id)
                    )`
				}
			} else {
				civList := joinInts(civs)
				searchSql += fmt.Sprintf(" AND EXISTS (SELECT 1 FROM card_civilization cc WHERE cc.card_id = c_search.card_id AND cc.civilization_id IN (%s))", civList)
			}
		}
	}

	if len(excludeCivs) > 0 {
		hasExcludeZero := containsInt(excludeCivs, 6)
		var otherExcludeCivs []int
		for _, v := range excludeCivs {
			if v != 6 {
				otherExcludeCivs = append(otherExcludeCivs, v)
			}
		}

		if len(otherExcludeCivs) > 0 {
			excludeList := joinInts(otherExcludeCivs)
			searchSql += fmt.Sprintf(` AND NOT EXISTS (
                SELECT 1 FROM card_civilization cc 
                WHERE cc.card_id = c_search.card_id AND cc.civilization_id IN (%s)
            )`, excludeList)

			searchSql += fmt.Sprintf(` AND (
                ccb_search.combination_id IS NULL OR NOT EXISTS (
                    SELECT 1 FROM card_combination ccb_sub
                    JOIN card_civilization cc ON ccb_sub.card_id = cc.card_id
                    WHERE ccb_sub.combination_id = ccb_search.combination_id 
                      AND cc.civilization_id IN (%s)
                )
            )`, excludeList)

			if hasExcludeZero {
				searchSql += " AND EXISTS (SELECT 1 FROM card_civilization cc WHERE cc.card_id = c_search.card_id)"
				searchSql += " AND NOT EXISTS (SELECT 1 FROM card_civilization cc WHERE cc.card_id = c_search.card_id AND cc.civilization_id = 6)"
			}
		} else if hasExcludeZero {
			searchSql += " AND EXISTS (SELECT 1 FROM card_civilization cc WHERE cc.card_id = c_search.card_id)"
			searchSql += " AND NOT EXISTS (SELECT 1 FROM card_civilization cc WHERE cc.card_id = c_search.card_id AND cc.civilization_id = 6)"
		}
	}

	if len(races) > 0 {
		if raceLogic == "AND" {
			for i, id := range races {
				key := fmt.Sprintf(":r%d", i)
				searchSql += fmt.Sprintf(" AND EXISTS (SELECT 1 FROM card_race cr WHERE cr.card_id = c_search.card_id AND cr.race_id = %s)", key)
				params[key] = id
			}
		} else {
			idList := joinInts(races)
			searchSql += fmt.Sprintf(" AND EXISTS (SELECT 1 FROM card_race cr WHERE cr.card_id = c_search.card_id AND cr.race_id IN (%s))", idList)
		}
	}

	if len(abilities) > 0 {
		if abilityLogic == "AND" {
			for i, id := range abilities {
				key := fmt.Sprintf(":a%d", i)
				searchSql += fmt.Sprintf(" AND EXISTS (SELECT 1 FROM card_ability ca WHERE ca.card_id = c_search.card_id AND ca.ability_id = %s)", key)
				params[key] = id
			}
		} else {
			idList := joinInts(abilities)
			searchSql += fmt.Sprintf(" AND EXISTS (SELECT 1 FROM card_ability ca WHERE ca.card_id = c_search.card_id AND ca.ability_id IN (%s))", idList)
		}
	}

	if len(characteristics) > 0 {
		if characteristicLogic == "AND" {
			for i, id := range characteristics {
				key := fmt.Sprintf(":char%d", i)
				searchSql += fmt.Sprintf(" AND EXISTS (SELECT 1 FROM card_characteristics c_char WHERE c_char.card_id = c_search.card_id AND c_char.characteristics_id = %s)", key)
				params[key] = id
			}
		} else {
			idList := joinInts(characteristics)
			searchSql += fmt.Sprintf(" AND EXISTS (SELECT 1 FROM card_characteristics c_char WHERE c_char.card_id = c_search.card_id AND c_char.characteristics_id IN (%s))", idList)
		}
	}

	if len(cardtypes) > 0 {
		if cardtypeLogic == "AND" {
			for i, id := range cardtypes {
				key := fmt.Sprintf(":ctype%d", i)
				searchSql += fmt.Sprintf(" AND EXISTS (SELECT 1 FROM card_cardtype c_type WHERE c_type.card_id = c_search.card_id AND c_type.cardtype_id = %s)", key)
				params[key] = id
			}
		} else {
			idList := joinInts(cardtypes)
			searchSql += fmt.Sprintf(" AND EXISTS (SELECT 1 FROM card_cardtype c_type WHERE c_type.card_id = c_search.card_id AND c_type.cardtype_id IN (%s))", idList)
		}
	}

	if len(regulations) > 0 {
		regList := joinInts(regulations)
		searchSql += fmt.Sprintf(" AND cd_search.regulation IN (%s)", regList)
	}

	if len(goods) > 0 {
		goodsList := joinInts(goods)
		searchSql += fmt.Sprintf(" AND cd_search.goods_id IN (%s)", goodsList)
	}

	if twinpact == "only" {
		searchSql += " AND cd_search.twinpact = 1"
	} else if twinpact == "exclude" {
		searchSql += " AND (cd_search.twinpact = 0 OR cd_search.twinpact IS NULL)"
	}

	isFiltered := (q != "" ||
		costMin != "" ||
		costMax != "" ||
		powMin != "" ||
		powMax != "" ||
		civType != "" ||
		len(civs) > 0 ||
		len(excludeCivs) > 0 ||
		len(races) > 0 ||
		len(abilities) > 0 ||
		len(characteristics) > 0 ||
		len(cardtypes) > 0 ||
		len(regulations) > 0 ||
		len(goods) > 0 ||
		twinpact != "")

	var orderBy string
	switch sort {
	case "release_asc":
		orderBy = "ORDER BY cd.release_date ASC, cd.goods_id ASC, c.card_id ASC "
	case "cost_desc":
		orderBy = "ORDER BY c.cost DESC, cd.release_date DESC, c.card_id DESC "
	case "cost_asc":
		orderBy = "ORDER BY c.cost ASC, cd.release_date DESC, c.card_id ASC "
	case "pow_desc":
		orderBy = "ORDER BY c.pow DESC, cd.release_date DESC, c.card_id DESC "
	case "pow_asc":
		orderBy = "ORDER BY c.pow ASC, cd.release_date DESC, c.card_id ASC "
	case "name_asc":
		orderBy = "ORDER BY (CASE WHEN c.reading = '' OR c.reading IS NULL THEN 1 ELSE 0 END), c.reading ASC, c.card_name ASC "
	case "name_desc":
		orderBy = "ORDER BY (CASE WHEN c.reading = '' OR c.reading IS NULL THEN 1 ELSE 0 END), c.reading DESC, c.card_name DESC "
	case "release_desc":
		fallthrough
	default:
		orderBy = "ORDER BY cd.release_date DESC, cd.goods_id DESC, c.card_id DESC "
	}

	var sqlQuery string
	if !isFiltered {
		sqlQuery = fmt.Sprintf(`
            SELECT 
                c.*, cd.modelnum, cd.imagepath, cd.`+"`limit`"+` as card_limit, cd.twinpact,
                CASE 
                    WHEN cd.twinpact = 1 AND ccb.combination_id IS NOT NULL THEN (
                        SELECT GROUP_CONCAT(c_sub.card_name ORDER BY c_sub.card_id ASC SEPARATOR ' / ')
                        FROM card_combination cc_sub
                        JOIN card c_sub ON cc_sub.card_id = c_sub.card_id
                        WHERE cc_sub.combination_id = ccb.combination_id
                    )
                    ELSE c.card_name 
                END as display_name,
                (SELECT GROUP_CONCAT(civilization_id) FROM card_civilization WHERE card_id = c.card_id) as civ_ids,
                (SELECT GROUP_CONCAT(characteristics_id) FROM card_characteristics WHERE card_id = c.card_id) as char_ids,
                (SELECT GROUP_CONCAT(c_all.card_name ORDER BY cc_all.card_id ASC SEPARATOR '|||') 
                 FROM card_combination cc_ref 
                 JOIN card_combination cc_all ON cc_ref.combination_id = cc_all.combination_id 
                 JOIN card c_all ON cc_all.card_id = c_all.card_id 
                 WHERE cc_ref.card_id = c.card_id) as combo_names,
                IF(ccb.card_id IS NOT NULL, 1, 0) as is_combo
            FROM card c
            JOIN card_detail cd ON c.card_id = cd.card_id
            LEFT JOIN card_combination ccb ON c.card_id = ccb.card_id
            WHERE cd.is_primary_version = 1
            AND (ccb.combination_id IS NULL OR ccb.is_main_side = 1)
            %s
            LIMIT %d OFFSET %d`, orderBy, limit, offset)
	} else {
		sqlQuery = fmt.Sprintf(`
            SELECT 
                c.*, cd.modelnum, cd.imagepath, cd.`+"`limit`"+` as card_limit, cd.twinpact,
                CASE 
                    WHEN cd.twinpact = 1 AND ccb.combination_id IS NOT NULL THEN (
                        SELECT GROUP_CONCAT(c_sub.card_name ORDER BY c_sub.card_id ASC SEPARATOR ' / ')
                        FROM card_combination cc_sub
                        JOIN card c_sub ON cc_sub.card_id = c_sub.card_id
                        WHERE cc_sub.combination_id = ccb.combination_id
                    )
                    ELSE c.card_name 
                END as display_name,
                (SELECT GROUP_CONCAT(civilization_id) FROM card_civilization WHERE card_id = c.card_id) as civ_ids,
                (SELECT GROUP_CONCAT(characteristics_id) FROM card_characteristics WHERE card_id = c.card_id) as char_ids,
                (SELECT GROUP_CONCAT(c_all.card_name ORDER BY cc_all.card_id ASC SEPARATOR '|||') 
                 FROM card_combination cc_ref 
                 JOIN card_combination cc_all ON cc_ref.combination_id = cc_all.combination_id 
                 JOIN card c_all ON cc_all.card_id = c_all.card_id 
                 WHERE cc_ref.card_id = c.card_id) as combo_names,
                IF(ccb.card_id IS NOT NULL, 1, 0) as is_combo
            FROM card c
            JOIN card_detail cd ON c.card_id = cd.card_id
            LEFT JOIN card_combination ccb ON c.card_id = ccb.card_id
            JOIN (%s) as matched_names ON 
                c.card_name = matched_names.target_name 
                AND IF(ccb.card_id IS NOT NULL, 1, 0) = matched_names.is_combo
            WHERE cd.is_primary_version = 1
            AND (ccb.combination_id IS NULL OR ccb.is_main_side = 1)
            %s
            LIMIT %d OFFSET %d`, searchSql, orderBy, limit, offset)
	}

	execSql, args := buildNamedQuery(sqlQuery, params)

	// デバッグ用ヘッダー
	debugParams := make(map[string]interface{})
	for k, v := range params {
		debugParams[k] = v
	}
	debugParams[":limit"] = limit
	debugParams[":offset"] = offset

	cleanSql := regexp.MustCompile(`\s+`).ReplaceAllString(strings.TrimSpace(sqlQuery), " ")
	debugParamsJSON, _ := json.Marshal(debugParams)
	w.Header().Set("X-Debug-Sql", url.QueryEscape(cleanSql))
	w.Header().Set("X-Debug-Params", url.QueryEscape(string(debugParamsJSON)))

	rows, err := db.Query(execSql, args...)
	if err != nil {
		w.WriteHeader(http.StatusInternalServerError)
		json.NewEncoder(w).Encode(map[string]string{"error": err.Error()})
		return
	}

	results, err := rowsToMapSlice(rows)
	if err != nil {
		w.WriteHeader(http.StatusInternalServerError)
		json.NewEncoder(w).Encode(map[string]string{"error": err.Error()})
		return
	}

	json.NewEncoder(w).Encode(results)
}

func (c *CardController) MasterDataApi(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	db, err := models.DatabaseConnect()
	if err != nil {
		w.WriteHeader(http.StatusInternalServerError)
		json.NewEncoder(w).Encode(map[string]string{"error": err.Error()})
		return
	}
	// defer db.Close()

	racesRows, err := db.Query("SELECT race_id, race_name, reading FROM race ORDER BY (CASE WHEN reading = '' OR reading IS NULL THEN 0 ELSE 1 END) ASC, reading ASC, race_id ASC")
	if err != nil {
		w.WriteHeader(http.StatusInternalServerError)
		json.NewEncoder(w).Encode(map[string]string{"error": err.Error()})
		return
	}
	races, _ := rowsToMapSlice(racesRows)

	abilitiesRows, err := db.Query("SELECT ability_id, ability_name, reading FROM ability ORDER BY (CASE WHEN reading = '' OR reading IS NULL THEN 0 ELSE 1 END) ASC, reading ASC, ability_id ASC")
	if err != nil {
		w.WriteHeader(http.StatusInternalServerError)
		json.NewEncoder(w).Encode(map[string]string{"error": err.Error()})
		return
	}
	abilities, _ := rowsToMapSlice(abilitiesRows)

	charsRows, err := db.Query("SELECT characteristics_id, characteristics_name FROM characteristics ORDER BY characteristics_id ASC")
	if err != nil {
		w.WriteHeader(http.StatusInternalServerError)
		json.NewEncoder(w).Encode(map[string]string{"error": err.Error()})
		return
	}
	characteristics, _ := rowsToMapSlice(charsRows)

	cardtypesRows, err := db.Query("SELECT cardtype_id, cardtype_name FROM cardtype ORDER BY cardtype_id ASC")
	if err != nil {
		w.WriteHeader(http.StatusInternalServerError)
		json.NewEncoder(w).Encode(map[string]string{"error": err.Error()})
		return
	}
	cardtypes, _ := rowsToMapSlice(cardtypesRows)

	json.NewEncoder(w).Encode(map[string]interface{}{
		"races":           races,
		"abilities":       abilities,
		"characteristics": characteristics,
		"cardtypes":       cardtypes,
	})
}

func (c *CardController) CardVersionsApi(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	cardId := r.URL.Query().Get("card_id")
	if cardId == "" {
		json.NewEncoder(w).Encode([]interface{}{})
		return
	}

	db, err := models.DatabaseConnect()
	if err != nil {
		log.Printf("[CardVersionsApi DBエラー] %v", err)
		w.WriteHeader(http.StatusInternalServerError)
		json.NewEncoder(w).Encode([]interface{}{})
		return
	}
	// defer db.Close()

	targetQuery := `
        SELECT c.card_name, ccb.combination_id 
        FROM card c 
        LEFT JOIN card_combination ccb ON c.card_id = ccb.card_id 
        WHERE c.card_id = ?`

	var cardName string
	var combinationId sql.NullInt64
	err = db.QueryRow(targetQuery, cardId).Scan(&cardName, &combinationId)
	if err != nil {
		log.Printf("[CardVersionsApi targetQueryエラー card_id=%s] %v", cardId, err)
		json.NewEncoder(w).Encode([]interface{}{}) // 空配列を返却
		return
	}

	isCombo := combinationId.Valid

	sqlQuery := `SELECT c.card_id, 
                   CASE 
                       WHEN ccb.combination_id IS NOT NULL AND cd.twinpact = 1 AND (
                           SELECT COUNT(DISTINCT c_sub.card_name) 
                           FROM card_combination cc_sub 
                           JOIN card c_sub ON cc_sub.card_id = c_sub.card_id 
                           WHERE cc_sub.combination_id = ccb.combination_id
                       ) > 1 THEN (
                           SELECT GROUP_CONCAT(c_sub.card_name ORDER BY cc_sub.card_id ASC SEPARATOR ' / ') 
                           FROM card_combination cc_sub 
                           JOIN card c_sub ON cc_sub.card_id = c_sub.card_id 
                           WHERE cc_sub.combination_id = ccb.combination_id
                       )
                       ELSE c.card_name 
                   END as card_name,

                   CASE 
                       WHEN ccb.combination_id IS NOT NULL AND cd.twinpact = 1 AND (
                           SELECT COUNT(DISTINCT c_sub.card_name) 
                           FROM card_combination cc_sub 
                           JOIN card c_sub ON cc_sub.card_id = c_sub.card_id 
                           WHERE cc_sub.combination_id = ccb.combination_id
                       ) > 1 THEN (
                           SELECT GROUP_CONCAT(c_sub.text ORDER BY cc_sub.card_id ASC SEPARATOR '\n\n') 
                           FROM card_combination cc_sub 
                           JOIN card c_sub ON cc_sub.card_id = c_sub.card_id 
                           WHERE cc_sub.combination_id = ccb.combination_id
                       )
                       WHEN ccb.combination_id IS NOT NULL AND cd.twinpact = 1 AND (
                           SELECT COUNT(DISTINCT c_sub.card_name) 
                           FROM card_combination cc_sub 
                           JOIN card c_sub ON cc_sub.card_id = c_sub.card_id 
                           WHERE cc_sub.combination_id = ccb.combination_id
                       ) = 1 THEN (
                           SELECT c_max.text 
                           FROM card_combination cc_max 
                           JOIN card c_max ON cc_max.card_id = c_max.card_id 
                           WHERE cc_max.combination_id = ccb.combination_id 
                           ORDER BY cc_max.card_id DESC 
                           LIMIT 1
                       )
                       ELSE c.text 
                   END as text,

                   c.pow, c.cost, cd.twinpact, cd.modelnum, cd.imagepath, cd.release_date, cd.` + "`limit`" + ` as card_limit,
                   (SELECT GROUP_CONCAT(characteristics_id) FROM card_characteristics WHERE card_id = c.card_id) as char_ids,
                   (SELECT GROUP_CONCAT(c_all.card_name ORDER BY cc_all.card_id ASC SEPARATOR '|||') 
                    FROM card_combination cc_ref 
                    JOIN card_combination cc_all ON cc_ref.combination_id = cc_all.combination_id 
                    JOIN card c_all ON cc_all.card_id = c_all.card_id 
                    WHERE cc_ref.card_id = c.card_id) as combo_names,
                   CASE WHEN ccb.card_id IS NOT NULL THEN 1 ELSE 0 END as is_combo
            FROM card c
            JOIN card_detail cd ON c.card_id = cd.card_id
            LEFT JOIN card_combination ccb ON c.card_id = ccb.card_id
            WHERE c.card_name = :name `

	if isCombo {
		sqlQuery += ` AND ccb.combination_id IS NOT NULL 
          AND ccb.is_main_side = 1 
          AND (
            SELECT GROUP_CONCAT(c_all2.card_name ORDER BY cc_all2.card_id ASC SEPARATOR '|||') 
            FROM card_combination cc_ref2 
            JOIN card_combination cc_all2 ON cc_ref2.combination_id = cc_all2.combination_id 
            JOIN card c_all2 ON cc_all2.card_id = c_all2.card_id 
            WHERE cc_ref2.card_id = c.card_id
        ) = (
            SELECT GROUP_CONCAT(c_all3.card_name ORDER BY cc_all3.card_id ASC SEPARATOR '|||') 
            FROM card_combination cc_ref3 
            JOIN card_combination cc_all3 ON cc_ref3.combination_id = cc_all3.combination_id 
            JOIN card c_all3 ON cc_all3.card_id = c_all3.card_id 
            WHERE cc_ref3.card_id = :target_id
        )`
	} else {
		sqlQuery += " AND ccb.combination_id IS NULL "
	}
	sqlQuery += " ORDER BY cd.release_date ASC"

	params := map[string]interface{}{":name": cardName}
	if isCombo {
		params[":target_id"] = cardId
	}

	execSql, args := buildNamedQuery(sqlQuery, params)
	rows, err := db.Query(execSql, args...)
	if err != nil {
		log.Printf("[CardVersionsApi Queryエラー] %v", err)
		json.NewEncoder(w).Encode([]interface{}{})
		return
	}

	results, err := rowsToMapSlice(rows)
	if err != nil {
		log.Printf("[CardVersionsApi Scanエラー] %v", err)
		json.NewEncoder(w).Encode([]interface{}{})
		return
	}

	json.NewEncoder(w).Encode(results)
}

func (c *CardController) CardCombinationApi(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json; charset=utf-8")
	cardId := r.URL.Query().Get("card_id")
	if cardId == "" {
		w.WriteHeader(http.StatusBadRequest)
		json.NewEncoder(w).Encode([]interface{}{})
		return
	}

	db, err := models.DatabaseConnect()
	if err != nil {
		log.Printf("[CardCombinationApi DBエラー] %v", err)
		w.WriteHeader(http.StatusInternalServerError)
		json.NewEncoder(w).Encode([]interface{}{})
		return
	}
	// defer db.Close()

	sqlQuery := `SELECT 
                c.card_id, 
                c.card_name, 
                c.cost, 
                c.pow,
                c.text,
                COALESCE(cd.imagepath, (SELECT imagepath FROM card_detail WHERE card_id = c.card_id AND imagepath IS NOT NULL LIMIT 1)) as imagepath,
                COALESCE(cd.twinpact, (SELECT twinpact FROM card_detail WHERE card_id = c.card_id LIMIT 1), 0) as twinpact,
                (SELECT GROUP_CONCAT(civilization_id) FROM card_civilization WHERE card_id = c.card_id) as civ_ids,
                (SELECT GROUP_CONCAT(characteristics_id) FROM card_characteristics WHERE card_id = c.card_id) as char_ids,
                (SELECT GROUP_CONCAT(cardtype_id) FROM card_cardtype WHERE card_id = c.card_id) as cardtype_ids,
                (SELECT GROUP_CONCAT(typename SEPARATOR '/') FROM card_cardtype WHERE card_id = c.card_id) as typename,
                (SELECT GROUP_CONCAT(race_id) FROM card_race WHERE card_id = c.card_id) as race_ids,
                (SELECT GROUP_CONCAT(r.race_name SEPARATOR '/') FROM card_race cr JOIN race r ON cr.race_id = r.race_id WHERE cr.card_id = c.card_id) as race_names
            FROM card_combination cc
            JOIN card_combination cc_all ON cc.combination_id = cc_all.combination_id
            JOIN card c ON cc_all.card_id = c.card_id
            LEFT JOIN card_detail cd ON c.card_id = cd.card_id AND cd.is_primary_version = 1
            WHERE cc.card_id = ?
            ORDER BY cc_all.card_id ASC`

	rows, err := db.Query(sqlQuery, cardId)
	if err != nil {
		log.Printf("[CardCombinationApi Queryエラー card_id=%s] %v", cardId, err)
		json.NewEncoder(w).Encode([]interface{}{})
		return
	}

	results, err := rowsToMapSlice(rows)
	if err != nil {
		log.Printf("[CardCombinationApi Scanエラー card_id=%s] %v", cardId, err)
		json.NewEncoder(w).Encode([]interface{}{})
		return
	}

	if len(results) == 0 {
		fallbackSql := `SELECT 
                            c.card_id, 
                            c.card_name, 
                            c.cost, 
                            c.pow,
                            c.text,
                            cd.imagepath,
                            cd.twinpact,
                            (SELECT GROUP_CONCAT(civilization_id) FROM card_civilization WHERE card_id = c.card_id) as civ_ids,
                            (SELECT GROUP_CONCAT(characteristics_id) FROM card_characteristics WHERE card_id = c.card_id) as char_ids,
                            (SELECT GROUP_CONCAT(cardtype_id) FROM card_cardtype WHERE card_id = c.card_id) as cardtype_ids,
                            (SELECT GROUP_CONCAT(typename SEPARATOR '/') FROM card_cardtype WHERE card_id = c.card_id) as typename,
                            (SELECT GROUP_CONCAT(race_id) FROM card_race WHERE card_id = c.card_id) as race_ids,
                            (SELECT GROUP_CONCAT(r.race_name SEPARATOR '/') FROM card_race cr JOIN race r ON cr.race_id = r.race_id WHERE cr.card_id = c.card_id) as race_names
                        FROM card c
                        LEFT JOIN card_detail cd ON c.card_id = cd.card_id AND cd.is_primary_version = 1
                        WHERE c.card_id = ?
                        LIMIT 1`
		fbRows, fbErr := db.Query(fallbackSql, cardId)
		if fbErr == nil {
			results, _ = rowsToMapSlice(fbRows)
		} else {
			log.Printf("[CardCombinationApi Fallbackエラー card_id=%s] %v", cardId, fbErr)
		}
	}

	json.NewEncoder(w).Encode(results)
}

func (c *CardController) MasterDataExtendedApi(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json; charset=utf-8")
	db, err := models.DatabaseConnect()
	if err != nil {
		w.WriteHeader(http.StatusInternalServerError)
		json.NewEncoder(w).Encode(map[string]string{"error": err.Error()})
		return
	}
	// defer db.Close()

	safeQuery := func(query string) []map[string]interface{} {
		rows, err := db.Query(query)
		if err != nil {
			log.Printf("[MasterDataExtendedApi エラー] %s : %v", query, err)
			return []map[string]interface{}{}
		}
		res, err := rowsToMapSlice(rows)
		if err != nil {
			log.Printf("[MasterDataExtendedApi スキャンエラー] %s : %v", query, err)
			return []map[string]interface{}{}
		}
		return res
	}

	cardtypes := safeQuery("SELECT cardtype_id, cardtype_name FROM cardtype ORDER BY cardtype_id ASC")
	characteristics := safeQuery("SELECT characteristics_id, characteristics_name FROM characteristics ORDER BY characteristics_id ASC")
	goods := safeQuery("SELECT goods_id, goods_name FROM goods ORDER BY goods_id DESC")
	rarities := safeQuery("SELECT rarity_id, rarity_name FROM rarity ORDER BY rarity_id ASC")

	json.NewEncoder(w).Encode(map[string]interface{}{
		"cardtypes":       cardtypes,
		"characteristics": characteristics,
		"goods":           goods,
		"rarities":        rarities,
	})
}

func (c *CardController) HelpSearchApi(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json; charset=utf-8")

	// Controllers\AuthController::checkAdminOrDeveloper() (app/Controllers/AuthController.go)
	if !CheckAdminOrDeveloper(w, r) {
		w.WriteHeader(http.StatusForbidden)
		json.NewEncoder(w).Encode(map[string]string{"error": "この操作を行う権限がありません。"})
		return
	}

	query := r.URL.Query()
	q := query.Get("q")
	scopes := parseStringSlice(query.Get("scope"))
	if len(scopes) == 0 {
		scopes = []string{"name"}
	}
	sort := query.Get("sort")
	if sort == "" {
		sort = "newest"
	}
	civType := query.Get("civ_type")
	civs := parseIntSlice(parseStringSlice(query.Get("civs")))
	excludeCivs := parseIntSlice(parseStringSlice(query.Get("exclude_civs")))
	civMatchType := query.Get("civ_match_type")
	if civMatchType == "" {
		civMatchType = "include"
	}

	races := parseIntSlice(parseStringSlice(query.Get("races")))
	abilities := parseIntSlice(parseStringSlice(query.Get("abilities")))
	characteristics := parseIntSlice(parseStringSlice(query.Get("characteristics")))
	cardtypes := parseIntSlice(parseStringSlice(query.Get("cardtypes")))
	rarities := parseIntSlice(parseStringSlice(query.Get("rarities")))
	goods := parseIntSlice(parseStringSlice(query.Get("goods")))
	oldestOnly := query.Get("oldest_only") == "1"

	limit := 100
	if val := query.Get("limit"); val != "" {
		if l, err := strconv.Atoi(val); err == nil {
			limit = l
		}
	}
	offset := 0
	if val := query.Get("offset"); val != "" {
		if o, err := strconv.Atoi(val); err == nil {
			offset = o
		}
	}

	db, err := models.DatabaseConnect()
	if err != nil {
		w.WriteHeader(http.StatusInternalServerError)
		json.NewEncoder(w).Encode(map[string]string{"error": err.Error()})
		return
	}
	// defer db.Close()

	params := make(map[string]interface{})

	sqlQuery := `
        SELECT DISTINCT c.card_id, c.card_name, cd.imagepath, cd.release_date, c.cost, c.reading
        FROM card c
        JOIN card_detail cd ON c.card_id = cd.card_id
        WHERE cd.imagepath IS NOT NULL 
        AND cd.imagepath <> ''
    `

	if oldestOnly {
		sqlQuery += ` AND c.card_id = (
            SELECT c2.card_id
            FROM card c2
            JOIN card_detail cd2 ON c2.card_id = cd2.card_id
            WHERE c2.card_name = c.card_name
            AND cd2.imagepath IS NOT NULL AND cd2.imagepath <> ''
            ORDER BY cd2.release_date ASC, c2.card_id ASC
            LIMIT 1
        )`
	}

	if q != "" {
		qKata := convertKana(q, "C")
		qHira := convertKana(q, "c")
		qClean := cleanSearchString(q)
		qKataClean := cleanSearchString(qKata)
		qHiraClean := cleanSearchString(qHira)

		var conds []string
		if containsString(scopes, "name") {
			conds = append(conds, `(
                REPLACE(REPLACE(REPLACE(c.card_name, '・', ''), ' ', ''), '　', '') LIKE :q_name_clean
                OR REPLACE(REPLACE(REPLACE(c.card_name, '・', ''), ' ', ''), '　', '') LIKE :q_name_kata_clean
                OR REPLACE(REPLACE(REPLACE(c.card_name, '・', ''), ' ', ''), '　', '') LIKE :q_name_hira_clean
            )`)
			params[":q_name_clean"] = "%" + qClean + "%"
			params[":q_name_kata_clean"] = "%" + qKataClean + "%"
			params[":q_name_hira_clean"] = "%" + qHiraClean + "%"
		}

		if containsString(scopes, "reading") {
			conds = append(conds, `(
                REPLACE(REPLACE(REPLACE(c.reading, '・', ''), ' ', ''), '　', '') LIKE :q_read_kata_clean
                OR REPLACE(REPLACE(REPLACE(c.reading, '・', ''), ' ', ''), '　', '') LIKE :q_read_hira_clean
            )`)
			params[":q_read_kata_clean"] = "%" + qKataClean + "%"
			params[":q_read_hira_clean"] = "%" + qHiraClean + "%"
		}

		if containsString(scopes, "race") {
			conds = append(conds, `EXISTS (
                SELECT 1 FROM card_race cr_s 
                JOIN race r_s ON cr_s.race_id = r_s.race_id 
                WHERE cr_s.card_id = c.card_id AND (
                    REPLACE(REPLACE(REPLACE(r_s.race_name, '・', ''), ' ', ''), '　', '') LIKE :q_race_clean
                    OR REPLACE(REPLACE(REPLACE(r_s.reading, '・', ''), ' ', ''), '　', '') LIKE :q_race_read_kata_clean
                    OR REPLACE(REPLACE(REPLACE(r_s.reading, '・', ''), ' ', ''), '　', '') LIKE :q_race_read_hira_clean
                )
            )`)
			params[":q_race_clean"] = "%" + qClean + "%"
			params[":q_race_read_kata_clean"] = "%" + qKataClean + "%"
			params[":q_race_read_hira_clean"] = "%" + qHiraClean + "%"
		}

		if containsString(scopes, "text") {
			conds = append(conds, "c.text LIKE :q_text")
			params[":q_text"] = "%" + q + "%"
		}

		if len(conds) > 0 {
			sqlQuery += " AND (" + strings.Join(conds, " OR ") + ")"
		}
	}

	if civType == "single" {
		sqlQuery += " AND (SELECT COUNT(*) FROM card_civilization cc WHERE cc.card_id = c.card_id) = 1"
	} else if civType == "multi" {
		sqlQuery += " AND (SELECT COUNT(*) FROM card_civilization cc WHERE cc.card_id = c.card_id) >= 2"
	} else if civType == "none" {
		sqlQuery += " AND 1=0"
	}

	if len(civs) > 0 {
		if containsInt(civs, -1) {
			var otherCivs []int
			for _, v := range civs {
				if v != -1 {
					otherCivs = append(otherCivs, v)
				}
			}
			if len(otherCivs) > 0 {
				civList := joinInts(otherCivs)
				sqlQuery += fmt.Sprintf(" AND (NOT EXISTS (SELECT 1 FROM card_civilization cc WHERE cc.card_id = c.card_id) OR EXISTS (SELECT 1 FROM card_civilization cc WHERE cc.card_id = c.card_id AND cc.civilization_id IN (%s)))", civList)
			} else {
				sqlQuery += " AND NOT EXISTS (SELECT 1 FROM card_civilization cc WHERE cc.card_id = c.card_id)"
			}
		} else {
			civList := joinInts(civs)
			if civMatchType == "match" {
				for _, cId := range civs {
					sqlQuery += fmt.Sprintf(" AND EXISTS (SELECT 1 FROM card_civilization cc WHERE cc.card_id = c.card_id AND cc.civilization_id = %d)", cId)
				}
				sqlQuery += fmt.Sprintf(" AND NOT EXISTS (SELECT 1 FROM card_civilization cc WHERE cc.card_id = c.card_id AND cc.civilization_id NOT IN (%s))", civList)
			} else {
				sqlQuery += fmt.Sprintf(" AND EXISTS (SELECT 1 FROM card_civilization cc WHERE cc.card_id = c.card_id AND cc.civilization_id IN (%s))", civList)
			}
		}
	}

	if len(excludeCivs) > 0 {
		excludeList := joinInts(excludeCivs)
		sqlQuery += fmt.Sprintf(" AND NOT EXISTS (SELECT 1 FROM card_civilization cc WHERE cc.card_id = c.card_id AND cc.civilization_id IN (%s))", excludeList)
	}

	if len(races) > 0 {
		if containsInt(races, -1) {
			var otherRaces []int
			for _, v := range races {
				if v != -1 {
					otherRaces = append(otherRaces, v)
				}
			}
			if len(otherRaces) > 0 {
				idList := joinInts(otherRaces)
				sqlQuery += fmt.Sprintf(" AND (NOT EXISTS (SELECT 1 FROM card_race cr WHERE cr.card_id = c.card_id) OR EXISTS (SELECT 1 FROM card_race cr WHERE cr.card_id = c.card_id AND cr.race_id IN (%s)))", idList)
			} else {
				sqlQuery += " AND NOT EXISTS (SELECT 1 FROM card_race cr WHERE cr.card_id = c.card_id)"
			}
		} else {
			idList := joinInts(races)
			sqlQuery += fmt.Sprintf(" AND EXISTS (SELECT 1 FROM card_race cr WHERE cr.card_id = c.card_id AND cr.race_id IN (%s))", idList)
		}
	}

	if len(abilities) > 0 {
		if containsInt(abilities, -1) {
			var otherAbilities []int
			for _, v := range abilities {
				if v != -1 {
					otherAbilities = append(otherAbilities, v)
				}
			}
			if len(otherAbilities) > 0 {
				idList := joinInts(otherAbilities)
				sqlQuery += fmt.Sprintf(" AND (NOT EXISTS (SELECT 1 FROM card_ability ca WHERE ca.card_id = c.card_id) OR EXISTS (SELECT 1 FROM card_ability ca WHERE ca.card_id = c.card_id AND ca.ability_id IN (%s)))", idList)
			} else {
				sqlQuery += " AND NOT EXISTS (SELECT 1 FROM card_ability ca WHERE ca.card_id = c.card_id)"
			}
		} else {
			idList := joinInts(abilities)
			sqlQuery += fmt.Sprintf(" AND EXISTS (SELECT 1 FROM card_ability ca WHERE ca.card_id = c.card_id AND ca.ability_id IN (%s))", idList)
		}
	}

	if len(characteristics) > 0 {
		if containsInt(characteristics, -1) {
			var otherChars []int
			for _, v := range characteristics {
				if v != -1 {
					otherChars = append(otherChars, v)
				}
			}
			if len(otherChars) > 0 {
				idList := joinInts(otherChars)
				sqlQuery += fmt.Sprintf(" AND (NOT EXISTS (SELECT 1 FROM card_characteristics c_char WHERE c_char.card_id = c.card_id) OR EXISTS (SELECT 1 FROM card_characteristics c_char WHERE c_char.card_id = c.card_id AND c_char.characteristics_id IN (%s)))", idList)
			} else {
				sqlQuery += " AND NOT EXISTS (SELECT 1 FROM card_characteristics c_char WHERE c_char.card_id = c.card_id)"
			}
		} else {
			idList := joinInts(characteristics)
			sqlQuery += fmt.Sprintf(" AND EXISTS (SELECT 1 FROM card_characteristics c_char WHERE c_char.card_id = c.card_id AND c_char.characteristics_id IN (%s))", idList)
		}
	}

	if len(cardtypes) > 0 {
		if containsInt(cardtypes, -1) {
			var otherTypes []int
			for _, v := range cardtypes {
				if v != -1 {
					otherTypes = append(otherTypes, v)
				}
			}
			if len(otherTypes) > 0 {
				idList := joinInts(otherTypes)
				sqlQuery += fmt.Sprintf(" AND (NOT EXISTS (SELECT 1 FROM card_cardtype c_type WHERE c_type.card_id = c.card_id) OR EXISTS (SELECT 1 FROM card_cardtype c_type WHERE c_type.card_id = c.card_id AND c_type.cardtype_id IN (%s)))", idList)
			} else {
				sqlQuery += " AND NOT EXISTS (SELECT 1 FROM card_cardtype c_type WHERE c_type.card_id = c.card_id)"
			}
		} else {
			idList := joinInts(cardtypes)
			sqlQuery += fmt.Sprintf(" AND EXISTS (SELECT 1 FROM card_cardtype c_type WHERE c_type.card_id = c.card_id AND c_type.cardtype_id IN (%s))", idList)
		}
	}

	if len(rarities) > 0 {
		if containsInt(rarities, -1) {
			var otherRarities []int
			for _, v := range rarities {
				if v != -1 {
					otherRarities = append(otherRarities, v)
				}
			}
			if len(otherRarities) > 0 {
				idList := joinInts(otherRarities)
				sqlQuery += fmt.Sprintf(" AND (NOT EXISTS (SELECT 1 FROM card_rarity cr_rarity WHERE cr_rarity.card_id = c.card_id) OR EXISTS (SELECT 1 FROM card_rarity cr_rarity WHERE cr_rarity.card_id = c.card_id AND cr_rarity.rarity_id IN (%s)))", idList)
			} else {
				sqlQuery += " AND NOT EXISTS (SELECT 1 FROM card_rarity cr_rarity WHERE cr_rarity.card_id = c.card_id)"
			}
		} else {
			idList := joinInts(rarities)
			sqlQuery += fmt.Sprintf(" AND EXISTS (SELECT 1 FROM card_rarity cr_rarity WHERE cr_rarity.card_id = c.card_id AND cr_rarity.rarity_id IN (%s))", idList)
		}
	}

	if len(goods) > 0 {
		if containsInt(goods, -1) {
			var otherGoods []int
			for _, v := range goods {
				if v != -1 {
					otherGoods = append(otherGoods, v)
				}
			}
			if len(otherGoods) > 0 {
				idList := joinInts(otherGoods)
				sqlQuery += fmt.Sprintf(" AND (cd.goods_id IS NULL OR cd.goods_id IN (%s))", idList)
			} else {
				sqlQuery += " AND cd.goods_id IS NULL"
			}
		} else {
			idList := joinInts(goods)
			sqlQuery += fmt.Sprintf(" AND cd.goods_id IN (%s)", idList)
		}
	}

	orderDirection := "DESC"
	if sort == "oldest" {
		orderDirection = "ASC"
	}
	sqlQuery += fmt.Sprintf(" ORDER BY cd.release_date %s, c.cost ASC, c.reading ASC LIMIT %d OFFSET %d", orderDirection, limit, offset)

	execSql, args := buildNamedQuery(sqlQuery, params)
	rows, err := db.Query(execSql, args...)
	if err != nil {
		w.WriteHeader(http.StatusInternalServerError)
		json.NewEncoder(w).Encode(map[string]string{"error": err.Error()})
		return
	}

	results, err := rowsToMapSlice(rows)
	if err != nil {
		w.WriteHeader(http.StatusInternalServerError)
		json.NewEncoder(w).Encode(map[string]string{"error": err.Error()})
		return
	}

	json.NewEncoder(w).Encode(results)
}

func (c *CardController) HelpDetailApi(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json; charset=utf-8")

	if !CheckAdminOrDeveloper(w, r) {
		w.WriteHeader(http.StatusForbidden)
		json.NewEncoder(w).Encode(map[string]string{"error": "この操作を行う権限がありません。"})
		return
	}

	cardId := r.URL.Query().Get("card_id")
	if cardId == "" {
		w.WriteHeader(http.StatusBadRequest)
		json.NewEncoder(w).Encode(map[string]string{"error": "Card ID is required"})
		return
	}

	db, err := models.DatabaseConnect()
	if err != nil {
		w.WriteHeader(http.StatusInternalServerError)
		json.NewEncoder(w).Encode(map[string]string{"error": err.Error()})
		return
	}
	// defer db.Close()

	sqlQuery := `
        SELECT 
            c.card_id,
            c.card_name,
            c.reading,
            c.pow,
            c.cost,
            c.text,
            c.flavortext,
            cd.imagepath,
            g.goods_name,
            (SELECT GROUP_CONCAT(civilization_id) FROM card_civilization WHERE card_id = c.card_id) as civilizations_ids,
            (SELECT GROUP_CONCAT(race_id) FROM card_race WHERE card_id = c.card_id) as race_ids,
            (SELECT GROUP_CONCAT(ability_id) FROM card_ability WHERE card_id = c.card_id) as ability_ids,
            (SELECT GROUP_CONCAT(rarity_id) FROM card_rarity WHERE card_id = c.card_id) as rarity_ids,
            (SELECT GROUP_CONCAT(characteristics_id) FROM card_characteristics WHERE card_id = c.card_id) as characteristic_ids,
            (SELECT GROUP_CONCAT(cardtype_id) FROM card_cardtype WHERE card_id = c.card_id) as cardtype_ids,
            (SELECT GROUP_CONCAT(typename SEPARATOR '/') FROM card_cardtype WHERE card_id = c.card_id) as typename
        FROM card c
        JOIN card_detail cd ON c.card_id = cd.card_id
        LEFT JOIN goods g ON cd.goods_id = g.goods_id
        WHERE c.card_id = ?
        LIMIT 1
    `

	rows, err := db.Query(sqlQuery, cardId)
	if err != nil {
		w.WriteHeader(http.StatusInternalServerError)
		json.NewEncoder(w).Encode(map[string]string{"error": err.Error()})
		return
	}

	results, err := rowsToMapSlice(rows)
	if err != nil || len(results) == 0 {
		w.WriteHeader(http.StatusNotFound)
		json.NewEncoder(w).Encode(map[string]string{"error": "Card not found"})
		return
	}

	json.NewEncoder(w).Encode(results[0])
}

type HelpUpdateInput struct {
	CardID          interface{}   `json:"card_id"`
	CardName        string        `json:"card_name"`
	Reading         string        `json:"reading"`
	Cost            interface{}   `json:"cost"`
	Pow             interface{}   `json:"pow"`
	Text            string        `json:"text"`
	Flavortext      string        `json:"flavortext"`
	Civilizations   []interface{} `json:"civilizations"`
	Races           []interface{} `json:"races"`
	Abilities       []interface{} `json:"abilities"`
	Rarities        []interface{} `json:"rarities"`
	Characteristics []interface{} `json:"characteristics"`
	Cardtypes       []interface{} `json:"cardtypes"`
}

func (c *CardController) HelpUpdateApi(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json; charset=utf-8")

	if !CheckAdminOrDeveloper(w, r) {
		w.WriteHeader(http.StatusForbidden)
		json.NewEncoder(w).Encode(map[string]string{"error": "この操作を行う権限がありません。"})
		return
	}

	var input HelpUpdateInput
	if err := json.NewDecoder(r.Body).Decode(&input); err != nil || input.CardID == nil {
		w.WriteHeader(http.StatusBadRequest)
		json.NewEncoder(w).Encode(map[string]string{"error": "無効なデータです。"})
		return
	}

	var cardId int
	switch v := input.CardID.(type) {
	case float64:
		cardId = int(v)
	case string:
		cardId, _ = strconv.Atoi(v)
	}

	if cardId == 0 {
		w.WriteHeader(http.StatusBadRequest)
		json.NewEncoder(w).Encode(map[string]string{"error": "無効なデータです。"})
		return
	}

	var cost sql.NullInt64
	if input.Cost != nil && input.Cost != "" {
		if cVal, err := strconv.ParseInt(fmt.Sprint(input.Cost), 10, 64); err == nil {
			cost = sql.NullInt64{Int64: cVal, Valid: true}
		}
	}

	var pow sql.NullInt64
	if input.Pow != nil && input.Pow != "" {
		if pVal, err := strconv.ParseInt(fmt.Sprint(input.Pow), 10, 64); err == nil {
			pow = sql.NullInt64{Int64: pVal, Valid: true}
		}
	}

	db, err := models.DatabaseConnect()
	if err != nil {
		w.WriteHeader(http.StatusInternalServerError)
		json.NewEncoder(w).Encode(map[string]string{"error": err.Error()})
		return
	}
	// defer db.Close()

	tx, err := db.Begin()
	if err != nil {
		w.WriteHeader(http.StatusInternalServerError)
		json.NewEncoder(w).Encode(map[string]string{"error": err.Error()})
		return
	}

	defer func() {
		if p := recover(); p != nil {
			tx.Rollback()
			panic(p)
		}
	}()

	// 1. cardテーブル（基本情報）のアップデート
	sqlCard := `
        UPDATE card 
        SET card_name = ?, reading = ?, cost = ?, pow = ?, text = ?, flavortext = ? 
        WHERE card_id = ?
    `
	_, err = tx.Exec(sqlCard, input.CardName, input.Reading, cost, pow, input.Text, input.Flavortext, cardId)
	if err != nil {
		tx.Rollback()
		w.WriteHeader(http.StatusInternalServerError)
		json.NewEncoder(w).Encode(map[string]string{"error": err.Error()})
		return
	}

	// 2. 文明中間テーブルの削除＆再登録
	if _, err = tx.Exec("DELETE FROM card_civilization WHERE card_id = ?", cardId); err != nil {
		tx.Rollback()
		w.WriteHeader(http.StatusInternalServerError)
		json.NewEncoder(w).Encode(map[string]string{"error": err.Error()})
		return
	}
	for _, cItem := range input.Civilizations {
		cId, _ := strconv.Atoi(fmt.Sprint(cItem))
		if _, err = tx.Exec("INSERT INTO card_civilization (card_id, civilization_id) VALUES (?, ?)", cardId, cId); err != nil {
			tx.Rollback()
			w.WriteHeader(http.StatusInternalServerError)
			json.NewEncoder(w).Encode(map[string]string{"error": err.Error()})
			return
		}
	}

	// 3. 種族中間テーブルの削除＆再登録
	if _, err = tx.Exec("DELETE FROM card_race WHERE card_id = ?", cardId); err != nil {
		tx.Rollback()
		w.WriteHeader(http.StatusInternalServerError)
		json.NewEncoder(w).Encode(map[string]string{"error": err.Error()})
		return
	}
	for _, rItem := range input.Races {
		rId, _ := strconv.Atoi(fmt.Sprint(rItem))
		if _, err = tx.Exec("INSERT INTO card_race (card_id, race_id) VALUES (?, ?)", cardId, rId); err != nil {
			tx.Rollback()
			w.WriteHeader(http.StatusInternalServerError)
			json.NewEncoder(w).Encode(map[string]string{"error": err.Error()})
			return
		}
	}

	// 4. 特殊能力中間テーブルの削除＆再登録
	if _, err = tx.Exec("DELETE FROM card_ability WHERE card_id = ?", cardId); err != nil {
		tx.Rollback()
		w.WriteHeader(http.StatusInternalServerError)
		json.NewEncoder(w).Encode(map[string]string{"error": err.Error()})
		return
	}
	for _, aItem := range input.Abilities {
		aId, _ := strconv.Atoi(fmt.Sprint(aItem))
		if _, err = tx.Exec("INSERT INTO card_ability (card_id, ability_id) VALUES (?, ?)", cardId, aId); err != nil {
			tx.Rollback()
			w.WriteHeader(http.StatusInternalServerError)
			json.NewEncoder(w).Encode(map[string]string{"error": err.Error()})
			return
		}
	}

	// 5. レアリティ中間テーブルの削除＆再登録
	if _, err = tx.Exec("DELETE FROM card_rarity WHERE card_id = ?", cardId); err != nil {
		tx.Rollback()
		w.WriteHeader(http.StatusInternalServerError)
		json.NewEncoder(w).Encode(map[string]string{"error": err.Error()})
		return
	}
	for _, raItem := range input.Rarities {
		raId, _ := strconv.Atoi(fmt.Sprint(raItem))
		if _, err = tx.Exec("INSERT INTO card_rarity (card_id, rarity_id) VALUES (?, ?)", cardId, raId); err != nil {
			tx.Rollback()
			w.WriteHeader(http.StatusInternalServerError)
			json.NewEncoder(w).Encode(map[string]string{"error": err.Error()})
			return
		}
	}

	// 6. 特殊タイプ中間テーブルの削除＆再登録
	if _, err = tx.Exec("DELETE FROM card_characteristics WHERE card_id = ?", cardId); err != nil {
		tx.Rollback()
		w.WriteHeader(http.StatusInternalServerError)
		json.NewEncoder(w).Encode(map[string]string{"error": err.Error()})
		return
	}
	for _, chItem := range input.Characteristics {
		chId, _ := strconv.Atoi(fmt.Sprint(chItem))
		if _, err = tx.Exec("INSERT INTO card_characteristics (card_id, characteristics_id) VALUES (?, ?)", cardId, chId); err != nil {
			tx.Rollback()
			w.WriteHeader(http.StatusInternalServerError)
			json.NewEncoder(w).Encode(map[string]string{"error": err.Error()})
			return
		}
	}

	// 7. カードタイプ中間テーブルの削除＆再登録
	if _, err = tx.Exec("DELETE FROM card_cardtype WHERE card_id = ?", cardId); err != nil {
		tx.Rollback()
		w.WriteHeader(http.StatusInternalServerError)
		json.NewEncoder(w).Encode(map[string]string{"error": err.Error()})
		return
	}
	for _, ctItem := range input.Cardtypes {
		ctId, _ := strconv.Atoi(fmt.Sprint(ctItem))
		stmt := `
            INSERT INTO card_cardtype (card_id, cardtype_id, typename)
            SELECT ?, cardtype_id, cardtype_name 
            FROM cardtype 
            WHERE cardtype_id = ?
        `
		if _, err = tx.Exec(stmt, cardId, ctId); err != nil {
			tx.Rollback()
			w.WriteHeader(http.StatusInternalServerError)
			json.NewEncoder(w).Encode(map[string]string{"error": err.Error()})
			return
		}
	}

	if err := tx.Commit(); err != nil {
		w.WriteHeader(http.StatusInternalServerError)
		json.NewEncoder(w).Encode(map[string]string{"error": err.Error()})
		return
	}

	json.NewEncoder(w).Encode(map[string]bool{"success": true})
}
