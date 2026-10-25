package controllers

import (
	"bufio"
	"bytes"
	"context"
	"database/sql"
	models "deck-maker/app/Models"
	"encoding/json"
	"fmt"
	"io"
	"net/http"
	"os"
	"path/filepath"
	"regexp"
	"strings"
	"time"
	"unicode/utf8"
	views "deck-maker/app/Views"


	"golang.org/x/text/encoding/japanese"
	"golang.org/x/text/transform"
	// プロジェクト構成に合わせて Models パッケージのパスを指定してください
)

type RuleBotController struct{}

// JSONエラーレスポンス送信ヘルパー
func sendJSONError(w http.ResponseWriter, status int, msg string) {
	w.WriteHeader(status)
	_ = json.NewEncoder(w).Encode(map[string]string{"error": msg})
}

// chatApi: 質問を受け取り、DB検索およびGemini APIを用いて回答を返す
func (c *RuleBotController) ChatApi(w http.ResponseWriter, r *http.Request) {
	// タイムアウト120秒のコンテキストを設定
	ctx, cancel := context.WithTimeout(r.Context(), 120*time.Second)
	defer cancel()

	w.Header().Set("Content-Type", "application/json; charset=utf-8")

	// ★ PHPのFatal Error等の発生時にJSONを返すシャットダウンハンドラ（panicリカバリ）
	defer func() {
		if rec := recover(); rec != nil {
			sendJSONError(w, http.StatusInternalServerError, fmt.Sprintf("サーバー内部エラー: %v", rec))
		}
	}()

	// リクエストボディの読み取り
	var reqBody struct {
		Message string `json:"message"`
	}
	if err := json.NewDecoder(r.Body).Decode(&reqBody); err != nil && err != io.EOF {
		sendJSONError(w, http.StatusBadRequest, "リクエストの解析に失敗しました。")
		return
	}

	message := strings.TrimSpace(reqBody.Message)
	if message == "" {
		sendJSONError(w, http.StatusBadRequest, "メッセージを入力してください。")
		return
	}

	db, err := models.DatabaseConnect()
	if err != nil {
		sendJSONError(w, http.StatusInternalServerError, err.Error())
		return
	}

	// 1. 質問文のカードを特定（ツインパクトの「上面 / 下面」も分解して検索）
	reCard := regexp.MustCompile(`《([^》]+)》`)
	matches := reCard.FindAllStringSubmatch(message, -1)

	var searchNames []string
	seenNames := make(map[string]bool)

	for _, m := range matches {
		rName := m[1]
		if strings.Contains(rName, "/") {
			parts := strings.Split(rName, "/")
			for _, p := range parts {
				pTrim := strings.TrimSpace(p)
				if pTrim != "" && !seenNames[pTrim] {
					seenNames[pTrim] = true
					searchNames = append(searchNames, pTrim)
				}
			}
		} else {
			rTrim := strings.TrimSpace(rName)
			if rTrim != "" && !seenNames[rTrim] {
				seenNames[rTrim] = true
				searchNames = append(searchNames, rTrim)
			}
		}
	}

	type CardRecord struct {
		CardID        int
		CardName      string
		Reading       sql.NullString
		Cost          sql.NullString
		Pow           sql.NullString
		Text          string
		Twinpact      sql.NullInt64
		Hypermode     sql.NullInt64
		CombinationID sql.NullInt64
		SortOrder     sql.NullInt64
		CivNames      sql.NullString
		RaceNames     sql.NullString
		Typenames     sql.NullString
	}

	var matchedCards []CardRecord

	if len(searchNames) > 0 {
		placeholders := make([]string, len(searchNames))
		args := make([]interface{}, len(searchNames))
		for i, n := range searchNames {
			placeholders[i] = "?"
			args[i] = n
		}

		inQuery := strings.Join(placeholders, ",")
		sqlQuery := fmt.Sprintf(`
            WITH target_base_cards AS (
                SELECT c.card_id, c.card_name, cd.twinpact,
                       ROW_NUMBER() OVER (PARTITION BY c.card_name, cd.twinpact ORDER BY c.card_id DESC) AS rn
                FROM card c
                JOIN card_detail cd ON c.card_id = cd.card_id
                WHERE c.card_name IN (%s)
            ),
            latest_targets AS (
                SELECT card_id FROM target_base_cards WHERE rn = 1
            ),
            all_related_card_ids AS (
                -- 通常カード（組み合わせなし）
                SELECT lt.card_id, NULL AS combination_id, 1 AS sort_order
                FROM latest_targets lt
                LEFT JOIN card_combination cc ON lt.card_id = cc.card_id
                WHERE cc.combination_id IS NULL

                UNION ALL

                -- 組み合わせカード（同じcombination_idを持つ全構成面：card_id昇順で上面/下面を判定）
                SELECT cc_all.card_id, cc_all.combination_id, cc_all.card_id AS sort_order
                FROM latest_targets lt
                JOIN card_combination cc ON lt.card_id = cc.card_id
                JOIN card_combination cc_all ON cc.combination_id = cc_all.combination_id
            )
            SELECT DISTINCT 
                c.card_id,
                c.card_name,
                c.reading,
                c.cost,
                c.pow,
                c.text,
                cd.twinpact,
                cd.hypermode,
                rel.combination_id,
                rel.sort_order,
                (SELECT GROUP_CONCAT(civ.civilization_name SEPARATOR ' / ') 
                 FROM card_civilization cciv 
                 JOIN civilization civ ON cciv.civilization_id = civ.civilization_id 
                 WHERE cciv.card_id = c.card_id) AS civ_names,
                (SELECT GROUP_CONCAT(r.race_name SEPARATOR ' / ') 
                 FROM card_race cr 
                 JOIN race r ON cr.race_id = r.race_id 
                 WHERE cr.card_id = c.card_id) AS race_names,
                (SELECT GROUP_CONCAT(ct.typename SEPARATOR ' / ') 
                 FROM card_cardtype ct 
                 WHERE ct.card_id = c.card_id) AS typenames
            FROM all_related_card_ids rel
            JOIN card c ON rel.card_id = c.card_id
            JOIN card_detail cd ON c.card_id = cd.card_id
            ORDER BY rel.combination_id, rel.sort_order ASC
        `, inQuery)

		rows, err := db.QueryContext(ctx, sqlQuery, args...)
		if err != nil {
			sendJSONError(w, http.StatusInternalServerError, err.Error())
			return
		}
		defer rows.Close()

		for rows.Next() {
			var rec CardRecord
			if err := rows.Scan(
				&rec.CardID,
				&rec.CardName,
				&rec.Reading,
				&rec.Cost,
				&rec.Pow,
				&rec.Text,
				&rec.Twinpact,
				&rec.Hypermode,
				&rec.CombinationID,
				&rec.SortOrder,
				&rec.CivNames,
				&rec.RaceNames,
				&rec.Typenames,
			); err != nil {
				sendJSONError(w, http.StatusInternalServerError, err.Error())
				return
			}
			matchedCards = append(matchedCards, rec)
		}
	}

	// 関連カード情報のテキスト構築
	cardContext := ""
	if len(matchedCards) > 0 {
		cardContext += "【関連カード情報】\n"

		// combination_id ごとにグループ化
		groupedMap := make(map[string][]CardRecord)
		var groupOrder []string

		for _, c := range matchedCards {
			var key string
			if c.CombinationID.Valid {
				key = fmt.Sprintf("combo_%d", c.CombinationID.Int64)
			} else {
				key = fmt.Sprintf("single_%d", c.CardID)
			}
			if _, exists := groupedMap[key]; !exists {
				groupOrder = append(groupOrder, key)
			}
			groupedMap[key] = append(groupedMap[key], c)
		}

		for _, key := range groupOrder {
			cards := groupedMap[key]
			if len(cards) > 1 {
				first := cards[0]
				var flags []string
				if first.Twinpact.Valid && first.Twinpact.Int64 != 0 {
					flags = append(flags, "ツインパクト")
				}
				if first.Hypermode.Valid && first.Hypermode.Int64 != 0 {
					flags = append(flags, "ハイパーモード")
				}
				flagStr := "【複数面カード】"
				if len(flags) > 0 {
					flagStr = "【" + strings.Join(flags, "/") + "】"
				}

				cardContext += fmt.Sprintf("■ %s (構成面数: %d)\n", flagStr, len(cards))
				for idx, c := range cards {
					sideNum := idx + 1
					typenames := "なし"
					if c.Typenames.Valid && c.Typenames.String != "" {
						typenames = c.Typenames.String
					}
					civNames := "無色"
					if c.CivNames.Valid && c.CivNames.String != "" {
						civNames = c.CivNames.String
					}
					raceNames := "なし"
					if c.RaceNames.Valid && c.RaceNames.String != "" {
						raceNames = c.RaceNames.String
					}
					cost := "-"
					if c.Cost.Valid && c.Cost.String != "" {
						cost = c.Cost.String
					}
					pow := "-"
					if c.Pow.Valid && c.Pow.String != "" {
						pow = c.Pow.String
					}

					cardContext += fmt.Sprintf("  [面%d] 《%s》\n", sideNum, c.CardName)
					cardContext += fmt.Sprintf("    タイプ: %s\n", typenames)
					cardContext += fmt.Sprintf("    文明: %s\n", civNames)
					cardContext += fmt.Sprintf("    種族: %s\n", raceNames)
					cardContext += fmt.Sprintf("    コスト: %s / パワー: %s\n", cost, pow)
					cardContext += fmt.Sprintf("    効果テキスト: %s\n\n", c.Text)
				}
			} else {
				c := cards[0]
				var flags []string
				if c.Twinpact.Valid && c.Twinpact.Int64 != 0 {
					flags = append(flags, "ツインパクト")
				}
				if c.Hypermode.Valid && c.Hypermode.Int64 != 0 {
					flags = append(flags, "ハイパーモード")
				}
				flagStr := ""
				if len(flags) > 0 {
					flagStr = " 【" + strings.Join(flags, "/") + "】"
				}

				typenames := "なし"
				if c.Typenames.Valid && c.Typenames.String != "" {
					typenames = c.Typenames.String
				}
				civNames := "無色"
				if c.CivNames.Valid && c.CivNames.String != "" {
					civNames = c.CivNames.String
				}
				raceNames := "なし"
				if c.RaceNames.Valid && c.RaceNames.String != "" {
					raceNames = c.RaceNames.String
				}
				cost := "-"
				if c.Cost.Valid && c.Cost.String != "" {
					cost = c.Cost.String
				}
				pow := "-"
				if c.Pow.Valid && c.Pow.String != "" {
					pow = c.Pow.String
				}

				cardContext += fmt.Sprintf("■ 《%s》%s\n", c.CardName, flagStr)
				cardContext += fmt.Sprintf("  タイプ: %s\n", typenames)
				cardContext += fmt.Sprintf("  文明: %s\n", civNames)
				cardContext += fmt.Sprintf("  種族: %s\n", raceNames)
				cardContext += fmt.Sprintf("  コスト: %s / パワー: %s\n", cost, pow)
				cardContext += fmt.Sprintf("  効果テキスト: %s\n\n", c.Text)
			}
		}
	}

	// 1-2. 質問文から該当・類似する特殊能力を抽出し、代表カード（例文テキスト）を取得
	abilityContext := ""
	abilityRows, err := db.QueryContext(ctx, "SELECT ability_id, ability_name FROM ability")
	if err != nil {
		sendJSONError(w, http.StatusInternalServerError, err.Error())
		return
	}
	defer abilityRows.Close()

	type AbilityRecord struct {
		AbilityID   int
		AbilityName string
	}
	var allAbilities []AbilityRecord
	for abilityRows.Next() {
		var ab AbilityRecord
		if err := abilityRows.Scan(&ab.AbilityID, &ab.AbilityName); err == nil {
			allAbilities = append(allAbilities, ab)
		}
	}

	removeSymbols := func(s string) string {
		s = strings.ReplaceAll(s, "・", "")
		s = strings.ReplaceAll(s, " ", "")
		s = strings.ReplaceAll(s, "　", "")
		return s
	}

	cleanMessage := strings.ToLower(removeSymbols(message))
	type MatchedAbility struct {
		ID   int
		Name string
	}
	var matchedAbilities []MatchedAbility

	for _, ab := range allAbilities {
		cleanAbName := strings.ToLower(removeSymbols(ab.AbilityName))
		if utf8.RuneCountInString(cleanAbName) >= 2 && strings.Contains(cleanMessage, cleanAbName) {
			matchedAbilities = append(matchedAbilities, MatchedAbility{ID: ab.AbilityID, Name: ab.AbilityName})
		}
	}

	if len(matchedAbilities) > 0 {
		abilityContext += "【関連する特殊能力と例文カード】\n"
		stmtExample, err := db.PrepareContext(ctx, `
            SELECT c.card_name, c.text 
            FROM card_ability ca 
            JOIN card c ON ca.card_id = c.card_id 
            WHERE ca.ability_id = ? AND c.text IS NOT NULL AND c.text <> ''
            ORDER BY c.card_id DESC 
            LIMIT 2
        `)
		if err == nil {
			defer stmtExample.Close()
			for _, ma := range matchedAbilities {
				abilityContext += fmt.Sprintf("■ 特殊能力名: %s\n", ma.Name)
				exRows, err := stmtExample.QueryContext(ctx, ma.ID)
				if err == nil {
					for exRows.Next() {
						var cardName, text string
						if err := exRows.Scan(&cardName, &text); err == nil {
							abilityContext += fmt.Sprintf("  【例文カード】《%s》\n", cardName)
							abilityContext += fmt.Sprintf("  効果テキスト: %s\n", text)
						}
					}
					exRows.Close()
				}
				abilityContext += "\n"
			}
		}
	}

	// 2. 総合ルールテキストを確実に読み込み
	baseDir, _ := os.Getwd()
	path1 := filepath.Join(baseDir, "storage", "rules.txt")
	path2 := filepath.Join(filepath.Dir(baseDir), "storage", "rules.txt")

	ruleFilePath := ""
	if _, err := os.Stat(path1); err == nil {
		ruleFilePath = path1
	} else if _, err := os.Stat(path2); err == nil {
		ruleFilePath = path2
	}

	var comprehensiveRules string
	if ruleFilePath != "" {
		rawRules, err := os.ReadFile(ruleFilePath)
		if err == nil {
			// UTF-8か判定し、Shift-JISやEUC-JP等なら変換（標準ではShift-JIS試行）
			if utf8.Valid(rawRules) {
				comprehensiveRules = string(rawRules)
			} else {
				reader := transform.NewReader(bytes.NewReader(rawRules), japanese.ShiftJIS.NewDecoder())
				decoded, err := io.ReadAll(reader)
				if err == nil {
					comprehensiveRules = string(decoded)
				} else {
					comprehensiveRules = string(rawRules)
				}
			}
		}
	}

	// 3. システム指示
	systemInstruction := `あなたはTCG「デュエル・マスターズ」の公認ジャッジです。
プレイヤーの質問に対し、提示された【総合ゲームルール原文】、カード情報、および特殊能力情報に基づいて、
【結論から端的に、聞かれたことだけに絞って】回答してください。

【厳格な回答ルール】
1. 挨拶や前置き（「ご質問ありがとうございます」等）は一切不要です。
2. 1行目は必ず【結論】（例: 「可能です。」「対象になります。」「〜という能力です。」等）から始めてください。
3. 【種族に関する重要な判定ルール】：
・複数種族はスラッシュ「 / 」で区切られて提示されます。種族名に含まれる中黒「・」（例: 「アウトレイジ・ドラゴン」「アーマード・ドラゴン」等）は種族名の一部であり、これで「1つの独立した種族名」です。「・」で勝手に分割しないでください。
・デュエル・マスターズのルール上、種族を指定する効果（例: 「ドラゴン」「コマンド」等）は、種族名の中にその文字列を含んでいる場合に有効です。例えば「アウトレイジ・ドラゴン」は名前に「ドラゴン」を含むため、「ドラゴン」を指定するすべての効果の対象になります。
4. 【特殊能力についての質問の場合】：
能力の概要・ルール処理を簡潔に説明した上で、必ず提供された【例文カード】の名前とテキストを引用し、具体例として提示してください。
5. 【カードの効果テキスト、文明、種族、コスト、パワーなどカード情報自体の質問の場合】：
総合ルールの条文番号や項番は一切記載せず、提示されたカード情報の内容のみをわかりやすく回答してください。
6. 【ルールの処理・挙動・裁定に関する質問の場合】：
根拠として、該当する総合ルールの条文番号（例: 803.1 など）や効果の解決順序を簡潔に示してください。
7.「出る時」や「出す時」と書かれている効果は状態定義効果と言います。これらはバトルゾーンに出る前に処理されます。
置換効果によってカードが実際にそのゾーンに置かれない場合、状態定義効果は何もしません。
また、状態定義効果で他のクリーチャーになったとき、そのクリーチャーが「EXライフ」のような別の状態定義効果を持っていた場合、適用するタイミングが遅れているため、「EXライフ」は適用できません。
8. 質問で聞かれていない余談や私見は語らず、文章が途中で切れないよう必ず文末まで完結させてください。`

	// 4. 有料Gemini API へ総合ルールとカード情報・質問を送信
	apiKey := os.Getenv("GEMINI_API_KEY")
	if apiKey == "" {
		// .env からフォールバック読み込み
		envPaths := []string{filepath.Join(baseDir, ".env"), filepath.Join(filepath.Dir(baseDir), ".env")}
		for _, ep := range envPaths {
			if f, err := os.Open(ep); err == nil {
				scanner := bufio.NewScanner(f)
				for scanner.Scan() {
					line := strings.TrimSpace(scanner.Text())
					if strings.HasPrefix(line, "GEMINI_API_KEY=") {
						apiKey = strings.TrimSpace(strings.TrimPrefix(line, "GEMINI_API_KEY="))
						break
					}
				}
				f.Close()
				if apiKey != "" {
					break
				}
			}
		}
	}

	apiUrl := "https://generativelanguage.googleapis.com/v1beta/models/gemini-3.8-flash:generateContent?key=" + apiKey
	userPrompt := fmt.Sprintf("【デュエル・マスターズ 総合ゲームルール原文】\n%s\n\n%s\n\n%s\n\n【プレイヤーからの質問】\n%s",
		comprehensiveRules, cardContext, abilityContext, message)

	payload := map[string]interface{}{
		"systemInstruction": map[string]interface{}{
			"parts": []map[string]string{
				{"text": systemInstruction},
			},
		},
		"contents": []map[string]interface{}{
			{
				"role": "user",
				"parts": []map[string]string{
					{"text": userPrompt},
				},
			},
		},
		"generationConfig": map[string]interface{}{
			"temperature":     0.0,
			"maxOutputTokens": 4096,
		},
	}

	payloadBytes, err := json.Marshal(payload)
	if err != nil {
		sendJSONError(w, http.StatusInternalServerError, err.Error())
		return
	}

	geminiReq, err := http.NewRequestWithContext(ctx, http.MethodPost, apiUrl, bytes.NewBuffer(payloadBytes))
	if err != nil {
		sendJSONError(w, http.StatusInternalServerError, err.Error())
		return
	}
	geminiReq.Header.Set("Content-Type", "application/json")

	client := &http.Client{Timeout: 60 * time.Second}
	resp, err := client.Do(geminiReq)
	if err != nil {
		sendJSONError(w, http.StatusInternalServerError, "Gemini APIの呼び出しに失敗しました: "+err.Error())
		return
	}
	defer resp.Body.Close()

	respBytes, err := io.ReadAll(resp.Body)
	if err != nil {
		sendJSONError(w, http.StatusInternalServerError, err.Error())
		return
	}

	if resp.StatusCode != http.StatusOK {
		sendJSONError(w, http.StatusInternalServerError, "Gemini APIの呼び出しに失敗しました: "+string(respBytes))
		return
	}

	var resData struct {
		Candidates []struct {
			Content struct {
				Parts []struct {
					Text string `json:"text"`
				} `json:"parts"`
			} `json:"content"`
		} `json:"candidates"`
	}

	if err := json.Unmarshal(respBytes, &resData); err != nil {
		sendJSONError(w, http.StatusInternalServerError, err.Error())
		return
	}

	reply := ""
	if len(resData.Candidates) > 0 {
		for _, part := range resData.Candidates[0].Content.Parts {
			reply += part.Text
		}
	}

	if reply == "" {
		reply = "回答を取得できませんでした。"
	}

	_ = json.NewEncoder(w).Encode(map[string]string{"reply": reply})
}

// index: 専用画面を表示
func (c *RuleBotController) Index(w http.ResponseWriter, r *http.Request) {
	views.RenderView(w, r, "rule_bot/index.html", map[string]interface{}{
		"PageTitle": "デュエマ ルール相談AI",
	})
}
