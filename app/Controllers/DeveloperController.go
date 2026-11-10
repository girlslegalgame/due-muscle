package controllers

import (
	"database/sql"
	views "deck-maker/app/Views"
	"encoding/json"
	"fmt"
	"html/template"
	"net/http"
	"strconv"
	"strings"
)

type DeveloperController struct {
	db *sql.DB
}

func NewDeveloperController(db *sql.DB) *DeveloperController {
	return &DeveloperController{db: db}
}

// 権限チェック（admin または developer）
func (c *DeveloperController) checkAuth(w http.ResponseWriter, r *http.Request) (int, bool) {
	uid, ok := getSessionUserID(r)
	if !ok {
		if strings.Contains(r.Header.Get("Accept"), "application/json") {
			w.Header().Set("Content-Type", "application/json; charset=utf-8")
			w.WriteHeader(http.StatusUnauthorized)
			json.NewEncoder(w).Encode(map[string]string{"error": "ログインが必要です。"})
		} else {
			http.Redirect(w, r, "/login", http.StatusFound)
		}
		return 0, false
	}

	db := getDB()
	var role string
	err := db.QueryRow("SELECT role FROM users WHERE user_id = ? LIMIT 1", uid).Scan(&role)
	if err != nil || (role != "admin" && role != "developer") {
		w.Header().Set("Content-Type", "application/json; charset=utf-8")
		w.WriteHeader(http.StatusForbidden)
		json.NewEncoder(w).Encode(map[string]string{"error": "アクセス権限がありません。"})
		return 0, false
	}

	return uid, true
}

func (c *DeveloperController) Index(w http.ResponseWriter, r *http.Request) {
	if _, ok := c.checkAuth(w, r); !ok {
		return
	}
	db := getDB()
	rows, _ := db.Query("SELECT * FROM formats ORDER BY format_id ASC")
	formats, _ := rowsToMapSlice(rows)

	views.RenderView(w, r, "developer/index.html", map[string]interface{}{
		"formats": formats,
	})
}

func (c *DeveloperController) TemplateCreate(w http.ResponseWriter, r *http.Request) {
	if _, ok := c.checkAuth(w, r); !ok {
		return
	}
	db := getDB()
	fRows, _ := db.Query("SELECT format_id, format_name FROM formats ORDER BY format_id ASC")
	formats, _ := rowsToMapSlice(fRows)

	gRows, _ := db.Query("SELECT goods_id, goods_name FROM goods WHERE goodstype_id = 3 ORDER BY goods_id ASC")
	officialGoods, _ := rowsToMapSlice(gRows)

	formatsJSON, _ := json.Marshal(formats)
	officialGoodsJSON, _ := json.Marshal(officialGoods)

	views.RenderView(w, r, "developer/template_edit.html", map[string]interface{}{
		"hideFooter":            true,
		"isTemplate":            true,
		"formats":               formats,
		"formatsJSON":           template.JS(formatsJSON),
		"officialGoods":         officialGoods,
		"officialGoodsJSON":     template.JS(officialGoodsJSON),
		"isEditJSON":            template.JS("false"),
		"deckIDJSON":            template.JS("null"),
		"deckConfigJSON":        template.JS("{}"),
		"initialDeckNameJSON":   template.JS(`""`),
		"initialCardsJSON":      template.JS("[]"),
		"initialDescJSON":       template.JS(`""`),
		"selectedIsOfficialJSON": template.JS("0"),
		"selectedGoodsIdJSON":   template.JS("null"),
	})
}

