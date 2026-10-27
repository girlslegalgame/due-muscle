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

				reading := "なし"
				if c.Reading.Valid && c.Reading.String != "" {
					reading = c.Reading.String
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

				cardContext += fmt.Sprintf("■ 《%s》（読み: %s）%s\n", c.CardName, reading, flagStr)
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
提示された【総合ゲームルール原文】、カード情報、および特殊能力情報に基づいて、
以下の【思考プロセス】を経て、【結論から端的に、聞かれたことだけに絞って】回答してください。

【ジャッジ思考プロセス（内部で必ず実行すること）】
1. 関係するカードテキストや種族名、能力の分類（常時・誘発・置換・状態定義）を正確に確認する。
2. 処理タイミング（出る前/出た後）、AP優先、置換効果の非連鎖、タイムスタンプ（後出し優先）を照合する。
3. 公式Q&Aに確定裁定がある場合はそれを最優先し、客観的な事実のみで結論を確定させる。

【厳格な回答ルール】
1. 挨拶や前置き（「ご質問ありがとうございます」等）は一切不要です。
2. 1行目は必ず【結論】（例: 「可能です。」「対象になります。」「〜という能力です。」等）から始めてください。
3. 【種族に関する重要な判定ルール】：
・複数種族はスラッシュ「 / 」で区切られて提示されます。種族名に含まれる中黒「・」（例: 「アウトレイジ・ドラゴン」「アーマード・ドラゴン」等）は種族名の一部であり、これで「1つの独立した種族名」です。「・」で勝手に分割しないでください。
・デュエル・マスターズのルール上、種族を指定する効果（例: 「ドラゴン」「コマンド」等）は、種族名の中にその文字列を含んでいる場合に有効です。例えば「アウトレイジ・ドラゴン」は名前に「ドラゴン」を含むため、「ドラゴン」を指定するすべての効果の対象になります。
4. 【特殊能力についての質問の場合】：
能力の概要・ルール処理を簡潔に説明してください。
提供された【例文カード情報】がある場合はその名前とテキストをそのまま引用してください。
提示されていない場合は、カード名やテキストを勝手に捏造・創作することは固く禁じます。
5. 【カードの効果テキスト、文明、種族、コスト、パワーなどカード情報自体の質問の場合】：
総合ルールの条文番号や項番は一切記載せず、提示されたカード情報の内容のみをわかりやすく回答してください。
6. 【ルールの処理・挙動・裁定に関する質問の場合】：
根拠として、該当する総合ルールの条文番号（例: 803.1 など）や効果の解決順序を簡潔に示してください。
7. 【状態定義効果（出る時/出す時）のルール】：
「出る時」や「出す時」と書かれている効果は状態定義効果と言います。これらはバトルゾーンに出る前に処理されます。
置換効果によってカードが実際にそのゾーンに置かれない場合、状態定義効果は何もしません。
また、状態定義効果で他のクリーチャーになったとき、そのクリーチャーが「EXライフ」のような別の状態定義効果を持っていた場合、適用するタイミングが遅れているため、「EXライフ」は適用できません。
《テラ・スザーク <ロマノフ.Star>》のように、状態定義効果で進化元をバトルゾーンから離れさせる効果は、結果的に進化元がいなくなる場合でも行うことができます。この場合、《テラ・スザーク <ロマノフ.Star>》は出せず、元の場所に戻ります。
クリーチャーが出る時、リンクなどの状態定義効果でそのカードの情報が変わった場合、他の出た時に起こる効果やルールは、変わった後の情報が参照されます。
状態定義効果は、元あったゾーンの状態を見て、効果を適用するかを決定します
例1:
自分の《薫風妖精Re:コートニー》と《緊縛の影バインド・シャドウ》がバトルゾーンにいる状況で、自分はマナゾーンから《再生妖精スズラン》を召喚した場合、《緊縛の影バインド・シャドウ》の状態定義効果でマナゾーンにあったときの特性を参照するため、タップ状態でバトルゾーンに出ます。
例2:
相手のバトルゾーンに《聖鎧亜キング・アルカディアス》がいて、自分のバトルゾーンに《薫風妖精コートニー》がいる時、クリーチャーをマナゾーンからバトルゾーンに出す際、バトルゾーンに置かれるまではそのクリーチャーはマナゾーンのカードという扱いになるため、マナゾーンから単色のクリーチャーをバトルゾーンに出すことができます。

8.総合ルールで判断しきれない部分があるのでそれについて説明します。
《Library of Alexandria》のような、「攻撃の前に」の能力はメインステップに行う処理です。
 
「攻撃するかわりに」の能力は攻撃ステップで行います。

「実行する」や「出す」効果で《新世界王の創造》のようなセルを選ぶことはできません。

キング・セルは単独ではキング・セル以外のカードタイプで扱うことはできません。

常在型能力はその条件を満たしている間のみ、効果を適用します。(総合ルール608.3)

革命チェンジが各ターン1度だけ適用できる置換効果などで入れ替えが失敗した場合、その置換効果は適用したことにはなりません。

名前指定の効果では、ルビも指定されます。
例:
《ナ・チュラルゴ・デンジャー / ナチュラル・トラップ》の能力に「名前に《トラップ》とある呪文を唱えた時」とあるので、ルビにトラップを含む《調和と繁栄の罠》を唱えた時、能力がトリガーする。

「同じ名前を持つカード」とは、指定したクリーチャーの名前と完全に同じ文字列が名前に含まれているカードを指します。
例1:
《龍覇 グレンモルト》から見て、《二刀龍覇 グレンモルト 「王」》は同じ名前を持つカードです。
例2:
《トロン》から見て《ドンジャングルS7》は、読みが《ドンジャングルストロングセブン》のため、同じ名前を持つカードです。

「○○枚まで××する。その後、同じ数の～」というテキストで、「××する」行為が行えなかった場合でも、その行為を行うとした数を「同じ数」に適応させることができます。
例1:
相手の《鬼星の絆 フウジン天&ライジン天》がバトルゾーンにいる状況で、自分のターン中、自分は《マーシャル・クイーン》を出し、「出た時」で自分の手札3枚をシールド化しようとしました。ですが、《鬼星の絆 フウジン天&ライジン天》の能力により、自分はシールド化ができませんでした。この場合でも、《マーシャル・クイーン》の能力で自分のシールドをブレイクすることはできます。

カードを出せない状況でも、カードを出そうとしたり、実行しようとすることはできます。
例:
自分の《神歌の悪夢 ナイトメア・キリコ》の「出た時」の能力で山札の上6枚を見たところ、その中に2枚目の《神歌の悪夢 ナイトメア・キリコ》と《エンペラー・キリコ》がありました。 《神歌の悪夢 ナイトメア・キリコ》と《エンペラー・キリコ》を山札から出すクリーチャーとして指定し、先に《エンペラー・キリコ》をバトルゾーンの《神歌の悪夢 ナイトメア・キリコ》の上に進化して重ねた後、その上にさらに2体目の《神歌の悪夢 ナイトメア・キリコ》を進化して出すことができます。

「中央G・リンク」を持つゴッドは、自分や、他のゴッドが出る時、自分のゴッドのリンクを好きなだけ外し、その後、「右G・リンク」または「左G・リンク」を持つゴッドにリンクさせることができます。そのため、《ヨミとイズモの計画》で「クリーチャーを1体、自分の墓地から出してもよい。その後、それにリンクできる好きな数のゴッドを、自分の墓地からリンクして出してもよい。」で「中央G・リンク」を持つゴッドを出した場合、それが左右両方でリンクしている場合でも、墓地にあるすべての「右G・リンク」または「左G・リンク」を持つゴッドを、出すときにリンクを外し、それを、それにリンクできるクリーチャーにリンクして出す、ということを繰り返し、結果的にすべて出すことができます。
 
《ヨミとイズモの計画》等の「それにリンクできる」の「それ」は、出してリンクしていた場合、リンク後の特性を参照します。
例:
自分のリンクしている《聖霊左機 コットン》《機械神類 ヨミ》がバトルゾーンにいる状況です。 《ヨミとイズモの計画》を唱えて、まず墓地から《悪魔右機 フリル》を《聖霊左機 コットン》《機械神類 ヨミ》にリンクして出しました。 その後、《ヨミとイズモの計画》の「それにリンクできる好きな数のゴッドを、自分の墓地からリンクして出してもよい」で自分が出せるのは、「左G・リンク」「中央G・リンク」「右G・リンク」のどれかを持つゴッドです。

《プロジェクト・ゴッド》の効果は、5枚墓地に置いた時点で、出したいゴッドをすべて選ぶ必要があります。そのため、自分のリンクしている「右G・リンク」を持つゴッドと「左G・リンク」を持つクリーチャーがいるときに唱えた場合、「中央G・リンク」を持つゴッドのみ出してリンクできます。

クリーチャーの再構築前、リンクしていたクリーチャーと再構築後のクリーチャーが適正であれば、それは維持されます。

《FORBIDDEN STAR ～世界最後の日～》の「バトルゾーンにある自分の封印は、各ターン1つだけ、闇または火のコスト5以上のコマンドを召喚または「侵略ZERO」を使って出すことによってのみ、外すことができる。」は、封印されているカードが闇でも火でもなくても、闇か火を召喚した時に封印を外せるようにします。また、召喚して、結果的にバトルゾーンに出なかった場合でも、外すことが可能になります。

進化などによってカードの下に置かれているカードは通常のゲーム中は特性がすべて無視されますが、他のカード効果やルールにカード指定で指定される時のみ、特性が参照されます。(200.3a)
例:
カードの下に置かれている《晴舞龍 ズンドコ・モモキング》は、《イデア・パラドックス》のようなカードを指定する効果によって破壊できますが、《晴舞龍 ズンドコ・モモキング》の「このカードが離れた時」の能力はトリガーしません。
 
特定の能力を無視する効果は、それに該当する行のすべての能力を無視します。
例:
自分の《コアラ大佐の虹勲章》と《禁断英雄 モモキングダムX》がいる時、《禁断英雄 モモキングダムX》の「このクリーチャーは、カードが6枚以上含まれていなければ、攻撃できず、相手に選ばれない。」がすべて無視され、攻撃できるようになり、選ばれるようになります。
 
《連戦の精霊龍 カイザルルール》の「自分のターンのはじめに、自分の山札の上から1枚を見てもよい。それがエンジェル・コマンドなら出してもよい。こうして出さなければ、それをシールド化する。」のような能力で山札の上のカードを見ることができなかった時、参照する対象が無いため、「こうして出さなければ」の処理を行う事はできません。
 
「出た時にトリガーする能力を1つ使う」能力では、「○○によって出た時」のような条件付きの物も使うことができます。ただし、「「このクリーチャーが出た時」で始まる能力を1つ使ってもよい。」の場合、「○○によって出た時」は「このクリーチャーが出た時」で始まっていないため、使うことはできません。
例1:
自分の《勝熱百覇 モモキングReVo》がバトルゾーンにいる時、自分は《にやにYAH》を出しました。「出た時」の能力で「Jビッグ1する。」を選び、それで《勝熱百覇 モモキングReVo》を選んだ場合、「キリフダReVo」を使うことができます。
例2:
《クリス=タブラ=ラーサ》の「召喚によって出た時」の能力で《「修羅」の頂 VAN・ベートーベン》が持つ「召喚によって出た時」の能力は使えません。 

「出た時」にトリガーする能力を2つ以上持つクリーチャーには「自分のクリーチャーが出て、それの能力がトリガーする時」能力は、それらすべてに適応されます。
例:
《凶鬼90号 ゾレーゴ / 「大当たり!もう一本!!」》の呪文側を唱えてから、自分のクリーチャーをバトルゾーンに出しました。そのクリーチャーがバトルゾーンに出た時にトリガーする能力を２つ以上持っている場合、そのクリーチャーのすべての出た時にトリガーする能力が2回トリガーします。
 
何かしらの処理に置換効果が適応された場合、その処理は行ったことにはなりません。
例1:
自分のシールドゾーンに表向きの《深き知識の神殿 ブルー・ツナミ》と相手の《魔素縫合 アモデゴラス》が状況で、自分のターン中、自分がこのターン2枚目のカードを引く時、それはかわりにマナゾーンに置かれます。結果的にカードを引いたことにはならないので、《深き知識の神殿 ブルー・ツナミ》の「自分がカードを引いた時」の能力はトリガーしません。
例2:
自分のマナゾーンに水と火の文明があるとき《スケルトン・デバイス》を唱えて、手札から《夜露死苦 キャロル》を捨てる際、かわりに手札に戻ったため、「捨てたクリーチャー」として《夜露死苦 キャロル》を参照することはできない。

「××なら、△△する。その後□□する。」のような能力で「それが××なら」の条件に合わない場合、その後の同一テキストの処理はすべて行う事はできません。
例:1
《叡光の精霊アレックス》の「出た時」の能力で山札の上から1枚マナゾーンに置いたところ、それがエンジェル・コマンド・カードではなかったので、「その後、カードを1枚、自分のマナゾーンから手札に戻してもよい」は行えません。
例:2
バトルゾーンとマナゾーンに合計3枚のジョーカーズがあるとき、《ガヨウ神》を召喚しました。条件を満たしていないので2枚カードを引くことはできず、「その後」の処理でジョーカーズを1枚捨て、2枚引くことはできません

自分のクリーチャーに能力を与える能力や、進化する時に、進化元に付与されている効果を受け継ぐ処理は、出る直前に適応されます。ただし、進化後のクリーチャーが付与する条件に当てはまらない場合、適応されません。これは状態定義効果の後に適応されます。
例1:
《サイバー・B・フィッシュα》の「サバイバー」能力で自分のサバイバーすべてに「ジャストダイバー」が与えられている時、自分がサバイバー・クリーチャーを出したら、出た後に「ジャストダイバー」が与えられて、トリガータイミングを失って選ばれなくなることはなく、出る前に「ジャストダイバー」が与えられ、そのクリーチャーが出て、「ジャストダイバー」の「出た時」がトリガーします。
例2:
自分の《アカシック・パラレル》が「出た時、または自分のターンのはじめに」の能力で《天災 デドダム》になっています。これが攻撃する時、「SSS級侵略[天災]」で《SSS級天災 デッドダムド》に進化させましたが、出る前に《天災 デドダム》になるため、《SSS級天災 デッドダムド》の「このクリーチャーを他のクリーチャーの上に重ねた時」の能力はトリガーしませんが、《天災 デドダム》の「出た時」の能力はトリガーします。
例3: 
自分の《魔誕の騎士ザガーン》がバトルゾーンにいる状況で、このターン、はじめて自分は手札から《死神信徒バーロウ・ビリーバー》を召喚して出し、《魔誕の騎士ザガーン》が《死神信徒バーロウ・ビリーバー》に与えた「ヨビニオン」を使いました。 同ターン中、この《死神信徒バーロウ・ビリーバー》の上に重ねて《死神明王バロム・モナーク》を進化して召喚した場合、《死神明王バロム・モナーク》はこのターンはじめて召喚して出るクリーチャーではないため、《魔誕の騎士ザガーン》が与えた「ヨビニオン」を再度使うことはできません。

バトルゾーン以外のいずれかのゾーンに複数のカードが移動する時、それらはすべて同時に移動します。これにターンプレイヤーは優先されません。
例1:
自分の墓地が7枚で、バトルゾーンには自分の闇のクリーチャーが、《凶鬼000号 ゼロヴォイド》を含む3体のみいる状況です。手札を3枚捨てると墓地が10枚になりますが、同時にクリーチャーの破壊が起こるため、《凶鬼000号 ゼロヴォイド》は問題なく離れます。
例2:
自分の《龍装艦 チェンジザ / 六奇怪の四 ～土を割る逆瀧～》が2体バトルゾーンにいる状況で、相手は《ロスト・ソウル》を唱え、自分は手札をすべて捨てました。捨てた手札の中に《フェアリー・ライフ》と《エナジー・ライト》があったのですが、これらは同時に墓地に移動しているため、両方「はじめて捨てた」カードとして扱い、1体目の《龍装艦 チェンジザ / 六奇怪の四 ～土を割る逆瀧～》の「コスト5以下の呪文を自分の手札からはじめて捨てた時」の能力で《フェアリー・ライフ》を墓地から唱え、2体目の能力で《エナジー・ライト》を墓地から唱えることができます。

バトルゾーンから墓地に移動することはすべて破壊として扱われます。そのため、進化クリーチャーの下にあるカードが墓地に置かれたとき、「カードが破壊された時」の能力がトリガーします。
 
攻撃ステップは以下のように行われます。
攻撃クリーチャー指定ステップ、ブロッククリーチャー指定ステップ、(クリーチャーを攻撃している、またはブロックなどで攻撃先がクリーチャーに変更されている場合)バトルステップ(プレイヤーを攻撃している場合)ダイレクトアタックステップ、攻撃終了ステップ
まだ攻撃できるクリーチャーがいるなら、再び攻撃クリーチャー指定ステップを行うことができます。攻撃を行わずにターン終了ステップに移行することもできます。
「攻撃中」に継続する効果は、攻撃終了ステップに消滅します。攻撃クリーチャーがバトルゾーンを離れた場合、その時点で解決中の効果と待機中の効果をすべて解決し終わった後で攻撃終了ステップへ移行します。
 
バトルゾーンにあるツインパクトクリーチャーは、カードの効果で下面の呪文を唱える時以外、クリーチャー面の情報しか持ちません。また、それは封印されている時も同様です。
例:
《神豚 ブータンPUNK / ブータン両成敗》に封印が付いている状況です。 自分が、コマンドを持つ闇のエレメントを出した場合、《神豚 ブータンPUNK / ブータン両成敗》は無色クリーチャーなので、封印を外せません。

《The邪悪 寄成ギョウ》で相手の「出た時」の能力がトリガーして、かわりに自分がそれを使うとき、それに対して他の置換効果を適用できません。

《天災 デドダム》の「出た時」のように、1枚ずつカードを移動させる効果は、《飛ベル津バサ「曲通風」》等のクリーチャーで山札の上から1枚目だけしか見ることができなかった場合、一番最初に記述されている操作を行います。

コストなどの条件を指定してカードを選び、そのカードを実行する際、「コストが○○以下のカードを選ぶ。そのカードを実行する。」のように、カードを選ぶテキストとそのカードを実行するテキストが「。」で分かれているテキストの場合、実行する際はカードの条件を問うことはしていないため、選んだカードがツインパクトなら、どちらか片方が条件に合う場合、「選ぶ」タイミングで条件に合う方を選び、「使う」や「実行する」タイミングで、好きな方を側を実行できます。この時、「選ぶ」条件に合わない方も実行できます。
「コストが○○以下のカードを実行する。」のようなテキストの効果で、ツインパクトカードの条件に合う側を選んだ場合、もう片方を選んで実行はできません。
例1:
《サイバー・I・チョイス》の「このクリーチャーが出た時、「S・トリガー」を持つカードを1枚、自分の手札から選んでもよい。選んだカードをコストを支払わずに使う。」の効果で「S・トリガー」を持つ《バーンメア・ザ・シルバー / オラオラ・スラッシュ》の呪文側を選び、クリーチャー側を実行できます。
例2:
《仙祖電融 テラスネスク》の「このクリーチャーが出た時、コスト4以下のカードを1枚、自分の手札からコストを支払わずに使ってもよい。」で《バーンメア・ザ・シルバー / オラオラ・スラッシュ》の呪文側はコスト4以下ですが、これを選んでクリーチャー側を使うことはできません。
 
総合ルールの605.2eに則り、ツインパクトクリーチャーがバトルゾーンで自身の呪文側を唱える効果がトリガーした時、その効果を処理する前に他のゾーンに移動した場合でも、呪文を唱えることができます。
 
バトルゾーンにあるカードの下、手札や山札やマナゾーン、墓地にあるツインパクトカードは、カード指定で選ばれる時、両方の特性を持ちます。ただし、出そうとしていたり、実行しようとしている場合や、どちらかの面を指定している場合、そちら側の特性のみが参照されます。

例1:
《ピクシー・ライフ》で《「色災」の頂 カラーレス・レインボー / 天上天下輪廻天頂》は手札に戻せません。

例2:
相手の《神化のカルマ シフォン》がいる時、自分の《魔導管理室 カリヤドネ / ハーミット・サークル》のコストは2多くなりません。

例3:
自分の手札が3枚の状況です。《劇場戦艦 カァテンコヲル》の「出た時」の能力で、呪文側のコストが3の《魔導管理室 カリヤドネ/ハーミット・サークル》は、呪文側は手札の枚数以下のコスト3ですがが、実行しようとしている時は水文明でないため、選択できません。

例4:
自分の進化クリーチャーの下に、ツインパクトである《奇石 ミクセル / ジャミング・チャフ》が置かれている状況で、《ルナ・デイブレイクΣ》の召喚するコストは2コスト分少なくなります。

自分の《マジック・H・コレクター / ♪マジソンを 集めて早し ストリーム》がバトルゾーンにいる状況で、手札にある「S・バック:ツインパクト」を与えられたカードを「S・バック:ツインパクト」で使う場合、クリーチャー側と呪文側どちらを使うかを決めるタイミングは、「S・バック」を宣言した時に行います。
 
自分がカードの能力で「相手がクリーチャーを選ぶ時、かわりに自分が選ぶ。」を受けている状況で、相手がエレメントを選ぶ能力を使うとき、まず相手がどのエレメントを選びたいか決め、それがクリーチャーだった場合のみ、かわりに自分が選ぶことができます。クリーチャーではなかった場合、結果的に選びたかったエレメントが選ばれます。
 
自分の「相手がクリーチャーを選ぶ時、可能ならこのクリーチャーを選ぶ。」を持つクリーチャーがいる状況で、相手がエレメントを選ぶ能力を使うとき、相手がクリーチャーではないエレメントを選びたくても、「相手がクリーチャーを選ぶ時、可能ならこのクリーチャーを選ぶ。」を持つクリーチャーを選ばなければなりません。

クリーチャーの能力を無視する効果は、すでにトリガーしているクリーチャーの効果は無視できません。

バトルに負けたクリーチャーの常在能力は、そのクリーチャーが離れる直前まで効果を発揮します。
例:
自分の、カード名として《闘門の精霊ウェルキウス》を選んでいる《神聖龍 エモーショナル・ハードコア》と、相手の《闘門の精霊ウェルキウス》と《∞龍 ゲンムエンペラー》がバトルゾーンにいる状況で、相手の《∞龍 ゲンムエンペラー》が攻撃して、自分は《神聖龍 エモーショナル・ハードコア》でブロックし、バトルの結果《神聖龍 エモーショナル・ハードコア》が破壊され時、相手の《闘門の精霊ウェルキウス》の「自分のクリーチャーがバトルに勝った時」の能力はトリガーしない。

呪文の効果が無視されている場合、その呪文の「G・ストライク」などの唱えること以外によって起こる効果も無視されます。

条件のある効果の影響に選ばれ、その処理が実行されるとき、置換効果でその条件から外れた場合でも、その影響は受けたままです。

自分が負けない効果を受けている時、相手はゲームに勝つことはできません。

「攻撃できない効果を無視する」で、「自分は攻撃されない」等の結果的に攻撃できない効果も無視できます。

置換効果は常在型能力です。なので、以下のような場合でも有効になります
例1:
《冥界の不死帝 ブルース /「迷いはない。俺の成すことは決まった」》の呪文側を手札から唱えて、墓地から《SC龍飛→SC風流》を出し、「自分がツインパクトカードの呪文側を手札から唱えた時」の能力で、唱えていた《冥界の不死帝 ブルース /「迷いはない。俺の成すことは決まった」》を墓地に置くかわりに出すことができる。
例2:
墓地から《インフェルノ・サイン》を唱え、墓地から《龍素記号wD サイクルペディア》を出した場合、唱えていた《インフェルノ・サイン》は山札の下に置かれる。

「ワンダブル」で呪文を唱えた場合、先に「ワンダブル」を持つ方から処理しなければなりません。

何かしらの効果でカードに操作を行うとき、結果的にその操作を行えないカードでも、その操作の対象として選ぶことができます。
例1:
自分の封印の付いた《Forbidden Sunrise ～禁断の夜明け～》と封印の付いた火のクリーチャー、相手の《終焉の禁断 ドルマゲドンX》がバトルゾーンにいる状況です。 自分が火のコマンドを出して封印を外す際、クリーチャーに付いた封印を外そうとして、結果的に1つも封印を墓地に置かないことができます。
例2:
自分の《堕魔 ドゥポイズ》を出したとき、自分の離れないクリーチャーを選び、破壊しようとすることができます。

総合ルール110.5aに則り、効果を誘発したクリーチャーが離れても、その効果は消失することはありません。
例:
《R.S.F.K. / オールイン・チャージャー》で攻撃する時、《轟く革命 レッドギラゾーン》の「革命チェンジ」を宣言し、「革命チェンジ」を先に解決して《轟く革命 レッドギラゾーン》と入れ替えた後で、《R.S.F.K. / オールイン・チャージャー》の「攻撃する時」の能力で「ガチンコ・ジャッジ」を行い、相手のシールドをブレイクできます。

「超魂X」を持つクリーチャーが進化した時、進化前と進化後で別の能力として扱われます。
例:
自分のターン中、《貴布人 テブルカッケ=エディ》の「自分のターンに一度、クリーチャーを１体、自分の墓地から召喚してもよい」で、その《貴布人 テブルカッケ=エディ》の上に、墓地から《至高の魂 アビスベル=ジャシン帝》を進化して召喚した場合、進化前の「超魂X」と進化後の「超魂X」は別の能力として扱われるため、同じターン中に、《至高の魂 アビスベル=ジャシン帝》が「超魂X」で得た《貴布人 テブルカッケ=エディ》の能力をもう一度使い、さらに墓地からクリーチャーを１体召喚できます。

他のカードになる効果で、選んだクリーチャーが複数セルで構成されていても、変身したカードはセルの情報は持ちません。

クリーチャー指定の能力は、ゾーンの指定が無い限り、バトルゾーンでのみ有効です。
例:
《とこしえの超人》のような単色クリーチャーの「G・ストライク」で《勝熱英雄 モモキング》を選ぶことができます。

ルビがない10(このサイトのデータベースでは読みにそのまま10と登録されているもの)は、「じゅう」と読みます。例えば、《甲殻 TS-10》の読みが「シェル ティーエス10」となっている場合、実際には「シェル ティーエスじゅう」と読むことになります。

一部の「龍解」のテキストに「アンタップする」とありますが、これは旧裁定の名残で、実際にアンタップしているわけではありません。

《DARK MATERIAL COMPLEX》のように、カードの下にカードを置く行為は、バトルゾーンにカードを出したり、置いたりする行為としては扱いません。そのため、《カレイコの黒像》や《とこしえの超人》でカードの移動が妨げられることはありません。

相手の《終納 カボード=ロウ》のような、タップしているクリーチャーのパワーをマイナスする交換が発生しているとき、自分のクリーチャーを「ハイパー化」でハイパーモードを開放するためにタップしたクリーチャーのパワーが0になったなら、ハイパーモードが解放される前に状況起因処理で破壊されます。結果的にハイパーモードは解放されますが、ハイパーモードの「クリーチャーが破壊された時」の能力はタイミングを逃しているため、トリガーしません。

《終焉の禁断 ドルマゲドンX》は《FORBIDDEN STAR ～世界最後の日～》側を裏面として扱います。

ハイパーモードが解放された《料理犬のヴィヤンドゥ》の「自分の山札の上から１枚目をマナゾーンに置く。それがドリームメイト・クリーチャーなら、出してもよい。」のように、置換効果などでカードがマナゾーンに置かれなかった場合でも、ゾーンの指定が無い場合は、参照することが可能です。

「可能なら攻撃する」能力を持つクリーチャーや、効果を受けているクリーチャーは、攻撃可能な状態である限り、何度でも攻撃をする必要があります。

「山札の上からn枚を見る」という効果は、山札がn枚未満の場合は見ることができません。また、その文章に「そうしたら」と続く場合、山札を見ることができていないため、行う事はできません。

9. 質問で聞かれていない余談や私見は語らず、文章が途中で切れないよう必ず文末まで完結させてください。`

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
