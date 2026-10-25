package controllers

import (
	"crypto/md5"
	"database/sql"
	models "deck-maker/app/Models"
	views "deck-maker/app/Views"
	"encoding/json"
	"fmt"
	"html/template"
	"io"
	"log"
	"math"
	"net/http"
	"net/url"
	"os"
	"regexp"
	"sort"
	"strconv"
	"strings"
	"time"

	"golang.org/x/text/width"
)

// DB接続取得ヘルパー（エラーハンドリング統一）
func getDB() *sql.DB {
	db, err := models.Connect()
	if err != nil {
		panic(err)
	}
	return db
}

type DeckController struct {
	db *sql.DB
}

func NewDeckController(db *sql.DB) *AuthController {
	return &AuthController{
		db: db,
	}
}

// セッションからuser_idを取得するヘルパー（実装環境のセッション機構に合わせて調整可能）
func getSessionUserID(r *http.Request) (int, bool) {
	uidVal := views.GetSessionUserID(r)
	if uidVal == nil {
		return 0, false
	}
	switch v := uidVal.(type) {
	case int:
		if v > 0 {
			return v, true
		}
	case int64:
		if v > 0 {
			return int(v), true
		}
	case float64:
		if v > 0 {
			return int(v), true
		}
	// ★ 追加: Cookie由来の string 型を数値に変換して受け付ける
	case string:
		if id, err := strconv.Atoi(v); err == nil && id > 0 {
			return id, true
		}
	}
	return 0, false
}

func (c *DeckController) MyDecks(w http.ResponseWriter, r *http.Request) {
	var myDecks []map[string]interface{}
	totalDecks := 0
	perPage := 15
	currentPage := 1

	if pageStr := r.URL.Query().Get("page"); pageStr != "" {
		if p, err := strconv.Atoi(pageStr); err == nil && p > 1 {
			currentPage = p
		}
	}

	totalPages := 1
	userID, ok := getSessionUserID(r)
	if ok {
		db := getDB()
		deckModel := models.NewDeck(db) // package名を Models に統一
		allDecks, _ := deckModel.GetByUserId(userID)

		// ★ 追加: 各デッキの文明（光・水・闇・火・自然）の有無を判定してマップにセット
		for _, d := range allDecks {
			did, _ := strconv.Atoi(fmt.Sprintf("%v", d["deck_id"]))

			// 公開フラグを数値(1/0)・真偽値(true/false)の両方に確実にセット
			pubStr := fmt.Sprintf("%v", d["is_public"])
			isPub := (pubStr == "1" || pubStr == "true")
			if isPub {
				d["is_public"] = 1
			} else {
				d["is_public"] = 0
			}
			d["IsPublic"] = isPub

			var civList string
			_ = db.QueryRow(`
				SELECT GROUP_CONCAT(DISTINCT cc.civilization_id)
				FROM deck_cards dc
				LEFT JOIN card_civilization cc ON dc.card_id = cc.card_id
				WHERE dc.deck_id = ? AND (dc.card_type_in_deck IS NULL OR dc.card_type_in_deck = 'main')
			`, did).Scan(&civList)

			d["civ_light"] = strings.Contains(civList, "1")
			d["civ_water"] = strings.Contains(civList, "2")
			d["civ_dark"] = strings.Contains(civList, "3")
			d["civ_fire"] = strings.Contains(civList, "4")
			d["civ_nature"] = strings.Contains(civList, "5")

			// ★ 追加: 日付を YYYY/MM/DD にフォーマット
			rawDate := fmt.Sprintf("%v", d["updated_at"])
			if len(rawDate) >= 10 {
				d["formatted_at"] = strings.ReplaceAll(rawDate[:10], "-", "/")
			}
		}
		totalDecks = len(allDecks)
		totalPages = int(math.Max(1, math.Ceil(float64(totalDecks)/float64(perPage))))
		if currentPage > totalPages {
			currentPage = totalPages
		}

		offset := (currentPage - 1) * perPage
		if offset < totalDecks {
			end := offset + perPage
			if end > totalDecks {
				end = totalDecks
			}
			myDecks = allDecks[offset:end]
		}
	}

	// ページネーション用変数の計算
	startPage := currentPage - 2
	if startPage < 1 {
		startPage = 1
	}
	endPage := currentPage + 2
	if endPage > totalPages {
		endPage = totalPages
	}

	var pageList []int
	for p := startPage; p <= endPage; p++ {
		pageList = append(pageList, p)
	}

	views.RenderView(w, r, "deck/index.html", map[string]interface{}{
		"Decks":       myDecks,
		"CurrentPage": currentPage,
		"TotalPages":  totalPages,
		"TotalDecks":  totalDecks,
		"StartPage":   startPage,
		"EndPage":     endPage,
		"PageList":    pageList,
		"PrevPage":    currentPage - 1,
		"NextPage":    currentPage + 1,
		"IsLoggedIn":  ok,
		"UserID":      userID,
	})
}

/**
 * 公開デッキ検索画面
 */
func (c *DeckController) Search(w http.ResponseWriter, r *http.Request) {
	db := getDB()
	query := r.URL.Query()

	deckName := strings.TrimSpace(query.Get("deck_name"))
	formatId := query.Get("format_id")
	cardName := strings.TrimSpace(query.Get("card_name"))
	civIds := query["civ_ids"]
	civLogic := query.Get("civ_logic")
	if civLogic == "" {
		civLogic = "include"
	}

	sqlStr := `SELECT d.*, f.format_name, u.username as creator_name, cd.imagepath as thumbnail_imagepath
               FROM decks d
               JOIN formats f ON d.format_id = f.format_id
               JOIN users u ON d.user_id = u.user_id
               LEFT JOIN card_detail cd ON d.thumbnail_card_id = cd.card_id
               WHERE d.is_public = 1`

	var params []interface{}

	if deckName != "" {
		sqlStr += " AND d.deck_name LIKE ?"
		params = append(params, "%"+deckName+"%")
	}
	if formatId != "" {
		fid, _ := strconv.Atoi(formatId)
		sqlStr += " AND d.format_id = ?"
		params = append(params, fid)
	}
	if cardName != "" {
		rawNames := strings.Split(cardName, ",")
		for _, name := range rawNames {
			trimmed := strings.TrimSpace(name)
			if trimmed != "" {
				sqlStr += ` AND EXISTS (
                    SELECT 1 FROM deck_cards dc 
                    JOIN card c ON dc.card_id = c.card_id 
                    WHERE dc.deck_id = d.deck_id AND c.card_name LIKE ?
                )`
				params = append(params, "%"+trimmed+"%")
			}
		}
	}

	if len(civIds) > 0 {
		if civLogic == "only" {
			allCivs := []int{1, 2, 3, 4, 5, 6}
			var civIdsInt []int
			for _, cid := range civIds {
				if v, err := strconv.Atoi(cid); err == nil {
					civIdsInt = append(civIdsInt, v)
				}
			}
			var excludedCivs []string
			for _, ac := range allCivs {
				found := false
				for _, ic := range civIdsInt {
					if ac == ic {
						found = true
						break
					}
				}
				if !found {
					excludedCivs = append(excludedCivs, strconv.Itoa(ac))
				}
			}

			if len(excludedCivs) > 0 {
				excludedList := strings.Join(excludedCivs, ",")
				sqlStr += fmt.Sprintf(` AND NOT EXISTS (
                    SELECT 1 FROM deck_cards dc 
                    JOIN card_civilization cc ON dc.card_id = cc.card_id 
                    WHERE dc.deck_id = d.deck_id AND cc.civilization_id IN (%s)
                )`, excludedList)
			}
		} else {
			for _, id := range civIds {
				cid, _ := strconv.Atoi(id)
				sqlStr += ` AND EXISTS (
                    SELECT 1 FROM deck_cards dc 
                    JOIN card_civilization cc ON dc.card_id = cc.card_id 
                    WHERE dc.deck_id = d.deck_id AND cc.civilization_id = ?
                )`
				params = append(params, cid)
			}
		}
	}

	sqlStr += " ORDER BY d.updated_at DESC"

	rows, err := db.Query(sqlStr, params...)
	if err != nil {
		http.Error(w, err.Error(), 500)
		return
	}
	defer rows.Close()

	var allDecks []map[string]interface{}
	cols, _ := rows.Columns()
	for rows.Next() {
		columns := make([]interface{}, len(cols))
		columnPointers := make([]interface{}, len(cols))
		for i := range columns {
			columnPointers[i] = &columns[i]
		}
		rows.Scan(columnPointers...)
		rowMap := make(map[string]interface{})
		for i, colName := range cols {
			val := columnPointers[i].(*interface{})
			if b, ok := (*val).([]byte); ok {
				rowMap[colName] = string(b)
			} else {
				rowMap[colName] = *val
			}
		}
		allDecks = append(allDecks, rowMap)
	}

	perPage := 15
	totalDecks := len(allDecks)
	currentPage := 1
	if p, err := strconv.Atoi(query.Get("page")); err == nil && p > 1 {
		currentPage = p
	}
	totalPages := int(math.Max(1, math.Ceil(float64(totalDecks)/float64(perPage))))
	if currentPage > totalPages {
		currentPage = totalPages
	}

	offset := (currentPage - 1) * perPage
	var decks []map[string]interface{}
	if offset < totalDecks {
		end := offset + perPage
		if end > totalDecks {
			end = totalDecks
		}
		decks = allDecks[offset:end]
	}

	// ★ deck_item.html での参照（直接/階層/大文字/小文字）のすべてに対応できるよう補完
	for _, d := range decks {
		did, _ := strconv.Atoi(fmt.Sprintf("%v", d["deck_id"]))

		var civList string
		_ = db.QueryRow(`
			SELECT GROUP_CONCAT(DISTINCT cc.civilization_id)
			FROM deck_cards dc
			LEFT JOIN card_civilization cc ON dc.card_id = cc.card_id
			WHERE dc.deck_id = ? AND (dc.card_type_in_deck IS NULL OR dc.card_type_in_deck = 'main')
		`, did).Scan(&civList)

		d["civ_light"] = strings.Contains(civList, "1")
		d["civ_water"] = strings.Contains(civList, "2")
		d["civ_dark"] = strings.Contains(civList, "3")
		d["civ_fire"] = strings.Contains(civList, "4")
		d["civ_nature"] = strings.Contains(civList, "5")

		rawDate := fmt.Sprintf("%v", d["updated_at"])
		if len(rawDate) >= 10 {
			d["formatted_at"] = strings.ReplaceAll(rawDate[:10], "-", "/")
		}

		// サムネイル画像のパス補完
		thumb := fmt.Sprintf("%v", d["thumbnail_imagepath"])
		if thumb == "" || thumb == "<nil>" {
			thumb = "/images/card/noimage.webp"
		} else if !strings.HasPrefix(thumb, "http") && !strings.HasPrefix(thumb, "/") {
			thumb = "/images/card/" + thumb
		}

		// フィールドの大文字・小文字両対応
		d["DeckID"] = did
		d["deck_id"] = did
		d["DeckName"] = d["deck_name"]
		d["ThumbnailImagePath"] = thumb
		d["thumbnail_imagepath"] = thumb
		d["CreatorName"] = d["creator_name"]
		d["FormatName"] = d["format_name"]
		d["IsPublic"] = true
		d["is_public"] = 1

		// 表示モード設定（検索画面用）
		d["context"] = "search"
		d["Context"] = "search"

		// 階層参照（.deck.xxx または .Deck.xxx）のどちらでもアクセスできるように自己参照を登録
		d["deck"] = d
		d["Deck"] = d
	}

	fRows, _ := db.Query("SELECT format_id, format_name FROM formats ORDER BY format_id ASC")
	defer fRows.Close()
	var formats []map[string]interface{}
	for fRows.Next() {
		var id int
		var name string
		fRows.Scan(&id, &name)
		formats = append(formats, map[string]interface{}{"format_id": id, "format_name": name})
	}

	civilizations := []map[string]interface{}{
		{"civilization_id": 1, "civilization_name": "光"},
		{"civilization_id": 2, "civilization_name": "水"},
		{"civilization_id": 3, "civilization_name": "闇"},
		{"civilization_id": 4, "civilization_name": "火"},
		{"civilization_id": 5, "civilization_name": "自然"},
		{"civilization_id": 6, "civilization_name": "ゼロ"},
	}

	// ログイン状態を取得
	userID, ok := getSessionUserID(r)

	views.RenderView(w, r, "deck/search.html", map[string]interface{}{
		"decks":         decks,
		"Decks":         decks,
		"formats":       formats,
		"civilizations": civilizations,
		"currentPage":   currentPage,
		"totalPages":    totalPages,
		"totalDecks":    totalDecks,
		"IsLoggedIn":    ok,
		"UserID":        userID,
		"searchValues": map[string]interface{}{
			"deck_name": deckName,
			"format_id": formatId,
			"card_name": cardName,
			"civ_ids":   civIds,
			"civ_logic": civLogic,
		},
	})
}