func (c *DeveloperController) TemplateEdit(w http.ResponseWriter, r *http.Request) {
	if _, ok := c.checkAuth(w, r); !ok {
		return
	}
	templateIdStr := r.URL.Query().Get("template_id")
	templateId, _ := strconv.Atoi(templateIdStr)
	if templateId == 0 {
		http.Redirect(w, r, "/developer", http.StatusFound)
		return
	}

	db := getDB()
	tRows, err := db.Query("SELECT * FROM deck_templates WHERE template_id = ?", templateId)
	if err != nil {
		http.Redirect(w, r, "/developer", http.StatusFound)
		return
	}
	tList, _ := rowsToMapSlice(tRows)
	if len(tList) == 0 {
		http.Redirect(w, r, "/developer", http.StatusFound)
		return
	}
	tpl := tList[0]

	cRows, _ := db.Query(`
		SELECT 
			dtc.template_id,
			COALESCE(c.card_id, CONCAT('dummy_', dtc.sort_order)) as card_id,
			dtc.quantity,
			dtc.card_type_in_deck,
			dtc.sort_order,
			CASE 
				WHEN dtc.dummy_card_type = 'twinpact' AND dtc.dummy_partner_name IS NOT NULL AND dtc.dummy_partner_name <> '' THEN 
					CONCAT(dtc.dummy_name, ' / ', dtc.dummy_partner_name)
				ELSE 
					COALESCE(c.card_name, dtc.dummy_name)
			END as card_name,
			c.reading,
			COALESCE(c.cost, dtc.dummy_cost) as cost,
			c.pow,
			COALESCE(
				cd.imagepath,
				(SELECT cd2.imagepath FROM card_detail cd2 WHERE cd2.card_id = c.card_id AND cd2.imagepath IS NOT NULL AND cd2.imagepath != '' LIMIT 1),
				(SELECT cd3.imagepath FROM card_combination cc1 JOIN card_combination cc2 ON cc1.combination_id = cc2.combination_id JOIN card_detail cd3 ON cc2.card_id = cd3.card_id WHERE cc1.card_id = c.card_id AND cd3.imagepath IS NOT NULL AND cd3.imagepath != '' LIMIT 1),
				''
			) as imagepath,
			COALESCE(cd.`+"`limit`"+`, 4) as card_limit,
			COALESCE(cd.twinpact, CASE WHEN dtc.dummy_card_type = 'twinpact' THEN 1 ELSE 0 END) as twinpact,
			CASE WHEN dtc.dummy_name IS NOT NULL THEN 1 ELSE 0 END as is_dummy,
			COALESCE(dtc.dummy_card_type, 'normal') as dummy_card_type,
			dtc.dummy_partner_name,
			dtc.dummy_partner_cost,
			dtc.dummy_partner_civ_ids,
			(SELECT GROUP_CONCAT(characteristics_id) FROM card_characteristics WHERE card_id = c.card_id) as char_ids,
			COALESCE((SELECT GROUP_CONCAT(civilization_id) FROM card_civilization WHERE card_id = c.card_id), dtc.dummy_civ_ids) as civ_ids,
			(SELECT GROUP_CONCAT(c_all.card_name ORDER BY cc_all.card_id ASC SEPARATOR '|||') 
			 FROM card_combination cc_ref 
			 JOIN card_combination cc_all ON cc_ref.combination_id = cc_all.combination_id 
			 JOIN card c_all ON cc_all.card_id = c_all.card_id 
			 WHERE cc_ref.card_id = c.card_id) as combo_names
		FROM deck_template_cards dtc
		LEFT JOIN card c ON dtc.card_id = c.card_id
		LEFT JOIN card_detail cd ON c.card_id = cd.card_id AND cd.is_primary_version = 1
		WHERE dtc.template_id = ?
		ORDER BY dtc.sort_order ASC
	`, templateId)
	cards, _ := rowsToMapSlice(cRows)

	fRows, _ := db.Query("SELECT format_id, format_name FROM formats ORDER BY format_id ASC")
	formats, _ := rowsToMapSlice(fRows)

	gRows, _ := db.Query("SELECT goods_id, goods_name FROM goods WHERE goodstype_id = 3 ORDER BY goods_id ASC")
	officialGoods, _ := rowsToMapSlice(gRows)

	formatsJSON, _ := json.Marshal(formats)
	officialGoodsJSON, _ := json.Marshal(officialGoods)
	initialCardsJSON, _ := json.Marshal(cards)
	descJSON, _ := json.Marshal(tpl["description"])
	deckNameJSON, _ := json.Marshal(tpl["template_name"])

	isOfficial := 0
	if fmt.Sprintf("%v", tpl["is_official"]) == "1" {
		isOfficial = 1
	}
	goodsIDJSON := "null"
	if tpl["goods_id"] != nil && fmt.Sprintf("%v", tpl["goods_id"]) != "" {
		goodsIDJSON = fmt.Sprintf("%v", tpl["goods_id"])
	}

	views.RenderView(w, r, "developer/template_edit.html", map[string]interface{}{
		"hideFooter":            true,
		"isTemplate":            true,
		"isEdit":                true,
		"deck":                  tpl,
		"formats":               formats,
		"formatsJSON":           template.JS(formatsJSON),
		"officialGoods":         officialGoods,
		"officialGoodsJSON":     template.JS(officialGoodsJSON),
		"isEditJSON":            template.JS("true"),
		"deckIDJSON":            template.JS(strconv.Itoa(templateId)),
		"initialDeckNameJSON":   template.JS(deckNameJSON),
		"initialCardsJSON":      template.JS(initialCardsJSON),
		"initialDescJSON":       template.JS(descJSON),
		"selectedIsOfficialJSON": template.JS(strconv.Itoa(isOfficial)),
		"selectedGoodsIdJSON":   template.JS(goodsIDJSON),
	})
}

