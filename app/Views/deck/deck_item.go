package deck

import (
	"database/sql"
	"html/template"
	"io"
	"strconv"
	"strings"
	"time"
)

// Deck はデッキ情報の構造体
type Deck struct {
	DeckID             int
	DeckName           string
	ThumbnailImagePath string
	CreatorName        string
	UserID             *int
	FormatName         string
	IsPublic           bool
	UpdatedAt          time.Time
}

// DeckColors は文明の有効状態
type DeckColors struct {
	Light  bool
	Water  bool
	Dark   bool
	Fire   bool
	Nature bool
}

// DeckItemViewData はテンプレートに渡すデータ構造
type DeckItemViewData struct {
	Deck        Deck
	Context     string
	DeckColors  DeckColors
	ThumbPath   string
	FormattedAt string
}

// RenderDeckItem はデッキアイテムのHTMLを描画します
func RenderDeckItem(w io.Writer, tmpl *template.Template, deck Deck, context string, db *sql.DB) error {
	deckColors := DeckColors{}

	// 文明の初期化と取得
	if db != nil && deck.DeckID != 0 {
		func() {
			defer func() {
				// 例外時も動作継続
				_ = recover()
			}()

			var colorsRes []int

			// 1. 正規カード（ツインパクト含む）の文明IDを取得
			queryColors := `
				SELECT DISTINCT c_civ.civilization_id
				FROM deck_cards dc
				JOIN card_detail cd ON dc.card_id = cd.card_id
				LEFT JOIN card_combination cc 
					ON dc.card_id = cc.card_id
				LEFT JOIN card_combination cc_all 
					ON cc.combination_id = cc_all.combination_id 
					AND (cd.twinpact = 1 OR cd.twinpact = 'true' OR cd.twinpact IS TRUE)
				JOIN card_civilization c_civ 
					ON c_civ.card_id = dc.card_id 
					OR c_civ.card_id = cc_all.card_id
				WHERE dc.deck_id = ?
				  AND (dc.card_type_in_deck IS NULL OR dc.card_type_in_deck = 'main')
			`
			rows, err := db.Query(queryColors, deck.DeckID)
			if err == nil {
				defer rows.Close()
				for rows.Next() {
					var civID int
					if err := rows.Scan(&civID); err == nil {
						colorsRes = append(colorsRes, civID)
					}
				}
			}

			// ★追加：2. ダミーカードに設定された文明（dummy_civ_ids）も取得してマージ
			queryDummy := `
				SELECT dummy_civ_ids
				FROM deck_cards
				WHERE deck_id = ?
				  AND (card_type_in_deck IS NULL OR card_type_in_deck = 'main')
				  AND dummy_civ_ids IS NOT NULL AND dummy_civ_ids != ''
			`
			dummyRows, err := db.Query(queryDummy, deck.DeckID)
			if err == nil {
				defer dummyRows.Close()
				for dummyRows.Next() {
					var civStr string
					if err := dummyRows.Scan(&civStr); err == nil {
						ids := strings.Split(civStr, ",")
						for _, idStr := range ids {
							id, err := strconv.Atoi(strings.TrimSpace(idStr))
							if err == nil && id > 0 {
								// 重複チェック
								exists := false
								for _, c := range colorsRes {
									if c == id {
										exists = true
										break
									}
								}
								if !exists {
									colorsRes = append(colorsRes, id)
								}
							}
						}
					}
				}
			}

			if len(colorsRes) > 0 {
				for _, id := range colorsRes {
					switch id {
					case 1:
						deckColors.Light = true
					case 2:
						deckColors.Water = true
					case 3:
						deckColors.Dark = true
					case 4:
						deckColors.Fire = true
					case 5:
						deckColors.Nature = true
					}
				}
			}
		}()
	}

	// サムネイル画像パスの解決
	thumbPath := "/images/card/noimage.webp"
	if deck.ThumbnailImagePath != "" {
		path := deck.ThumbnailImagePath
		if strings.HasPrefix(path, "/") {
			thumbPath = "/images/card" + path
		} else {
			thumbPath = "/images/card/" + path
		}
	}

	data := DeckItemViewData{
		Deck:        deck,
		Context:     context,
		DeckColors:  deckColors,
		ThumbPath:   thumbPath,
		FormattedAt: deck.UpdatedAt.Format("2006/01/02"),
	}

	return tmpl.ExecuteTemplate(w, "deck_item.html", data)
}