/**
 * 公開デッキをマイデッキとして丸ごと複製保存するAPI
 */
func (c *DeckController) CopyDeckApi(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	var input map[string]interface{}
	json.NewDecoder(r.Body).Decode(&input)

	userID, ok := getSessionUserID(r)
	if !ok {
		w.WriteHeader(http.StatusUnauthorized)
		json.NewEncoder(w).Encode(map[string]interface{}{"success": false, "error": "ログインが必要です。"})
		return
	}

	sourceDeckId := input["deck_id"]
	if sourceDeckId == nil {
		json.NewEncoder(w).Encode(map[string]interface{}{"success": false, "error": "コピー元デッキIDが指定されていません。"})
		return
	}

	db := getDB()
	tx, err := db.Begin()
	if err != nil {
		w.WriteHeader(500)
		json.NewEncoder(w).Encode(map[string]interface{}{"success": false, "error": err.Error()})
		return
	}
	defer tx.Rollback()

	var deckName string
	var formatId int
	var thumbnailCardId sql.NullInt64
	err = tx.QueryRow("SELECT deck_name, format_id, thumbnail_card_id FROM decks WHERE deck_id = ?", sourceDeckId).Scan(&deckName, &formatId, &thumbnailCardId)
	if err != nil {
		json.NewEncoder(w).Encode(map[string]interface{}{"success": false, "error": "コピー元のデッキが見つかりません。"})
		return
	}

	newDeckName := deckName + " のコピー"
	res, err := tx.Exec("INSERT INTO decks (user_id, deck_name, format_id, thumbnail_card_id, is_public, created_at, updated_at) VALUES (?, ?, ?, ?, 0, NOW(), NOW())",
		userID, newDeckName, formatId, thumbnailCardId)
	if err != nil {
		json.NewEncoder(w).Encode(map[string]interface{}{"success": false, "error": err.Error()})
		return
	}
	newDeckId, _ := res.LastInsertId()

	rows, err := tx.Query("SELECT card_id, quantity, card_type_in_deck, sort_order FROM deck_cards WHERE deck_id = ? ORDER BY sort_order ASC", sourceDeckId)
	if err != nil {
		json.NewEncoder(w).Encode(map[string]interface{}{"success": false, "error": err.Error()})
		return
	}
	defer rows.Close()

	type CardRow struct {
		CardID         sql.NullInt64
		Quantity       int
		CardTypeInDeck string
		SortOrder      int
	}
	var cards []CardRow
	for rows.Next() {
		var cr CardRow
		rows.Scan(&cr.CardID, &cr.Quantity, &cr.CardTypeInDeck, &cr.SortOrder)
		cards = append(cards, cr)
	}

	for _, cr := range cards {
		_, err = tx.Exec("INSERT INTO deck_cards (deck_id, card_id, quantity, card_type_in_deck, sort_order) VALUES (?, ?, ?, ?, ?)",
			newDeckId, cr.CardID, cr.Quantity, cr.CardTypeInDeck, cr.SortOrder)
		if err != nil {
			json.NewEncoder(w).Encode(map[string]interface{}{"success": false, "error": err.Error()})
			return
		}
	}

	tx.Commit()
	json.NewEncoder(w).Encode(map[string]interface{}{"success": true})
}

func (c *DeckController) Create(w http.ResponseWriter, r *http.Request) {
	db := getDB()
	stmt, _ := db.Query("SELECT format_id, format_name FROM formats ORDER BY format_id ASC")
	defer stmt.Close()

	var formats []map[string]interface{}
	for stmt.Next() {
		var id int
		var name string
		stmt.Scan(&id, &name)
		formats = append(formats, map[string]interface{}{"format_id": id, "format_name": name})
	}

	formatsJSON, _ := json.Marshal(formats)

	views.RenderView(w, r, "deck/create.html", map[string]interface{}{
		"hideFooter":          true,
		"formats":             formats,
		"formatsJSON":         template.JS(formatsJSON),
		"isEditJSON":          template.JS("false"),
		"deckIDJSON":          template.JS("null"),
		"deckConfigJSON":      template.JS("{}"),
		"initialDeckNameJSON": template.JS(`"マイデッキ"`),
		"initialCardsJSON":    template.JS("[]"),
	})
}