func (c *DeveloperController) GetMasterDataApi(w http.ResponseWriter, r *http.Request) {
	if _, ok := c.checkAuth(w, r); !ok {
		return
	}
	w.Header().Set("Content-Type", "application/json; charset=utf-8")
	mType := r.URL.Query().Get("type")
	allowed := map[string]bool{"race": true, "ability": true, "characteristics": true, "cardtype": true}
	if !allowed[mType] {
		json.NewEncoder(w).Encode(map[string]string{"error": "無効な種別です"})
		return
	}

	db := getDB()
	idCol := mType + "_id"
	nameCol := mType + "_name"
	hasReading := (mType == "race" || mType == "ability")

	sqlStr := fmt.Sprintf("SELECT %s as id, %s as name", idCol, nameCol)
	if hasReading {
		sqlStr += ", reading"
	}
	sqlStr += fmt.Sprintf(" FROM %s ORDER BY %s ASC", mType, idCol)

	rows, err := db.Query(sqlStr)
	if err != nil {
		json.NewEncoder(w).Encode([]interface{}{})
		return
	}
	list, _ := rowsToMapSlice(rows)
	json.NewEncoder(w).Encode(list)
}

func (c *DeveloperController) SaveMasterDataApi(w http.ResponseWriter, r *http.Request) {
	if _, ok := c.checkAuth(w, r); !ok {
		return
	}
	w.Header().Set("Content-Type", "application/json; charset=utf-8")

	var in map[string]interface{}
	json.NewDecoder(r.Body).Decode(&in)

	mType := fmt.Sprintf("%v", in["type"])
	name := strings.TrimSpace(fmt.Sprintf("%v", in["name"]))
	reading := strings.TrimSpace(fmt.Sprintf("%v", in["reading"]))

	allowed := map[string]bool{"race": true, "ability": true, "characteristics": true, "cardtype": true}
	if !allowed[mType] || name == "" || name == "<nil>" {
		json.NewEncoder(w).Encode(map[string]interface{}{"success": false, "error": "入力内容が正しくありません。"})
		return
	}

	id := 0
	if in["id"] != nil && fmt.Sprintf("%v", in["id"]) != "" {
		id, _ = strconv.Atoi(fmt.Sprintf("%v", in["id"]))
	}

	db := getDB()
	idCol := mType + "_id"
	nameCol := mType + "_name"
	hasReading := (mType == "race" || mType == "ability")

	var err error
	if id > 0 {
		if hasReading {
			_, err = db.Exec(fmt.Sprintf("UPDATE %s SET %s = ?, reading = ? WHERE %s = ?", mType, nameCol, idCol), name, reading, id)
		} else {
			_, err = db.Exec(fmt.Sprintf("UPDATE %s SET %s = ? WHERE %s = ?", mType, nameCol, idCol), name, id)
		}
	} else {
		if hasReading {
			_, err = db.Exec(fmt.Sprintf("INSERT INTO %s (%s, reading) VALUES (?, ?)", mType, nameCol), name, reading)
		} else {
			_, err = db.Exec(fmt.Sprintf("INSERT INTO %s (%s) VALUES (?)", mType, nameCol), name)
		}
	}

	if err != nil {
		json.NewEncoder(w).Encode(map[string]interface{}{"success": false, "error": err.Error()})
		return
	}
	json.NewEncoder(w).Encode(map[string]interface{}{"success": true})
}

func (c *DeveloperController) GetTemplatesApi(w http.ResponseWriter, r *http.Request) {
	if _, ok := c.checkAuth(w, r); !ok {
		return
	}
	w.Header().Set("Content-Type", "application/json; charset=utf-8")
	db := getDB()
	sqlStr := `SELECT dt.*, f.format_name, g.goods_name, cd.imagepath as thumbnail_imagepath 
               FROM deck_templates dt
               JOIN formats f ON dt.format_id = f.format_id
               LEFT JOIN goods g ON dt.goods_id = g.goods_id
               LEFT JOIN card_detail cd ON dt.thumbnail_card_id = cd.card_id
               ORDER BY dt.is_official DESC, dt.updated_at DESC`
	rows, err := db.Query(sqlStr)
	if err != nil {
		json.NewEncoder(w).Encode([]interface{}{})
		return
	}
	list, _ := rowsToMapSlice(rows)
	json.NewEncoder(w).Encode(list)
}

func (c *DeveloperController) SaveTemplateApi(w http.ResponseWriter, r *http.Request) {
	if _, ok := c.checkAuth(w, r); !ok {
		return
	}
	w.Header().Set("Content-Type", "application/json; charset=utf-8")

	var in map[string]interface{}
	json.NewDecoder(r.Body).Decode(&in)

	name := strings.TrimSpace(fmt.Sprintf("%v", in["deck_name"]))
	desc := strings.TrimSpace(fmt.Sprintf("%v", in["description"]))
	if desc == "<nil>" {
		desc = ""
	}
	formatId, _ := strconv.Atoi(fmt.Sprintf("%v", in["format_id"]))
	if formatId == 0 {
		formatId = 1
	}
	var thumbId interface{} = nil
	if tid, err := strconv.Atoi(fmt.Sprintf("%v", in["thumbnail_card_id"])); err == nil && tid > 0 {
		thumbId = tid
	}
	isOfficial, _ := strconv.Atoi(fmt.Sprintf("%v", in["is_official"]))
	var goodsId interface{} = nil
	if gid, err := strconv.Atoi(fmt.Sprintf("%v", in["goods_id"])); err == nil && gid > 0 {
		goodsId = gid
	}

	if name == "" || name == "<nil>" {
		json.NewEncoder(w).Encode(map[string]interface{}{"success": false, "error": "テンプレ名を入力してください。"})
		return
	}

	var templateId int64 = 0
	if did, err := strconv.Atoi(fmt.Sprintf("%v", in["deck_id"])); err == nil && did > 0 {
		templateId = int64(did)
	}

	db := getDB()
	tx, err := db.Begin()
	if err != nil {
		json.NewEncoder(w).Encode(map[string]interface{}{"success": false, "error": err.Error()})
		return
	}
	defer tx.Rollback()

	if templateId > 0 {
		_, err = tx.Exec(`UPDATE deck_templates 
                          SET template_name = ?, description = ?, format_id = ?, thumbnail_card_id = ?, is_official = ?, goods_id = ?, updated_at = NOW() 
                          WHERE template_id = ?`,
			name, desc, formatId, thumbId, isOfficial, goodsId, templateId)
		if err != nil {
			json.NewEncoder(w).Encode(map[string]interface{}{"success": false, "error": err.Error()})
			return
		}
		tx.Exec("DELETE FROM deck_template_cards WHERE template_id = ?", templateId)
	} else {
		res, err := tx.Exec(`INSERT INTO deck_templates (template_name, description, format_id, thumbnail_card_id, is_official, goods_id, created_at, updated_at) 
                             VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())`,
			name, desc, formatId, thumbId, isOfficial, goodsId)
		if err != nil {
			json.NewEncoder(w).Encode(map[string]interface{}{"success": false, "error": err.Error()})
			return
		}
		templateId, _ = res.LastInsertId()
	}

	if cards, ok := in["cards"].([]interface{}); ok {
		order := 1
		for _, item := range cards {
			if c, ok := item.(map[string]interface{}); ok {
				cType := fmt.Sprintf("%v", c["type"])
				isDummy := false
				if idm, ok := c["is_dummy"].(bool); ok && idm {
					isDummy = true
				}
				if dn, ok := c["dummy_name"].(string); ok && dn != "" {
					isDummy = true
				}

				if isDummy {
					dName := strings.TrimSpace(fmt.Sprintf("%v", c["dummy_name"]))
					var dCost *int
					if v, err := strconv.Atoi(fmt.Sprintf("%v", c["dummy_cost"])); err == nil {
						dCost = &v
					}
					dCiv := fmt.Sprintf("%v", c["dummy_civ_ids"])
					dType := fmt.Sprintf("%v", c["dummy_card_type"])
					if dType == "" || dType == "<nil>" {
						dType = "normal"
					}
					var dpName *string
					if v := strings.TrimSpace(fmt.Sprintf("%v", c["dummy_partner_name"])); v != "" && v != "<nil>" {
						dpName = &v
					}
					var dpCost *int
					if v, err := strconv.Atoi(fmt.Sprintf("%v", c["dummy_partner_cost"])); err == nil {
						dpCost = &v
					}
					var dpCiv *string
					if v := strings.TrimSpace(fmt.Sprintf("%v", c["dummy_partner_civ_ids"])); v != "" && v != "<nil>" {
						dpCiv = &v
					}

					tx.Exec(`INSERT INTO deck_template_cards 
                        (template_id, card_id, dummy_name, dummy_cost, dummy_civ_ids, dummy_card_type, dummy_partner_name, dummy_partner_cost, dummy_partner_civ_ids, quantity, card_type_in_deck, sort_order) 
                        VALUES (?, NULL, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?)`,
						templateId, dName, dCost, dCiv, dType, dpName, dpCost, dpCiv, cType, order)
					order++
				} else {
					cid, _ := strconv.Atoi(fmt.Sprintf("%v", c["id"]))
					if cid > 0 {
						tx.Exec(`INSERT INTO deck_template_cards 
                            (template_id, card_id, quantity, card_type_in_deck, sort_order) 
                            VALUES (?, ?, 1, ?, ?)`,
							templateId, cid, cType, order)
						order++
					}
				}
			}
		}
	}

	tx.Commit()
	json.NewEncoder(w).Encode(map[string]interface{}{"success": true})
}