func (c *DeckController) StoreDeckApi(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")

	// ★ panic を捕捉して原因をターミナルとレスポンスに明示
	defer func() {
		if rec := recover(); rec != nil {
			log.Printf("[StoreDeckApi PANIC]: %v", rec)
			w.WriteHeader(http.StatusInternalServerError)
			json.NewEncoder(w).Encode(map[string]interface{}{
				"success": false,
				"error":   fmt.Sprintf("サーバー内部エラー: %v", rec),
			})
		}
	}()

	var input map[string]interface{}
	if err := json.NewDecoder(r.Body).Decode(&input); err != nil {
		w.WriteHeader(http.StatusBadRequest)
		json.NewEncoder(w).Encode(map[string]interface{}{"success": false, "error": "リクエスト形式が不正です"})
		return
	}

	userID, ok := getSessionUserID(r)
	if !ok {
		w.WriteHeader(http.StatusUnauthorized)
		json.NewEncoder(w).Encode(map[string]interface{}{"success": false, "error": "ログインが必要です"})
		return
	}

	db := getDB()

	// ★ DDL(ALTER TABLE) はトランザクション開始前に実行する（暗黙コミット防止）
	c.ensureDummyColumnsExist(db)

	isPublic := 0
	if p, ok := input["is_public"]; ok {
		switch v := p.(type) {
		case bool:
			if v {
				isPublic = 1
			}
		case float64:
			isPublic = int(v)
		}
	}

	if isPublic == 1 {
		var banUntil sql.NullString
		err := db.QueryRow("SELECT public_ban_until FROM users WHERE user_id = ?", userID).Scan(&banUntil)
		if err == nil && banUntil.Valid && banUntil.String != "" {
			t, errParse := time.Parse("2006-01-02 15:04:05", banUntil.String)
			if errParse == nil && t.After(time.Now()) {
				w.WriteHeader(http.StatusForbidden)
				json.NewEncoder(w).Encode(map[string]interface{}{
					"success": false,
					"error":   "現在デッキの公開が制限されています（期限: " + banUntil.String + " まで）。非公開で保存してください。",
				})
				return
			}
		}
	}

	tx, err := db.Begin()
	if err != nil {
		log.Printf("[StoreDeckApi Begin Error]: %v", err)
		w.WriteHeader(http.StatusInternalServerError)
		json.NewEncoder(w).Encode(map[string]interface{}{"success": false, "error": err.Error()})
		return
	}
	defer tx.Rollback()

	var tcid interface{} = nil
	if val, ok := input["thumbnail_card_id"]; ok && val != nil && val != "" {
		if tid, err := strconv.Atoi(fmt.Sprintf("%v", val)); err == nil && tid > 0 {
			tcid = tid
		}
	}

	// ★ created_at, updated_at を追加
	res, err := tx.Exec("INSERT INTO decks (user_id, deck_name, format_id, thumbnail_card_id, is_public, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())",
		userID, input["deck_name"], input["format_id"], tcid, isPublic)
	if err != nil {
		log.Printf("[StoreDeckApi INSERT deck Error]: %v", err)
		w.WriteHeader(http.StatusInternalServerError)
		json.NewEncoder(w).Encode(map[string]interface{}{"success": false, "error": err.Error()})
		return
	}
	deckId, _ := res.LastInsertId()

	cards, _ := input["cards"].([]interface{})
	replacedMap := c.saveDeckCards(tx, deckId, cards)

	if tcid == nil && len(replacedMap) > 0 {
		var newThumbId int
		for _, v := range replacedMap {
			newThumbId = v
			break
		}
		tx.Exec("UPDATE decks SET thumbnail_card_id = ? WHERE deck_id = ?", newThumbId, deckId)
	}

	if err := tx.Commit(); err != nil {
		log.Printf("[StoreDeckApi Commit Error]: %v", err)
		w.WriteHeader(http.StatusInternalServerError)
		json.NewEncoder(w).Encode(map[string]interface{}{"success": false, "error": err.Error()})
		return
	}

	json.NewEncoder(w).Encode(map[string]interface{}{"success": true})
}

func (c *DeckController) ViewDeckApi(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	deckIdStr := r.URL.Query().Get("deck_id")
	if deckIdStr == "" {
		json.NewEncoder(w).Encode(map[string]interface{}{"error": "Deck ID missing"})
		return
	}
	deckId, _ := strconv.Atoi(deckIdStr)

	db := getDB()
	deckModel := models.NewDeck(db)
	cards, _ := deckModel.GetCardsByDeckId(deckId)

	json.NewEncoder(w).Encode(cards)
}

func (c *DeckController) DeleteDeckApi(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	deckIdStr := r.URL.Query().Get("deck_id")
	userID, ok := getSessionUserID(r)

	if deckIdStr == "" || !ok {
		json.NewEncoder(w).Encode(map[string]interface{}{"success": false, "error": "権限がありません"})
		return
	}

	deckId, _ := strconv.Atoi(deckIdStr)
	db := getDB()
	res, err := db.Exec("DELETE FROM decks WHERE deck_id = ? AND user_id = ?", deckId, userID)
	if err != nil {
		json.NewEncoder(w).Encode(map[string]interface{}{"success": false, "error": err.Error()})
		return
	}
	ra, _ := res.RowsAffected()
	json.NewEncoder(w).Encode(map[string]interface{}{"success": ra > 0})
}

func (c *DeckController) Edit(w http.ResponseWriter, r *http.Request) {
	deckIdStr := r.URL.Query().Get("deck_id")
	userID, ok := getSessionUserID(r)
	if deckIdStr == "" || !ok {
		http.Redirect(w, r, "/mydecks", http.StatusFound)
		return
	}
	deckId, _ := strconv.Atoi(deckIdStr)

	db := getDB()
	deckModel := models.NewDeck(db)
	deck, _ := deckModel.GetByIdAndUser(deckId, userID)
	if deck == nil {
		http.Redirect(w, r, "/mydecks", http.StatusFound)
		return
	}
	cards, _ := deckModel.GetCardsByDeckId(deckId)

	stmt, _ := db.Query("SELECT format_id, format_name FROM formats ORDER BY format_id ASC")
	defer stmt.Close()
	var formats []map[string]interface{}
	for stmt.Next() {
		var id int
		var name string
		stmt.Scan(&id, &name)
		formats = append(formats, map[string]interface{}{"format_id": id, "format_name": name})
	}

	formatsJSON, _ := json.Marshal(formats)
	initialCardsJSON, _ := json.Marshal(cards)
	deckConfigJSON, _ := json.Marshal(deck)
	deckNameJSON, _ := json.Marshal(deck["deck_name"])
	deckIDJSON := fmt.Sprintf("%v", deckId)

	views.RenderView(w, r, "deck/create.html", map[string]interface{}{
		"hideFooter":          true,
		"isEdit":              true,
		"deck":                deck,
		"initialCards":        cards,
		"formats":             formats,
		"formatsJSON":         template.JS(formatsJSON),
		"isEditJSON":          template.JS("true"),
		"deckIDJSON":          template.JS(deckIDJSON),
		"deckConfigJSON":      template.JS(deckConfigJSON),
		"initialDeckNameJSON": template.JS(deckNameJSON),
		"initialCardsJSON":    template.JS(initialCardsJSON),
	})
}

func (c *DeckController) UpdateDeckApi(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	var input map[string]interface{}
	json.NewDecoder(r.Body).Decode(&input)

	userID, ok := getSessionUserID(r)
	if !ok {
		json.NewEncoder(w).Encode(map[string]interface{}{"success": false, "error": "権限不足"})
		return
	}

	db := getDB()

	isPublic := 0
	if p, ok := input["is_public"]; ok {
		switch v := p.(type) {
		case bool:
			if v {
				isPublic = 1
			}
		case float64:
			isPublic = int(v)
		}
	}

	if isPublic == 1 {
		var banUntil sql.NullString
		err := db.QueryRow("SELECT public_ban_until FROM users WHERE user_id = ?", userID).Scan(&banUntil)
		if err == nil && banUntil.Valid && banUntil.String != "" {
			t, errParse := time.Parse("2006-01-02 15:04:05", banUntil.String)
			if errParse == nil && t.After(time.Now()) {
				w.WriteHeader(http.StatusForbidden)
				json.NewEncoder(w).Encode(map[string]interface{}{
					"success": false,
					"error":   "現在デッキの公開が制限されています（期限: " + banUntil.String + " まで）。非公開で保存してください。",
				})
				return
			}
		}
	}

	tx, err := db.Begin()
	if err != nil {
		json.NewEncoder(w).Encode(map[string]interface{}{"success": false, "error": err.Error()})
		return
	}
	defer tx.Rollback()

	c.ensureDummyColumnsExist(db)

	var tcid interface{} = nil
	if val, ok := input["thumbnail_card_id"]; ok && val != nil && val != "" {
		tcid = val
	}

	deckId := int64(input["deck_id"].(float64))

	_, err = tx.Exec("UPDATE decks SET deck_name = ?, format_id = ?, thumbnail_card_id = ?, is_public = ?, updated_at = NOW() WHERE deck_id = ? AND user_id = ?",
		input["deck_name"], input["format_id"], tcid, isPublic, deckId, userID)
	if err != nil {
		json.NewEncoder(w).Encode(map[string]interface{}{"success": false, "error": err.Error()})
		return
	}

	tx.Exec("DELETE FROM deck_cards WHERE deck_id = ?", deckId)
	cards, _ := input["cards"].([]interface{})
	replacedMap := c.saveDeckCards(tx, deckId, cards)

	if tcid == nil && len(replacedMap) > 0 {
		var newThumbId int
		for _, v := range replacedMap {
			newThumbId = v
			break
		}
		tx.Exec("UPDATE decks SET thumbnail_card_id = ? WHERE deck_id = ?", newThumbId, deckId)
	}

	tx.Commit()
	json.NewEncoder(w).Encode(map[string]interface{}{"success": true})
}

var checkedDummyColumns = false

func (c *DeckController) ensureDummyColumnsExist(db *sql.DB) {
	if checkedDummyColumns {
		return
	}
	defer func() { checkedDummyColumns = true }()

	rows, err := db.Query("SHOW COLUMNS FROM deck_cards LIKE 'dummy_name'")
	if err == nil {
		hasCol := rows.Next()
		rows.Close()
		if !hasCol {
			db.Exec("ALTER TABLE deck_cards MODIFY card_id INT NULL;")
			db.Exec(`ALTER TABLE deck_cards 
                ADD COLUMN dummy_name VARCHAR(255) NULL AFTER card_id,
                ADD COLUMN dummy_cost INT NULL AFTER dummy_name,
                ADD COLUMN dummy_civ_ids VARCHAR(50) NULL AFTER dummy_cost;`)
		}
	}

	rowsPart, err := db.Query("SHOW COLUMNS FROM deck_cards LIKE 'dummy_partner_name'")
	if err == nil {
		hasPart := rowsPart.Next()
		rowsPart.Close()
		if !hasPart {
			db.Exec(`ALTER TABLE deck_cards 
                ADD COLUMN dummy_card_type VARCHAR(20) DEFAULT 'normal' AFTER dummy_civ_ids,
                ADD COLUMN dummy_partner_name VARCHAR(255) NULL AFTER dummy_card_type,
                ADD COLUMN dummy_partner_cost INT NULL AFTER dummy_partner_name,
                ADD COLUMN dummy_partner_civ_ids VARCHAR(50) NULL AFTER dummy_partner_cost;`)
		}
	}
}

func (c *DeckController) saveDeckCards(tx *sql.Tx, deckId int64, cards []interface{}) map[string]int {
	replacedDummyMap := make(map[string]int)

	sqlNorm := func(col string) string {
		return fmt.Sprintf("LOWER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(%s, ' ', ''), '　', ''), '・', ''), '～', ''), '〜', ''), 'ー', ''))", col)
	}

	normalizeName := func(str string) string {
		if str == "" {
			return ""
		}
		s := width.Fold.String(str)
		s = strings.ToLower(s)
		reg := regexp.MustCompile(`[\s・−―ー〜～~//「」『』【】[\]"'”"'♪!！？?==::**++&&-]`)
		return reg.ReplaceAllString(s, "")
	}

	order := 1
	for _, cardItem := range cards {
		cMap, ok := cardItem.(map[string]interface{})
		if !ok {
			continue
		}

		var cardId *int = nil
		if idVal, exists := cMap["id"]; exists && idVal != nil {
			if idInt, err := strconv.Atoi(fmt.Sprintf("%v", idVal)); err == nil && idInt > 0 {
				cardId = &idInt
			}
		}

		var dummyName *string = nil
		var dummyCost *int = nil
		var dummyCivIds *string = nil
		dummyCardType := "normal"
		var dummyPartnerName *string = nil
		var dummyPartnerCost *int = nil
		var dummyPartnerCivIds *string = nil

		isDummy := false
		if idm, ok := cMap["is_dummy"].(bool); ok && idm {
			isDummy = true
		}
		if dn, ok := cMap["dummy_name"].(string); ok && dn != "" {
			isDummy = true
		}

		if isDummy {
			dName := ""
			if dn, ok := cMap["dummy_name"].(string); ok && dn != "" {
				dName = strings.TrimSpace(dn)
			} else if cn, ok := cMap["card_name"].(string); ok {
				dName = strings.TrimSpace(cn)
			}

			if dct, ok := cMap["dummy_card_type"].(string); ok && dct != "" {
				dummyCardType = dct
			}
			if dpn, ok := cMap["dummy_partner_name"].(string); ok && dpn != "" {
				v := strings.TrimSpace(dpn)
				dummyPartnerName = &v
			}
			if cVal, exists := cMap["cost"]; exists && cVal != nil {
				if costInt, err := strconv.Atoi(fmt.Sprintf("%v", cVal)); err == nil {
					dummyCost = &costInt
				}
			} else if dcVal, exists := cMap["dummy_cost"]; exists && dcVal != nil {
				if costInt, err := strconv.Atoi(fmt.Sprintf("%v", dcVal)); err == nil {
					dummyCost = &costInt
				}
			}

			if civs, exists := cMap["civ_ids"]; exists && civs != nil {
				switch cv := civs.(type) {
				case []interface{}:
					var strs []string
					for _, item := range cv {
						strs = append(strs, fmt.Sprintf("%v", item))
					}
					joined := strings.Join(strs, ",")
					dummyCivIds = &joined
				default:
					s := fmt.Sprintf("%v", civs)
					dummyCivIds = &s
				}
			} else if dciv, exists := cMap["dummy_civ_ids"]; exists && dciv != nil {
				s := fmt.Sprintf("%v", dciv)
				dummyCivIds = &s
			}

			if dpcVal, exists := cMap["dummy_partner_cost"]; exists && dpcVal != nil {
				if costInt, err := strconv.Atoi(fmt.Sprintf("%v", dpcVal)); err == nil {
					dummyPartnerCost = &costInt
				}
			}
			if dpciv, exists := cMap["dummy_partner_civ_ids"]; exists && dpciv != nil {
				switch cv := dpciv.(type) {
				case []interface{}:
					var strs []string
					for _, item := range cv {
						strs = append(strs, fmt.Sprintf("%v", item))
					}
					joined := strings.Join(strs, ",")
					dummyPartnerCivIds = &joined
				default:
					s := fmt.Sprintf("%v", dpciv)
					dummyPartnerCivIds = &s
				}
			}

			normDName := normalizeName(dName)
			normDPName := ""
			if dummyPartnerName != nil {
				normDPName = normalizeName(*dummyPartnerName)
			}

			var realCardID int = 0
			if dummyCardType == "twinpact" && normDPName != "" {
				q := fmt.Sprintf(`
                    SELECT c_main.card_id 
                    FROM card c_main
                    JOIN card_combination ccb_main ON c_main.card_id = ccb_main.card_id AND ccb_main.is_main_side = 1
                    JOIN card_combination ccb_partner ON ccb_main.combination_id = ccb_partner.combination_id AND ccb_main.card_id <> ccb_partner.card_id
                    JOIN card c_partner ON ccb_partner.card_id = c_partner.card_id
                    JOIN card_detail cd ON c_main.card_id = cd.card_id
                    WHERE %s = ? 
                      AND %s = ?
                      AND cd.twinpact = 1
                    LIMIT 1`, sqlNorm("c_main.card_name"), sqlNorm("c_partner.card_name"))
				tx.QueryRow(q, normDName, normDPName).Scan(&realCardID)
			} else if dummyCardType == "double" && normDPName != "" {
				q := fmt.Sprintf(`
                    SELECT c_main.card_id 
                    FROM card c_main
                    JOIN card_combination ccb_main ON c_main.card_id = ccb_main.card_id AND ccb_main.is_main_side = 1
                    JOIN card_combination ccb_partner ON ccb_main.combination_id = ccb_partner.combination_id AND ccb_main.card_id <> ccb_partner.card_id
                    JOIN card c_partner ON ccb_partner.card_id = c_partner.card_id
                    JOIN card_detail cd ON c_main.card_id = cd.card_id
                    WHERE %s = ? 
                      AND %s = ?
                      AND (cd.twinpact = 0 OR cd.twinpact IS NULL)
                    LIMIT 1`, sqlNorm("c_main.card_name"), sqlNorm("c_partner.card_name"))
				tx.QueryRow(q, normDName, normDPName).Scan(&realCardID)
			} else if dummyCardType == "normal" {
				q := fmt.Sprintf(`
                    SELECT c.card_id 
                    FROM card c
                    LEFT JOIN card_combination ccb ON c.card_id = ccb.card_id
                    WHERE %s = ? 
                      AND ccb.combination_id IS NULL
                    LIMIT 1`, sqlNorm("c.card_name"))
				tx.QueryRow(q, normDName).Scan(&realCardID)
			}

			if realCardID > 0 {
				cardId = &realCardID
				replacedDummyMap[dName] = realCardID
				dummyName = nil
				dummyCost = nil
				dummyCivIds = nil
				dummyCardType = "normal"
				dummyPartnerName = nil
				dummyPartnerCost = nil
				dummyPartnerCivIds = nil
			} else {
				cardId = nil
				dummyName = &dName
			}
		}

		cardTypeInDeck := "main"
		if t, ok := cMap["type"].(string); ok && t != "" {
			cardTypeInDeck = t
		}

		tx.Exec(`INSERT INTO deck_cards (deck_id, card_id, dummy_name, dummy_cost, dummy_civ_ids, dummy_card_type, dummy_partner_name, dummy_partner_cost, dummy_partner_civ_ids, quantity, card_type_in_deck, sort_order) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?)`,
			deckId, cardId, dummyName, dummyCost, dummyCivIds, dummyCardType, dummyPartnerName, dummyPartnerCost, dummyPartnerCivIds, cardTypeInDeck, order)
		order++
	}

	return replacedDummyMap
}

/**
 * ヘルプカード検索画面の表示
 */
func (c *DeckController) HelpSearch(w http.ResponseWriter, r *http.Request) {
	views.RenderView(w, r, "deck/help_search.go", nil)
}

/**
 * ヘルプ・FAQ画面の表示
 */
func (c *DeckController) Help(w http.ResponseWriter, r *http.Request) {
	// RenderFileToString / RenderFile ではなく、共通の RenderView を使用します
	views.RenderView(w, r, "help/index.go", nil)
}