func (c *DeveloperController) DeleteTemplateApi(w http.ResponseWriter, r *http.Request) {
	if _, ok := c.checkAuth(w, r); !ok {
		return
	}
	w.Header().Set("Content-Type", "application/json; charset=utf-8")

	var in map[string]interface{}
	json.NewDecoder(r.Body).Decode(&in)

	id, _ := strconv.Atoi(fmt.Sprintf("%v", in["template_id"]))
	if id == 0 {
		json.NewEncoder(w).Encode(map[string]interface{}{"success": false, "error": "IDが不正です。"})
		return
	}

	db := getDB()
	db.Exec("DELETE FROM deck_templates WHERE template_id = ?", id)
	json.NewEncoder(w).Encode(map[string]interface{}{"success": true})
}

// テンプレート内のカード構成一覧を取得するAPI (デッキ作成画面・編集画面で使用)
func (c *DeveloperController) TemplateCardsApi(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json; charset=utf-8")
	templateIdStr := r.URL.Query().Get("template_id")
	templateId, _ := strconv.Atoi(templateIdStr)
	if templateId == 0 {
		w.WriteHeader(http.StatusBadRequest)
		json.NewEncoder(w).Encode(map[string]string{"error": "Template ID is required"})
		return
	}

	db := getDB()
	var tpl map[string]interface{}
	tRows, err := db.Query("SELECT * FROM deck_templates WHERE template_id = ?", templateId)
	rowsD, err := db.Query("SHOW COLUMNS FROM deck_template_cards LIKE 'dummy_name'")
	if err == nil {
		hasDummy := rowsD.Next()
		rowsD.Close()
		if !hasDummy {
			db.Exec("ALTER TABLE deck_template_cards MODIFY card_id INT NULL;")
			db.Exec(`ALTER TABLE deck_template_cards 
                ADD COLUMN dummy_name VARCHAR(255) NULL AFTER card_id,
                ADD COLUMN dummy_cost INT NULL AFTER dummy_name,
                ADD COLUMN dummy_civ_ids VARCHAR(50) NULL AFTER dummy_cost,
                ADD COLUMN dummy_card_type VARCHAR(20) DEFAULT 'normal' AFTER dummy_civ_ids,
                ADD COLUMN dummy_partner_name VARCHAR(255) NULL AFTER dummy_card_type,
                ADD COLUMN dummy_partner_cost INT NULL AFTER dummy_partner_name,
                ADD COLUMN dummy_partner_civ_ids VARCHAR(50) NULL AFTER dummy_partner_cost;`)
		}
	}

	if tpl == nil {
		w.WriteHeader(http.StatusNotFound)
		json.NewEncoder(w).Encode(map[string]string{"error": "Template not found"})
		return
	}

	cRows, err := db.Query(`
		SELECT 
			dtc.card_type_in_deck,
			dtc.sort_order,
			c.card_id,
			c.card_name,
			c.reading,
			c.cost,
			c.pow,
			COALESCE(
				cd.imagepath,
				(SELECT cd2.imagepath FROM card_detail cd2 WHERE cd2.card_id = c.card_id AND cd2.imagepath IS NOT NULL AND cd2.imagepath != '' LIMIT 1),
				(SELECT cd3.imagepath FROM card_combination cc1 JOIN card_combination cc2 ON cc1.combination_id = cc2.combination_id JOIN card_detail cd3 ON cc2.card_id = cd3.card_id WHERE cc1.card_id = c.card_id AND cd3.imagepath IS NOT NULL AND cd3.imagepath != '' LIMIT 1),
				''
			) as imagepath,
			COALESCE(cd.`+"`limit`"+`, 4) as card_limit,
			COALESCE(cd.twinpact, 0) as twinpact,
			(SELECT GROUP_CONCAT(civilization_id) FROM card_civilization WHERE card_id = c.card_id) as civ_ids,
			(SELECT GROUP_CONCAT(characteristics_id) FROM card_characteristics WHERE card_id = c.card_id) as char_ids,
			(SELECT GROUP_CONCAT(c_all.card_name ORDER BY cc_all.card_id ASC SEPARATOR '|||') 
			 FROM card_combination cc_ref 
			 JOIN card_combination cc_all ON cc_ref.combination_id = cc_all.combination_id 
			 JOIN card c_all ON cc_all.card_id = c_all.card_id 
			 WHERE cc_ref.card_id = c.card_id) as combo_names
		FROM deck_template_cards dtc
		JOIN card c ON dtc.card_id = c.card_id
		LEFT JOIN card_detail cd ON c.card_id = cd.card_id AND cd.is_primary_version = 1
		WHERE dtc.template_id = ?
		ORDER BY dtc.sort_order ASC
	`, templateId)

	cards, _ := rowsToMapSlice(cRows)

	json.NewEncoder(w).Encode(map[string]interface{}{
		"template": tpl,
		"cards":    cards,
	})
}