func (c *DeckController) Playtest(w http.ResponseWriter, r *http.Request) {
	deckIdStr := r.URL.Query().Get("deck_id")
	if deckIdStr == "" {
		http.Redirect(w, r, "/mydecks", http.StatusFound)
		return
	}

	db := getDB()
	var deck map[string]interface{}
	// デッキ基本情報取得
	dRows, _ := db.Query("SELECT * FROM decks WHERE deck_id = ?", deckIdStr)
	defer dRows.Close()
	if dRows.Next() {
		cols, _ := dRows.Columns()
		columns := make([]interface{}, len(cols))
		columnPointers := make([]interface{}, len(cols))
		for i := range columns {
			columnPointers[i] = &columns[i]
		}
		dRows.Scan(columnPointers...)
		deck = make(map[string]interface{})
		for i, colName := range cols {
			val := *(columnPointers[i].(*interface{}))
			if b, ok := val.([]byte); ok {
				deck[colName] = string(b)
			} else {
				deck[colName] = val
			}
		}
	}

	deckName := "デッキ"
	if dn, ok := deck["deck_name"].(string); ok && dn != "" {
		deckName = dn
	}

	cardSql := `
        SELECT 
            dc.*, 
            c.card_name, 
            c.cost, 
            cd.imagepath, 
            cd.twinpact,
            cd_partner.imagepath as combination_imagepath, 
            cc_self.combination_id,
            (SELECT GROUP_CONCAT(cardtype_id) FROM card_cardtype WHERE card_id = dc.card_id) as cardtype_ids,
            (SELECT GROUP_CONCAT(CONCAT(ct.cardtype_id, ':', ct.cardtype_name)) FROM card_cardtype cct JOIN cardtype ct ON cct.cardtype_id = ct.cardtype_id WHERE cct.card_id = dc.card_id) as cardtype_data,
            (SELECT GROUP_CONCAT(characteristics_id) FROM card_characteristics WHERE card_id = dc.card_id) as characteristics_ids,
            (SELECT GROUP_CONCAT(ability_id) FROM card_ability WHERE card_id = dc.card_id) as ability_ids,
            (SELECT GROUP_CONCAT(CONCAT(r.race_id, ':', r.race_name)) FROM card_race cr JOIN race r ON cr.race_id = r.race_id WHERE cr.card_id = dc.card_id) as race_data,
            (
                SELECT COUNT(DISTINCT cc_civ.civilization_id)
                FROM card_combination cc_group
                JOIN card_civilization cc_civ ON cc_group.card_id = cc_civ.card_id
                WHERE cc_group.combination_id = cc_self.combination_id
            ) as twin_civ_count,
            (
                SELECT COUNT(DISTINCT cc_self_civ.civilization_id)
                FROM card_civilization cc_self_civ
                WHERE cc_self_civ.card_id = dc.card_id
            ) as self_civ_count
        FROM deck_cards dc
        JOIN card c ON dc.card_id = c.card_id
        LEFT JOIN card_detail cd ON dc.card_id = cd.card_id
        LEFT JOIN card_combination cc_self ON dc.card_id = cc_self.card_id
        LEFT JOIN card_combination cc_partner ON cc_self.combination_id = cc_partner.combination_id 
            AND cc_self.card_id <> cc_partner.card_id
            AND cc_partner.card_id = (
                SELECT MIN(cc_sub.card_id)
                FROM card_combination cc_sub
                WHERE cc_sub.combination_id = cc_self.combination_id
                  AND cc_sub.card_id <> cc_self.card_id
            )
        LEFT JOIN card_detail cd_partner ON cc_partner.card_id = cd_partner.card_id
        WHERE dc.deck_id = ?
        ORDER BY dc.sort_order ASC
    `

	cRows, _ := db.Query(cardSql, deckIdStr)
	defer cRows.Close()

	cols, _ := cRows.Columns()
	var cards []map[string]interface{}
	for cRows.Next() {
		columns := make([]interface{}, len(cols))
		columnPointers := make([]interface{}, len(cols))
		for i := range columns {
			columnPointers[i] = &columns[i]
		}
		cRows.Scan(columnPointers...)
		cMap := make(map[string]interface{})
		for i, colName := range cols {
			val := columnPointers[i].(*interface{})
			if b, ok := (*val).([]byte); ok {
				cMap[colName] = string(b)
			} else {
				cMap[colName] = *val
			}
		}

		combID := cMap["combination_id"]
		if combID != nil && fmt.Sprintf("%v", combID) != "" {
			cMap["civ_count"] = cMap["twin_civ_count"]
		} else {
			cMap["civ_count"] = cMap["self_civ_count"]
		}
		delete(cMap, "twin_civ_count")
		delete(cMap, "self_civ_count")
		cards = append(cards, cMap)
	}

	cardsJSON, _ := json.Marshal(cards)

	views.RenderView(w, r, "deck/playtest.html", map[string]interface{}{
		"hideHeader": true,
		"hideFooter": true,
		"deck":       deck,
		"deckName":   deckName,
		"cards":      cards,
		"cardsJSON":  template.JS(cardsJSON),
	})
}

/**
 * 通報API（ログイン必須・通報ペナルティチェック・ユーザー通報対応）
 */
func (c *DeckController) ReportDeckApi(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json; charset=utf-8")
	userID, ok := getSessionUserID(r)
	if !ok {
		w.WriteHeader(http.StatusUnauthorized)
		json.NewEncoder(w).Encode(map[string]interface{}{"success": false, "error": "通報にはログインが必要です。"})
		return
	}

	db := getDB()
	var reportBan sql.NullString
	err := db.QueryRow("SELECT report_ban_until FROM users WHERE user_id = ?", userID).Scan(&reportBan)
	if err == nil && reportBan.Valid && reportBan.String != "" {
		t, errParse := time.Parse("2006-01-02 15:04:05", reportBan.String)
		if errParse == nil && t.After(time.Now()) {
			w.WriteHeader(http.StatusForbidden)
			json.NewEncoder(w).Encode(map[string]interface{}{
				"success": false,
				"error":   "現在通報機能が制限されています（期限: " + reportBan.String + " まで）。",
			})
			return
		}
	}

	var input map[string]interface{}
	json.NewDecoder(r.Body).Decode(&input)

	var deckId *int = nil
	if did, ok := input["deck_id"].(float64); ok && did > 0 {
		i := int(did)
		deckId = &i
	}
	var reportedUserId *int = nil
	if ruid, ok := input["reported_user_id"].(float64); ok && ruid > 0 {
		i := int(ruid)
		reportedUserId = &i
	}

	reportType := "deck"
	if rt, ok := input["report_type"].(string); ok && rt == "user" {
		reportType = "user"
	}

	var userCategory *string = nil
	if reportType == "user" {
		if cat, ok := input["user_report_category"].(string); ok {
			if cat == "spam" || cat == "inappropriate_name" || cat == "other" {
				userCategory = &cat
			}
		}
	}

	reason := ""
	if r, ok := input["reason"].(string); ok {
		reason = strings.TrimSpace(r)
	}

	if reason == "" && reportType == "user" && userCategory != nil {
		if *userCategory == "spam" {
			reason = "デッキの連投"
		} else if *userCategory == "inappropriate_name" {
			reason = "不適切なユーザー名"
		}
	}

	if reason == "" || (reportType == "deck" && deckId == nil) || (reportType == "user" && reportedUserId == nil) {
		w.WriteHeader(http.StatusBadRequest)
		json.NewEncoder(w).Encode(map[string]interface{}{"success": false, "error": "入力情報が不足しています。"})
		return
	}

	if reportType == "deck" {
		var creatorID int
		err := db.QueryRow("SELECT user_id FROM decks WHERE deck_id = ?", *deckId).Scan(&creatorID)
		if err != nil {
			w.WriteHeader(http.StatusNotFound)
			json.NewEncoder(w).Encode(map[string]interface{}{"success": false, "error": "対象のデッキが見つかりません。"})
			return
		}
		reportedUserId = &creatorID
	}

	_, err = db.Exec(`INSERT INTO deck_reports (deck_id, reported_user_id, user_id, report_type, user_report_category, reason, created_at, updated_at) 
                      VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())`,
		deckId, reportedUserId, userID, reportType, userCategory, reason)
	if err != nil {
		w.WriteHeader(http.StatusInternalServerError)
		json.NewEncoder(w).Encode(map[string]interface{}{"success": false, "error": err.Error()})
		return
	}

	json.NewEncoder(w).Encode(map[string]interface{}{"success": true})
}

/**
 * 特定ユーザーの公開デッキ一覧画面
 */
func (c *DeckController) UserPublicDecks(w http.ResponseWriter, r *http.Request) {
	userIdStr := r.URL.Query().Get("user_id")
	userId, _ := strconv.Atoi(userIdStr)
	if userId == 0 {
		http.Redirect(w, r, "/search", http.StatusFound)
		return
	}

	db := getDB()
	var targetUser map[string]interface{}
	var uname string
	err := db.QueryRow("SELECT user_id, username FROM users WHERE user_id = ?", userId).Scan(&userId, &uname)
	if err != nil {
		http.Redirect(w, r, "/search", http.StatusFound)
		return
	}
	targetUser = map[string]interface{}{"user_id": userId, "username": uname}

	rows, _ := db.Query(`
        SELECT d.*, f.format_name, u.username as creator_name, cd.imagepath as thumbnail_imagepath
        FROM decks d
        JOIN formats f ON d.format_id = f.format_id
        JOIN users u ON d.user_id = u.user_id
        LEFT JOIN card_detail cd ON d.thumbnail_card_id = cd.card_id
        WHERE d.user_id = ? AND d.is_public = 1
        ORDER BY d.updated_at DESC
    `, userId)
	defer rows.Close()

	cols, _ := rows.Columns()
	var decks []map[string]interface{}
	for rows.Next() {
		columns := make([]interface{}, len(cols))
		columnPointers := make([]interface{}, len(cols))
		for i := range columns {
			columnPointers[i] = &columns[i]
		}
		rows.Scan(columnPointers...)
		rowMap := make(map[string]interface{})
		for i, colName := range cols {
			val := columnPointers[i].(*interface{})
			if b, ok := (*val).([]byte); ok {
				rowMap[colName] = string(b)
			} else {
				rowMap[colName] = *val
			}
		}
		decks = append(decks, rowMap)
	}

	views.RenderView(w, r, "deck/user_decks.html", map[string]interface{}{
		"targetUser": targetUser,
		"decks":      decks,
	})
}

/**
 * デッキの公開 / 非公開切り替えAPI
 */
func (c *DeckController) SetDeckPublicApi(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json; charset=utf-8")
	userID, ok := getSessionUserID(r)
	if !ok {
		w.WriteHeader(http.StatusUnauthorized)
		json.NewEncoder(w).Encode(map[string]interface{}{"success": false, "error": "ログインが必要です。"})
		return
	}

	var input map[string]interface{}
	json.NewDecoder(r.Body).Decode(&input)

	deckId := 0
	if did, ok := input["deck_id"].(float64); ok {
		deckId = int(did)
	} else if didStr, ok := input["deck_id"].(string); ok {
		deckId, _ = strconv.Atoi(didStr) // ★ 追加: 文字列の場合も数値に変換
	}
	isPublic := 0
	if pub, ok := input["is_public"]; ok && pub != nil {
		switch v := pub.(type) {
		case bool:
			if v {
				isPublic = 1
			}
		case float64:
			if int(v) > 0 {
				isPublic = 1
			}
		case int:
			if v > 0 {
				isPublic = 1
			}
		case string: // ★ 文字列での送信にも対応
			if v == "1" || v == "true" {
				isPublic = 1
			}
		}
	}

	if deckId == 0 {
		w.WriteHeader(http.StatusBadRequest)
		json.NewEncoder(w).Encode(map[string]interface{}{"success": false, "error": "デッキIDが不足しています。"})
		return
	}

	db := getDB()
	if isPublic == 1 {
		var pubBan sql.NullString
		err := db.QueryRow("SELECT public_ban_until FROM users WHERE user_id = ?", userID).Scan(&pubBan)
		if err == nil && pubBan.Valid && pubBan.String != "" {
			t, errParse := time.Parse("2006-01-02 15:04:05", pubBan.String)
			if errParse == nil && t.After(time.Now()) {
				w.WriteHeader(http.StatusForbidden)
				json.NewEncoder(w).Encode(map[string]interface{}{
					"success": false,
					"error":   fmt.Sprintf("現在ペナルティが科されているため、デッキを公開できません。\n(期限: %s まで)", t.Format("2006/01/02 15:04")),
				})
				return
			}
		}
	}

	res, err := db.Exec("UPDATE decks SET is_public = ?, updated_at = NOW() WHERE deck_id = ? AND user_id = ?", isPublic, deckId, userID)
	if err != nil {
		w.WriteHeader(http.StatusInternalServerError)
		json.NewEncoder(w).Encode(map[string]interface{}{"success": false, "error": err.Error()})
		return
	}
	ra, _ := res.RowsAffected()
	if ra == 0 {
		w.WriteHeader(http.StatusNotFound)
		json.NewEncoder(w).Encode(map[string]interface{}{"success": false, "error": "対象のデッキが見つかりません。"})
		return
	}

	json.NewEncoder(w).Encode(map[string]interface{}{"success": true, "is_public": isPublic})
}

/**
 * 楽天市場APIを用いたデッキ価格査定API
 */
func (c *DeckController) EstimatePriceApi(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json; charset=utf-8")

	// ★ panic発生時に500エラー理由をJSONで安全に返却
	defer func() {
		if rec := recover(); rec != nil {
			log.Printf("[EstimatePriceApi PANIC]: %v", rec)
			w.WriteHeader(http.StatusInternalServerError)
			json.NewEncoder(w).Encode(map[string]interface{}{
				"success": false,
				"error":   fmt.Sprintf("査定処理中にエラーが発生しました: %v", rec),
			})
		}
	}()
	query := r.URL.Query()

	deckIdStr := query.Get("deck_id")
	targetShopCode := strings.TrimSpace(query.Get("shop_code"))
	forceRefresh := query.Get("refresh") != ""
	excludeCardsRaw := query.Get("exclude_cards")

	var excludeCards []string
	if excludeCardsRaw != "" {
		for _, part := range strings.Split(excludeCardsRaw, "|||") {
			excludeCards = append(excludeCards, strings.TrimSpace(part))
		}
	}

	if deckIdStr == "" {
		w.WriteHeader(http.StatusBadRequest)
		json.NewEncoder(w).Encode(map[string]interface{}{"error": "Deck ID is required"})
		return
	}
	deckId, _ := strconv.Atoi(deckIdStr)

	appId := os.Getenv("RAKUTEN_APP_ID")
	if appId == "" {
		appId = "5e43be83-582b-4e0c-aca5-a2a2cdab185d"
	}
	accessKey := os.Getenv("RAKUTEN_ACCESS_KEY")
	if accessKey == "" {
		accessKey = "pk_6YPrWKh1sowRK0R3SSZQDvsmzoPzPZtgIytCQoajcwj"
	}
	affiliateId := os.Getenv("RAKUTEN_AFFILIATE_ID")
	if strings.Contains(affiliateId, "YOUR_") {
		affiliateId = ""
	}

	buildAffiliateUrl := func(targetUrl string) string {
		if targetUrl == "" {
			return ""
		}
		if affiliateId != "" {
			return "https://hb.afl.rakuten.co.jp/hgc/" + affiliateId + "/?pc=" + url.QueryEscape(targetUrl)
		}
		return targetUrl
	}

	db := getDB()
	deckModel := models.NewDeck(db)
	mainCards, err := deckModel.GetCardsByDeckId(deckId)
	if err != nil {
		w.WriteHeader(http.StatusInternalServerError)
		json.NewEncoder(w).Encode(map[string]interface{}{"error": "デッキ情報の取得に失敗しました"})
		return
	}

	db.Exec(`
        CREATE TABLE IF NOT EXISTS card_price_cache (
            cache_key VARCHAR(64) PRIMARY KEY,
            card_name VARCHAR(255) NOT NULL,
            shop_code VARCHAR(100) NOT NULL DEFAULT '',
            price INT NULL,
            affiliate_url TEXT,
            item_title TEXT,
            shop_name VARCHAR(255),
            updated_at DATETIME NOT NULL,
            INDEX idx_updated_at (updated_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    `)

	type CardInfo struct {
		DisplayName string
		IsTwinpact  bool
		TopName     string
		BottomName  string
		Quantity    int
	}

	cardMap := make(map[string]*CardInfo)
	var cardKeys []string

	for _, c := range mainCards {
		name := strings.TrimSpace(fmt.Sprintf("%v", c["card_name"]))
		qty := 1
		if qVal, ok := c["quantity"]; ok {
			if qInt, err := strconv.Atoi(fmt.Sprintf("%v", qVal)); err == nil && qInt > 0 {
				qty = qInt
			}
		}

		isTwinpact := false
		if tp, ok := c["twinpact"]; ok && fmt.Sprintf("%v", tp) == "1" && c["partner_card_id"] != nil {
			isTwinpact = true
		}

		topName := name
		bottomName := ""

		if isTwinpact {
			selfId, _ := strconv.Atoi(fmt.Sprintf("%v", c["card_id"]))
			partnerId, _ := strconv.Atoi(fmt.Sprintf("%v", c["partner_card_id"]))
			partnerCardName := fmt.Sprintf("%v", c["partner_card_name"])
			if selfId < partnerId {
				topName = name
				bottomName = partnerCardName
			} else {
				topName = partnerCardName
				bottomName = name
			}
		}

		key := name
		if isTwinpact {
			key = fmt.Sprintf("%s / %s", topName, bottomName)
		}

		isExcluded := false
		for _, ex := range excludeCards {
			if ex == key {
				isExcluded = true
				break
			}
		}
		if isExcluded {
			continue
		}

		if _, exists := cardMap[key]; !exists {
			cardMap[key] = &CardInfo{
				DisplayName: key,
				IsTwinpact:  isTwinpact,
				TopName:     topName,
				BottomName:  bottomName,
				Quantity:    0,
			}
			cardKeys = append(cardKeys, key)
		}
		cardMap[key].Quantity += qty
	}

	totalPrice := 0
	notFoundCount := 0
	var debugLog []map[string]interface{}
	var items []map[string]interface{}

	apiBaseUrl := "https://openapi.rakuten.co.jp/ichibams/api/IchibaItem/Search/20260701"
	ngTitlePattern := regexp.MustCompile(`(?i)(ス\s*リーブ|カードス\s*リーブ|プレイマット|ラバーマット|デッキケース|ストレージボックス|デッキシールド|カードファイル|バインダー|未開封BOX|未開封パック|くじ|オリパ|プロテクト|DXカード)`)

	normalize := func(str string) string {
		s := strings.ReplaceAll(str, "\\", "")
		s = strings.ReplaceAll(s, "∑", "シグマ")
		s = strings.ReplaceAll(s, "Σ", "シグマ")
		s = width.Fold.String(s)
		s = strings.ToLower(s)
		reg := regexp.MustCompile(`[\s・−―ー〜～~/／()（）「」『』【】[\]"'”"'♪!！？?==::**++&&-]`) // ★ 修正
		return reg.ReplaceAllString(s, "")
	}

	var twinpactTopNames []string
	for _, cm := range cardMap {
		if cm.IsTwinpact {
			twinpactTopNames = append(twinpactTopNames, cm.TopName)
		}
	}

	ambiguousMap := make(map[string]bool)
	if len(twinpactTopNames) > 0 {
		placeholders := make([]string, len(twinpactTopNames))
		args := make([]interface{}, len(twinpactTopNames))
		for i, n := range twinpactTopNames {
			placeholders[i] = "?"
			args[i] = n
		}
		q := fmt.Sprintf(`SELECT c.card_name FROM card c
                          JOIN card_combination cc ON c.card_id = cc.card_id
                          JOIN card_detail cd ON c.card_id = cd.card_id
                          WHERE cd.twinpact = 1 AND c.card_name IN (%s)
                          GROUP BY c.card_name
                          HAVING COUNT(DISTINCT cc.combination_id) > 1`, strings.Join(placeholders, ","))
		rows, err := db.Query(q, args...)
		if err == nil {
			for rows.Next() {
				var name string
				rows.Scan(&name)
				ambiguousMap[name] = true
			}
			rows.Close()
		}
	}

	for _, k := range cardKeys {
		cardInfo := cardMap[k]
		qty := cardInfo.Quantity
		isTwinpact := cardInfo.IsTwinpact
		topName := cardInfo.TopName
		bottomName := cardInfo.BottomName

		sanitizeCardName := func(str string) string {
			if str == "" {
				return ""
			}
			s := strings.ReplaceAll(str, "\\", "")
			count := strings.Count(s, "\"")
			if count >= 2 {
				c := 0
				re := regexp.MustCompile(`"`)
				s = re.ReplaceAllStringFunc(s, func(m string) string {
					c++
					if c%2 == 1 {
						return "“"
					}
					return "”"
				})
			}
			return s
		}

		topName = sanitizeCardName(topName)
		if bottomName != "" {
			bottomName = sanitizeCardName(bottomName)
		}
		topName = strings.ReplaceAll(topName, "∑", "Σ")

		cacheKey := fmt.Sprintf("%x", md5Sum(cardInfo.DisplayName+"_"+targetShopCode))

		if !forceRefresh {
			var cachedPrice sql.NullInt64
			var affUrl, ititle, sname, scode string
			err := db.QueryRow("SELECT price, affiliate_url, item_title, shop_name, shop_code FROM card_price_cache WHERE cache_key = ? AND updated_at > NOW() - INTERVAL 12 HOUR", cacheKey).
				Scan(&cachedPrice, &affUrl, &ititle, &sname, &scode)

			if err == nil {
				var minPrice *int = nil
				if cachedPrice.Valid {
					p := int(cachedPrice.Int64)
					minPrice = &p
					totalPrice += p * qty
				} else {
					notFoundCount++
				}

				var subtotal *int = nil
				if minPrice != nil {
					st := (*minPrice) * qty
					subtotal = &st
				}

				items = append(items, map[string]interface{}{
					"card_name":     cardInfo.DisplayName,
					"quantity":      qty,
					"price":         minPrice,
					"subtotal":      subtotal,
					"affiliate_url": affUrl,
					"item_title":    ititle,
					"shop_name":     sname,
					"shop_code":     scode,
				})
				continue
			}
		}

		isAmbiguous := isTwinpact && ambiguousMap[topName]
		cleanSearchWord := func(str string) string {
			s := strings.ReplaceAll(str, "&amp;", " ")
			s = strings.ReplaceAll(s, "&AMP;", " ")
			re := regexp.MustCompile(`["'”"'()（）「」『』【】[\]〜～~/／!！？?♪==::−―&&-]`) // ★ 修正
			s = re.ReplaceAllString(s, " ")
			fields := strings.Fields(s)
			if len(fields) <= 1 {
				return strings.Join(fields, " ")
			}
			var result []string
			carry := ""
			for _, t := range fields {
				if carry != "" {
					t = carry + t
					carry = ""
				}
				if len([]rune(t)) <= 1 {
					carry = t
				} else {
					result = append(result, t)
				}
			}
			if carry != "" {
				if len(result) > 0 {
					result[len(result)-1] += carry
				} else {
					result = append(result, carry)
				}
			}
			return strings.Join(result, " ")
		}

		baseSearchName := topName
		if isAmbiguous && bottomName != "" {
			baseSearchName = strings.TrimSpace(topName + " " + bottomName)
		}
		isSpecialFiveSet := strings.Contains(topName, "終焉の禁断 ドルマゲドン") || strings.Contains(topName, "零龍")

		safeKw := cleanSearchWord(baseSearchName)
		var keywordsToTry []string
		if isSpecialFiveSet {
			keywordsToTry = []string{safeKw + " 5枚セット", baseSearchName + " 5枚セット"}
		} else {
			keywordsToTry = append(keywordsToTry, safeKw)
			if !strings.Contains(baseSearchName, "-") && baseSearchName != safeKw {
				keywordsToTry = append(keywordsToTry, baseSearchName)
			}
			if strings.Contains(baseSearchName, "“") || strings.Contains(baseSearchName, "”") || strings.Contains(baseSearchName, "\"") || len([]rune(safeKw)) <= 5 {
				keywordsToTry = append(keywordsToTry, "デュエマ "+safeKw)
			}
		}

		normTop := normalize(topName)
		normBottom := ""
		if isTwinpact && bottomName != "" {
			normBottom = normalize(bottomName)
		}

		fetchRakutenItems := func(kw string) ([]map[string]interface{}, int) {
			endpoint := fmt.Sprintf("%s?applicationId=%s&accessKey=%s&keyword=%s&sort=standard&hits=30&minPrice=10",
				apiBaseUrl, url.QueryEscape(appId), url.QueryEscape(accessKey), url.QueryEscape(kw))
			if targetShopCode != "" {
				endpoint += "&shopCode=" + url.QueryEscape(targetShopCode)
			}
			if affiliateId != "" {
				endpoint += "&affiliateId=" + url.QueryEscape(affiliateId)
			}

			client := &http.Client{Timeout: 4 * time.Second}
			var body []byte
			httpCode := 0

			for attempt := 0; attempt <= 1; attempt++ {
				req, _ := http.NewRequest("GET", endpoint, nil)
				req.Header.Set("User-Agent", "Mozilla/5.0")
				req.Header.Set("Origin", "https://due-muscle.up.railway.app")
				req.Header.Set("Referer", "https://due-muscle.up.railway.app/")
				req.Header.Set("Authorization", "Bearer "+accessKey)

				resp, err := client.Do(req)
				if err != nil {
					break
				}
				httpCode = resp.StatusCode
				body, _ = io.ReadAll(resp.Body)
				resp.Body.Close()

				if httpCode == 429 {
					time.Sleep(800 * time.Millisecond)
					continue
				}
				break
			}

			var resData map[string]interface{}
			json.Unmarshal(body, &resData)
			var ret []map[string]interface{}
			if itemsRaw, ok := resData["Items"].([]interface{}); ok {
				for _, itm := range itemsRaw {
					if itmMap, ok := itm.(map[string]interface{}); ok {
						ret = append(ret, itmMap)
					}
				}
			}
			return ret, httpCode
		}

		findBestMatch := func(itemList []map[string]interface{}) map[string]interface{} {
			var matched map[string]interface{} = nil
			lowest := math.MaxInt64

			for _, rawItem := range itemList {
				candidate := rawItem
				if inner, ok := rawItem["Item"].(map[string]interface{}); ok {
					candidate = inner
				}

				if targetShopCode != "" {
					itemShopCode := fmt.Sprintf("%v", candidate["shopCode"])
					if itemShopCode == "" && candidate["shopUrl"] != nil {
						re := regexp.MustCompile(`rakuten\.co\.jp/([^/]+)/`)
						if m := re.FindStringSubmatch(fmt.Sprintf("%v", candidate["shopUrl"])); len(m) > 1 {
							itemShopCode = m[1]
						}
					}
					if itemShopCode != targetShopCode {
						continue
					}
				}

				title := fmt.Sprintf("%v", candidate["itemName"])
				if title == "" || title == "<nil>" {
					title = fmt.Sprintf("%v", candidate["title"])
				}

				titleKana := width.Fold.String(title)
				titleNoSpace := regexp.MustCompile(`\s+`).ReplaceAllString(titleKana, "")
				if ngTitlePattern.MatchString(titleKana) || ngTitlePattern.MatchString(titleNoSpace) {
					continue
				}

				normTitle := normalize(title)
				if isSpecialFiveSet && !strings.Contains(normTitle, "5枚セット") {
					continue
				}

				isMatched := false
				if !isTwinpact {
					if strings.Contains(normTitle, normTop) {
						isMatched = true
					}
				} else {
					if isAmbiguous {
						if normBottom != "" && strings.Contains(normTitle, normTop) && strings.Contains(normTitle, normBottom) {
							isMatched = true
						} else if normBottom != "" && strings.Contains(normTitle, normBottom) {
							isMatched = true
						}
					} else {
						if strings.Contains(normTitle, normTop) {
							isMatched = true
						} else if normBottom != "" && strings.Contains(normTitle, normBottom) {
							isMatched = true
						}
					}
				}

				if isMatched {
					pVal := 0
					if pr, ok := candidate["itemPrice"]; ok {
						pVal, _ = strconv.Atoi(fmt.Sprintf("%v", pr))
					} else if pr, ok := candidate["price"]; ok {
						pVal, _ = strconv.Atoi(fmt.Sprintf("%v", pr))
					}
					if pVal > 0 && pVal < lowest {
						lowest = pVal
						matched = candidate
					}
				}
			}
			return matched
		}

		var matchedItem map[string]interface{} = nil
		bestKeywordUsed := baseSearchName
		lastApiHitCount := 0
		httpCode := 200

		for _, kw := range keywordsToTry {
			cItems, cCode := fetchRakutenItems(kw)
			lastApiHitCount = len(cItems)
			httpCode = cCode

			if cCode == 400 && strings.Contains(kw, " ") {
				noSpaceKw := strings.ReplaceAll(kw, " ", "")
				cItems, cCode = fetchRakutenItems(noSpaceKw)
				lastApiHitCount = len(cItems)
				httpCode = cCode
			}

			if len(cItems) > 0 {
				candidate := findBestMatch(cItems)
				if candidate != nil {
					matchedItem = candidate
					bestKeywordUsed = kw
					break
				}
			}
			time.Sleep(50 * time.Millisecond)
		}

		if matchedItem == nil && strings.Contains(topName, "・") {
			fbKw := strings.ReplaceAll(strings.ReplaceAll(topName, "・", ""), " ", "")
			fbItems, fbCode := fetchRakutenItems(fbKw)
			lastApiHitCount = len(fbItems)
			httpCode = fbCode
			if len(fbItems) > 0 {
				candidate := findBestMatch(fbItems)
				if candidate != nil {
					matchedItem = candidate
					bestKeywordUsed = fbKw
				}
			}
		}

		var minPrice *int = nil
		affiliateUrl := ""
		itemName := ""
		shopName := ""
		shopCode := ""

		if matchedItem != nil {
			p := 0
			if pr, ok := matchedItem["itemPrice"]; ok {
				p, _ = strconv.Atoi(fmt.Sprintf("%v", pr))
			} else if pr, ok := matchedItem["price"]; ok {
				p, _ = strconv.Atoi(fmt.Sprintf("%v", pr))
			}
			minPrice = &p
			itemRawUrl := fmt.Sprintf("%v", matchedItem["itemUrl"])
			if aurl, ok := matchedItem["affiliateUrl"].(string); ok && aurl != "" {
				affiliateUrl = aurl
			} else {
				affiliateUrl = buildAffiliateUrl(itemRawUrl)
			}
			itemName = fmt.Sprintf("%v", matchedItem["itemName"])
			if itemName == "" || itemName == "<nil>" {
				itemName = fmt.Sprintf("%v", matchedItem["title"])
			}
			shopName = fmt.Sprintf("%v", matchedItem["shopName"])
			shopCode = fmt.Sprintf("%v", matchedItem["shopCode"])
			totalPrice += p * qty

			db.Exec(`
                INSERT INTO card_price_cache (cache_key, card_name, shop_code, price, affiliate_url, item_title, shop_name, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE 
                    price = VALUES(price), affiliate_url = VALUES(affiliate_url), 
                    item_title = VALUES(item_title), shop_name = VALUES(shop_name), updated_at = NOW()
            `, cacheKey, cardInfo.DisplayName, shopCode, minPrice, affiliateUrl, itemName, shopName)
		} else {
			notFoundCount++
			searchUrl := "https://search.rakuten.co.jp/search/mall/" + url.QueryEscape(baseSearchName) + "/"
			affiliateUrl = buildAffiliateUrl(searchUrl)
		}

		debugLog = append(debugLog, map[string]interface{}{
			"card_name":      cardInfo.DisplayName,
			"search_keyword": bestKeywordUsed,
			"http_code":      httpCode,
			"hit_count":      lastApiHitCount,
			"picked_item":    itemName,
			"price":          minPrice,
		})

		var subtotal *int = nil
		if minPrice != nil {
			st := (*minPrice) * qty
			subtotal = &st
		}

		items = append(items, map[string]interface{}{
			"card_name":     cardInfo.DisplayName,
			"quantity":      qty,
			"price":         minPrice,
			"subtotal":      subtotal,
			"affiliate_url": affiliateUrl,
			"item_title":    itemName,
			"shop_name":     shopName,
			"shop_code":     shopCode,
		})

		time.Sleep(150 * time.Millisecond)
	}

	sort.SliceStable(items, func(i, j int) bool {
		hasI := items[i]["price"] != nil
		hasJ := items[j]["price"] != nil
		if hasI && !hasJ {
			return true
		}
		return false
	})

	availableShops := make(map[string]string)
	seenShopNames := make(map[string]bool)
	for _, itm := range items {
		sCode := strings.TrimSpace(fmt.Sprintf("%v", itm["shop_code"]))
		sName := strings.TrimSpace(fmt.Sprintf("%v", itm["shop_name"]))
		if sCode != "" && sCode != "<nil>" && sName != "" && sName != "<nil>" {
			if !seenShopNames[sName] {
				availableShops[sCode] = sName
				seenShopNames[sName] = true
			}
		}
	}

	appIdMasked := ""
	if len(appId) >= 6 {
		appIdMasked = appId[:6] + "******"
	}

	json.NewEncoder(w).Encode(map[string]interface{}{
		"success":         true,
		"total_price":     totalPrice,
		"cards":           items,
		"not_found_cards": notFoundCount,
		"shops":           availableShops,
		"selected_shop":   targetShopCode,
		"debug": map[string]interface{}{
			"used_app_id": appIdMasked,
			"logs":        debugLog,
		},
	})
}

/**
 * デッキ共有・個別閲覧画面
 */
func (c *DeckController) Share(w http.ResponseWriter, r *http.Request) {
	deckIdStr := r.URL.Query().Get("deck_id")
	deckId, _ := strconv.Atoi(deckIdStr)
	if deckId == 0 {
		http.Redirect(w, r, "/search", http.StatusFound)
		return
	}

	db := getDB()
	var deck map[string]interface{}
	rows, _ := db.Query(`
        SELECT d.*, f.format_name, u.username as creator_name, cd.imagepath as thumbnail_imagepath
        FROM decks d
        JOIN formats f ON d.format_id = f.format_id
        JOIN users u ON d.user_id = u.user_id
        LEFT JOIN card_detail cd ON d.thumbnail_card_id = cd.card_id
        WHERE d.deck_id = ?
    `, deckId)
	defer rows.Close()

	if rows.Next() {
		cols, _ := rows.Columns()
		columns := make([]interface{}, len(cols))
		columnPointers := make([]interface{}, len(cols))
		for i := range columns {
			columnPointers[i] = &columns[i]
		}
		rows.Scan(columnPointers...)
		deck = make(map[string]interface{})
		for i, colName := range cols {
			val := columnPointers[i].(*interface{})
			if b, ok := (*val).([]byte); ok {
				deck[colName] = string(b)
			} else {
				deck[colName] = *val
			}
		}
	}

	currentUserID, _ := getSessionUserID(r)
	isPublic := false
	deckOwnerID := 0
	if deck != nil {
		if p, ok := deck["is_public"]; ok && fmt.Sprintf("%v", p) == "1" {
			isPublic = true
		}
		deckOwnerID, _ = strconv.Atoi(fmt.Sprintf("%v", deck["user_id"]))
	}

	if deck == nil || (!isPublic && deckOwnerID != currentUserID) {
		views.RenderView(w, r, "deck/share_not_found.html", nil)
		return
	}

	views.RenderView(w, r, "deck/share.html", map[string]interface{}{
		"deck":   deck,
		"pdo_db": db,
	})
}

/**
 * 購入済みカード一覧の取得API（作成者本人のみ取得可能）
 */
func (c *DeckController) GetOwnedCardsApi(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json; charset=utf-8")
	deckIdStr := r.URL.Query().Get("deck_id")
	deckId, _ := strconv.Atoi(deckIdStr)
	userID, ok := getSessionUserID(r)

	if deckId == 0 || !ok {
		json.NewEncoder(w).Encode([]string{})
		return
	}

	db := getDB()
	var ownerID int
	err := db.QueryRow("SELECT user_id FROM decks WHERE deck_id = ?", deckId).Scan(&ownerID)
	if err != nil || ownerID != userID {
		json.NewEncoder(w).Encode([]string{})
		return
	}

	db.Exec(`
        CREATE TABLE IF NOT EXISTS deck_owned_cards (
            deck_id INT NOT NULL,
            card_name VARCHAR(255) NOT NULL,
            PRIMARY KEY (deck_id, card_name),
            INDEX idx_deck_id (deck_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    `)

	rows, err := db.Query("SELECT card_name FROM deck_owned_cards WHERE deck_id = ?", deckId)
	if err != nil {
		w.WriteHeader(http.StatusInternalServerError)
		json.NewEncoder(w).Encode(map[string]interface{}{"error": err.Error()})
		return
	}
	defer rows.Close()

	var list []string
	for rows.Next() {
		var name string
		rows.Scan(&name)
		list = append(list, name)
	}

	if list == nil {
		list = []string{}
	}
	json.NewEncoder(w).Encode(list)
}

/**
 * 購入済みカード一覧の保存API（作成者本人のみ保存可能）
 */
func (c *DeckController) SaveOwnedCardsApi(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json; charset=utf-8")
	userID, ok := getSessionUserID(r)
	if !ok {
		w.WriteHeader(http.StatusUnauthorized)
		json.NewEncoder(w).Encode(map[string]interface{}{"success": false, "error": "ログインが必要です。"})
		return
	}

	var input map[string]interface{}
	json.NewDecoder(r.Body).Decode(&input)

	deckId := 0
	if did, ok := input["deck_id"].(float64); ok {
		deckId = int(did)
	}
	var ownedList []interface{}
	if ol, ok := input["owned_cards"].([]interface{}); ok {
		ownedList = ol
	}

	if deckId == 0 {
		w.WriteHeader(http.StatusBadRequest)
		json.NewEncoder(w).Encode(map[string]interface{}{"success": false, "error": "デッキIDが不足しています。"})
		return
	}

	db := getDB()
	var ownerID int
	err := db.QueryRow("SELECT user_id FROM decks WHERE deck_id = ?", deckId).Scan(&ownerID)
	if err != nil || ownerID != userID {
		w.WriteHeader(http.StatusForbidden)
		json.NewEncoder(w).Encode(map[string]interface{}{"success": false, "error": "デッキの作成者のみ変更できます。"})
		return
	}

	tx, err := db.Begin()
	if err != nil {
		w.WriteHeader(http.StatusInternalServerError)
		json.NewEncoder(w).Encode(map[string]interface{}{"success": false, "error": err.Error()})
		return
	}
	defer tx.Rollback()

	tx.Exec("DELETE FROM deck_owned_cards WHERE deck_id = ?", deckId)

	for _, name := range ownedList {
		cName := strings.TrimSpace(fmt.Sprintf("%v", name))
		if cName != "" && cName != "<nil>" {
			tx.Exec("INSERT INTO deck_owned_cards (deck_id, card_name) VALUES (?, ?)", deckId, cName)
		}
	}

	tx.Commit()
	json.NewEncoder(w).Encode(map[string]interface{}{"success": true})
}

func md5Sum(text string) [16]byte {
	return md5.Sum([]byte(text))
}
