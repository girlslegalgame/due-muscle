package controllers

import (
	"database/sql"
	"encoding/json"
	"fmt"
	"net/http"
	"strconv"
	"strings"
	"time"

	models "deck-maker/app/Models"
	views "deck-maker/app/Views"
	adminView "deck-maker/app/Views/admin" // ★ 追加
)

type AdminController struct{}

// セッションから値を取得するヘルパー関数
func getSessionValue(r *http.Request, key string) interface{} {
	sess := views.GetSession(r)
	return sess[key]
}

func (c *AdminController) ensureAdmin(w http.ResponseWriter, r *http.Request) bool {
	userId := getSessionValue(r, "user_id")
	if userId == nil || fmt.Sprintf("%v", userId) == "" {
		http.Redirect(w, r, "/login", http.StatusFound)
		return false
	}

	role, _ := getSessionValue(r, "role").(string)

	// ★ 追加: セッションに role が無い場合、DBから取得して確認する
	if role == "" {
		if db, err := models.DatabaseConnect(); err == nil {
			_ = db.QueryRow("SELECT role FROM users WHERE user_id = ?", userId).Scan(&role)
			// セッションマップにも反映
			sess := views.GetSession(r)
			sess["role"] = role
		}
	}

	if role != "admin" {
		http.Redirect(w, r, "/mydecks", http.StatusFound)
		return false
	}
	return true
}
func (c *AdminController) Index(w http.ResponseWriter, r *http.Request) {
	if !c.ensureAdmin(w, r) {
		return
	}
	db, err := models.DatabaseConnect()
	if err != nil {
		http.Error(w, err.Error(), http.StatusInternalServerError)
		return
	}

	// 1. 通報一覧
	sqlReports := `
		SELECT 
			r.report_id, r.deck_id, r.user_id, r.reported_user_id, r.report_type, 
			r.user_report_category, r.status, r.created_at,
			d.deck_name, d.is_public,
			reporter.username as reporter_name,
			creator.username as creator_name
		FROM deck_reports r
		LEFT JOIN decks d ON r.deck_id = d.deck_id
		JOIN users creator ON r.reported_user_id = creator.user_id
		LEFT JOIN users reporter ON r.user_id = reporter.user_id
		ORDER BY (r.status = 'pending') DESC, r.created_at DESC
	`
	rowsReports, err := db.Query(sqlReports)
	if err != nil {
		http.Error(w, err.Error(), http.StatusInternalServerError)
		return
	}
	defer rowsReports.Close()

	// 1. 通報一覧
	// （※ SQLクエリはそのまま）
	var reports []adminView.Report
	for rowsReports.Next() {
		var reportID, deckID, userID, reportedUserID sql.NullInt64
		var reportType, userReportCategory, status, createdAt sql.NullString
		var deckName sql.NullString
		var isPublic sql.NullInt64
		var reporterName, creatorName sql.NullString

		_ = rowsReports.Scan(
			&reportID, &deckID, &userID, &reportedUserID, &reportType,
			&userReportCategory, &status, &createdAt,
			&deckName, &isPublic,
			&reporterName, &creatorName,
		)

		reports = append(reports, adminView.Report{
			ReportID:           int(reportID.Int64),
			ReportType:         reportType.String,
			UserReportCategory: userReportCategory.String,
			DeckID:             int(deckID.Int64),
			DeckName:           deckName.String,
			IsPublic:           isPublic.Int64 == 1,
			ReportedUserID:     int(reportedUserID.Int64),
			CreatorName:        creatorName.String,
			UserID:             int(userID.Int64),
			ReporterName:       reporterName.String,
			Reason:             "", // 必要に応じてDBから取得
			Status:             status.String,
		})
	}

	// 2. ユーザー一覧
	sqlUsers := "SELECT user_id, username, email, role, public_ban_until, report_ban_until, created_at FROM users ORDER BY user_id DESC"
	rowsUsers, err := db.Query(sqlUsers)
	if err != nil {
		http.Error(w, err.Error(), http.StatusInternalServerError)
		return
	}
	defer rowsUsers.Close()

	var users []adminView.User
	for rowsUsers.Next() {
		var userID int64
		var username, email, role, createdAt sql.NullString
		var publicBanUntil, reportBanUntil sql.NullString

		_ = rowsUsers.Scan(&userID, &username, &email, &role, &publicBanUntil, &reportBanUntil, &createdAt)
		users = append(users, adminView.User{
			UserID:         int(userID),
			Username:       username.String,
			Email:          email.String,
			Role:           role.String,
			PublicBanUntil: publicBanUntil.String,
			ReportBanUntil: reportBanUntil.String,
		})
	}

	currentSessionUserId := int(getFloat(getSessionValue(r, "user_id")))

	// ★ admin/admin.go ではなく admin/admin.html を呼び出す
	views.RenderView(w, r, "admin/admin.html", map[string]interface{}{
		"Reports":       reports,
		"Users":         users,
		"SessionUserID": currentSessionUserId,
	})
}

/**
 * 特定ユーザーのデッキ一覧取得API
 */
func (c *AdminController) UserDecksApi(w http.ResponseWriter, r *http.Request) {
	if !c.ensureAdmin(w, r) {
		return
	}
	w.Header().Set("Content-Type", "application/json; charset=utf-8")

	userId, _ := strconv.Atoi(r.URL.Query().Get("user_id"))

	db, err := models.DatabaseConnect()
	if err != nil {
		http.Error(w, err.Error(), http.StatusInternalServerError)
		return
	}
	rows, err := db.Query("SELECT deck_id, deck_name, is_public, updated_at FROM decks WHERE user_id = ? ORDER BY updated_at DESC", userId)
	if err != nil {
		http.Error(w, err.Error(), http.StatusInternalServerError)
		return
	}
	defer rows.Close()

	var decks []map[string]interface{}
	for rows.Next() {
		var deckID, isPublic int64
		var deckName, updatedAt string
		_ = rows.Scan(&deckID, &deckName, &isPublic, &updatedAt)
		decks = append(decks, map[string]interface{}{
			"deck_id":    deckID,
			"deck_name":  deckName,
			"is_public":  isPublic,
			"updated_at": updatedAt,
		})
	}

	json.NewEncoder(w).Encode(decks)
}

/**
 * ペナルティ操作API (付与 / 解除)
 */
func (c *AdminController) ApplyPenaltyApi(w http.ResponseWriter, r *http.Request) {
	if !c.ensureAdmin(w, r) {
		return
	}
	w.Header().Set("Content-Type", "application/json; charset=utf-8")

	var input map[string]interface{}
	json.NewDecoder(r.Body).Decode(&input)

	userId := int(getFloat(input["user_id"]))
	deckId := int(getFloat(input["deck_id"]))
	banType, _ := input["type"].(string)
	action, _ := input["action"].(string)
	reason, _ := input["reason"].(string)
	reason = strings.TrimSpace(reason)
	if reason == "" {
		reason = "利用規約違反のため"
	}

	if userId == 0 || (banType != "public_ban" && banType != "report_ban") {
		w.WriteHeader(http.StatusBadRequest)
		json.NewEncoder(w).Encode(map[string]string{"error": "不正なパラメータです"})
		return
	}

	col := "report_ban_until"
	if banType == "public_ban" {
		col = "public_ban_until"
	}

	var val *string
	if action == "apply_1week" {
		t := time.Now().AddDate(0, 0, 7).Format("2006-01-02 15:04:05")
		val = &t
	}

	db, err := models.DatabaseConnect()
	if err != nil {
		http.Error(w, err.Error(), http.StatusInternalServerError)
		return
	}
	query := fmt.Sprintf("UPDATE users SET %s = ? WHERE user_id = ?", col)
	_, err = db.Exec(query, val, userId)
	if err != nil {
		w.WriteHeader(http.StatusInternalServerError)
		json.NewEncoder(w).Encode(map[string]string{"error": err.Error()})
		return
	}

	// ペナルティ付与時の自動通知
	if action == "apply_1week" {
		expireFormat := time.Now().AddDate(0, 0, 7).Format("2006年01月02日 15:04")
		if banType == "public_ban" {
			deckName := "対象のデッキ"
			if deckId != 0 {
				var dName string
				err := db.QueryRow("SELECT deck_name FROM decks WHERE deck_id = ?", deckId).Scan(&dName)
				if err == nil {
					deckName = dName
				}
			}
			msg := fmt.Sprintf("デッキ「%s」における規約違反に伴い、デッキを非公開としたうえで【1週間のデッキ公開禁止ペナルティ】を適用いたしました。\n\n理由: %s\n期間: %s まで", deckName, reason, expireFormat)
			c.sendNotification(db, userId, "【重要】デッキ公開制限ペナルティのお知らせ", msg, nil, nil)
		} else if banType == "report_ban" {
			msg := fmt.Sprintf("度重なる過度な通報や不正な通報利用が確認されたため、【1週間の通報機能利用禁止ペナルティ】を適用いたしました。\n\n理由: %s\n期間: %s まで", reason, expireFormat)
			c.sendNotification(db, userId, "【重要】通報機能利用制限のお知らせ", msg, nil, nil)
		}
	}

	json.NewEncoder(w).Encode(map[string]interface{}{"success": true, "new_value": val})
}

/**
 * ユーザー情報更新API (ユーザー名変更)
 */
func (c *AdminController) UpdateUserApi(w http.ResponseWriter, r *http.Request) {
	if !c.ensureAdmin(w, r) {
		return
	}
	w.Header().Set("Content-Type", "application/json; charset=utf-8")

	var input map[string]interface{}
	json.NewDecoder(r.Body).Decode(&input)

	userId := int(getFloat(input["user_id"]))
	username, _ := input["username"].(string)
	username = strings.TrimSpace(username)

	if userId == 0 || username == "" {
		w.WriteHeader(http.StatusBadRequest)
		json.NewEncoder(w).Encode(map[string]string{"error": "ユーザー名を入力してください"})
		return
	}

	db, err := models.DatabaseConnect()
	if err != nil {
		http.Error(w, err.Error(), http.StatusInternalServerError)
		return
	}
	_, err = db.Exec("UPDATE users SET username = ?, updated_at = NOW() WHERE user_id = ?", username, userId)
	if err != nil {
		w.WriteHeader(http.StatusInternalServerError)
		json.NewEncoder(w).Encode(map[string]string{"error": err.Error()})
		return
	}

	json.NewEncoder(w).Encode(map[string]bool{"success": true})
}

/**
 * 通報の処理API
 */
func (c *AdminController) HandleReportApi(w http.ResponseWriter, r *http.Request) {
	if !c.ensureAdmin(w, r) {
		return
	}
	w.Header().Set("Content-Type", "application/json; charset=utf-8")

	var input map[string]interface{}
	json.NewDecoder(r.Body).Decode(&input)

	reportId := int(getFloat(input["report_id"]))
	deckId := int(getFloat(input["deck_id"]))
	action, _ := input["action"].(string)
	reason, _ := input["reason"].(string)
	reason = strings.TrimSpace(reason)
	if reason == "" {
		reason = "利用規約違反が確認されたため"
	}

	db, err := models.DatabaseConnect()
	if err != nil {
		http.Error(w, err.Error(), http.StatusInternalServerError)
		return
	}

	// 対象通報とデッキ・ユーザー情報の取得
	stmtInfo := `
		SELECT r.user_id, r.report_type, d.deck_name, d.user_id as creator_id, u.username as creator_name
		FROM deck_reports r
		JOIN decks d ON r.deck_id = d.deck_id
		JOIN users u ON d.user_id = u.user_id
		WHERE r.report_id = ?
	`
	var repUserId sql.NullInt64
	var repType, deckName, creatorName sql.NullString
	var creatorId sql.NullInt64

	row := db.QueryRow(stmtInfo, reportId)
	hasReport := row.Scan(&repUserId, &repType, &deckName, &creatorId, &creatorName) == nil

	if action == "make_private" {
		// デッキ非公開化
		db.Exec("UPDATE decks SET is_public = 0 WHERE deck_id = ?", deckId)
		if reportId != 0 {
			db.Exec("UPDATE deck_reports SET status = 'resolved' WHERE report_id = ?", reportId)
		}

		cId := int(creatorId.Int64)
		dName := deckName.String
		if !hasReport {
			_ = db.QueryRow("SELECT user_id FROM decks WHERE deck_id = ?", deckId).Scan(&cId)
			_ = db.QueryRow("SELECT deck_name FROM decks WHERE deck_id = ?", deckId).Scan(&dName)
		}

		msg := fmt.Sprintf("作成されたデッキ「%s」は、以下の理由により非公開に設定されました。\n\n理由: %s\n\n内容をご確認・修正のうえ、再度公開設定を行ってください。", dName, reason)
		url := fmt.Sprintf("/decks/edit?deck_id=%d", deckId)
		label := "デッキを編集する"
		c.sendNotification(db, cId, "【重要】デッキ非公開のお知らせ", msg, &url, &label)

	} else if action == "dismiss" {
		// 通報却下
		db.Exec("UPDATE deck_reports SET status = 'dismissed' WHERE report_id = ?", reportId)

		// 通報者へ通知
		if hasReport && repUserId.Valid {
			targetName := fmt.Sprintf("デッキ「%s」", deckName.String)
			if repType.String == "user" {
				targetName = fmt.Sprintf("ユーザー「%s」", creatorName.String)
			}
			msg := fmt.Sprintf("ご報告いただきました%sにつきまして、確認を行いましたが問題は見られなかったため、対応を見送らせていただきました。\nご協力ありがとうございました。", targetName)
			c.sendNotification(db, int(repUserId.Int64), "通報いただいた件についてのご案内", msg, nil, nil)
		}
	}

	json.NewEncoder(w).Encode(map[string]bool{"success": true})
}

// --- AdminController 内へ追加する共通通知関数 ---
func (c *AdminController) sendNotification(db *sql.DB, userId interface{}, title, message string, actionUrl, actionLabel *string) {
	stmt := `
		INSERT INTO notifications (user_id, title, message, action_url, action_label, created_at) 
		VALUES (?, ?, ?, ?, ?, NOW())
	`
	db.Exec(stmt, userId, title, message, actionUrl, actionLabel)
}

func (c *AdminController) sendNotificationTx(tx *sql.Tx, userId interface{}, title, message string, actionUrl, actionLabel *string) {
	stmt := `
		INSERT INTO notifications (user_id, title, message, action_url, action_label, created_at) 
		VALUES (?, ?, ?, ?, ?, NOW())
	`
	tx.Exec(stmt, userId, title, message, actionUrl, actionLabel)
}

/**
 * お知らせ手動作成API
 */
func (c *AdminController) SendNotificationApi(w http.ResponseWriter, r *http.Request) {
	if !c.ensureAdmin(w, r) {
		return
	}
	w.Header().Set("Content-Type", "application/json; charset=utf-8")

	var input map[string]interface{}
	json.NewDecoder(r.Body).Decode(&input)

	target, _ := input["target"].(string)
	if target == "" {
		target = "all"
	}
	var targetUserId interface{} = nil
	if target == "user" && input["user_id"] != nil {
		targetUserId = int(getFloat(input["user_id"]))
	}

	title, _ := input["title"].(string)
	title = strings.TrimSpace(title)
	message, _ := input["message"].(string)
	message = strings.TrimSpace(message)

	var url *string
	if u, ok := input["action_url"].(string); ok && strings.TrimSpace(u) != "" {
		trimmed := strings.TrimSpace(u)
		url = &trimmed
	}

	var label *string
	if l, ok := input["action_label"].(string); ok && strings.TrimSpace(l) != "" {
		trimmed := strings.TrimSpace(l)
		label = &trimmed
	}

	if title == "" || message == "" {
		w.WriteHeader(http.StatusBadRequest)
		json.NewEncoder(w).Encode(map[string]string{"error": "タイトルと本文は必須です"})
		return
	}

	db, err := models.DatabaseConnect()
	if err != nil {
		http.Error(w, err.Error(), http.StatusInternalServerError)
		return
	}
	c.sendNotification(db, targetUserId, title, message, url, label)
	json.NewEncoder(w).Encode(map[string]bool{"success": true})
}

/**
 * 複数デッキの一括非公開化＆通知API
 */
func (c *AdminController) BulkMakeDecksPrivateApi(w http.ResponseWriter, r *http.Request) {
	if !c.ensureAdmin(w, r) {
		return
	}
	w.Header().Set("Content-Type", "application/json; charset=utf-8")

	var input map[string]interface{}
	json.NewDecoder(r.Body).Decode(&input)

	rawDeckIds, _ := input["deck_ids"].([]interface{})
	var deckIds []interface{}
	for _, id := range rawDeckIds {
		deckIds = append(deckIds, int(getFloat(id)))
	}

	userId := int(getFloat(input["user_id"]))
	reason, _ := input["reason"].(string)
	reason = strings.TrimSpace(reason)
	if reason == "" {
		reason = "利用規約違反が確認されたため"
	}

	applyPublicBan, _ := input["apply_public_ban"].(bool)
	reportId := int(getFloat(input["report_id"]))

	if len(deckIds) == 0 || userId == 0 {
		w.WriteHeader(http.StatusBadRequest)
		json.NewEncoder(w).Encode(map[string]string{"error": "対象デッキが選択されていません"})
		return
	}

	db, err := models.DatabaseConnect()
	if err != nil {
		http.Error(w, err.Error(), http.StatusInternalServerError)
		return
	}
	tx, err := db.Begin()
	if err != nil {
		w.WriteHeader(http.StatusInternalServerError)
		json.NewEncoder(w).Encode(map[string]string{"error": err.Error()})
		return
	}

	placeholders := make([]string, len(deckIds))
	for i := range deckIds {
		placeholders[i] = "?"
	}
	inClause := strings.Join(placeholders, ",")

	// 対象デッキ名を取得
	rowsNames, err := tx.Query(fmt.Sprintf("SELECT deck_name FROM decks WHERE deck_id IN (%s)", inClause), deckIds...)
	if err != nil {
		tx.Rollback()
		w.WriteHeader(http.StatusInternalServerError)
		json.NewEncoder(w).Encode(map[string]string{"error": err.Error()})
		return
	}
	var deckNames []string
	for rowsNames.Next() {
		var name string
		_ = rowsNames.Scan(&name)
		deckNames = append(deckNames, name)
	}
	rowsNames.Close()

	// 一括非公開
	_, err = tx.Exec(fmt.Sprintf("UPDATE decks SET is_public = 0 WHERE deck_id IN (%s)", inClause), deckIds...)
	if err != nil {
		tx.Rollback()
		w.WriteHeader(http.StatusInternalServerError)
		json.NewEncoder(w).Encode(map[string]string{"error": err.Error()})
		return
	}

	// 1週間公開禁止ペナルティの付与
	if applyPublicBan {
		_, err = tx.Exec("UPDATE users SET public_ban_until = DATE_ADD(NOW(), INTERVAL 1 WEEK) WHERE user_id = ?", userId)
		if err != nil {
			tx.Rollback()
			w.WriteHeader(http.StatusInternalServerError)
			json.NewEncoder(w).Encode(map[string]string{"error": err.Error()})
			return
		}
	}

	// 指定通報の解決
	if reportId != 0 {
		_, err = tx.Exec("UPDATE deck_reports SET status = 'resolved' WHERE report_id = ?", reportId)
		if err != nil {
			tx.Rollback()
			w.WriteHeader(http.StatusInternalServerError)
			json.NewEncoder(w).Encode(map[string]string{"error": err.Error()})
			return
		}

		// 通報者へ通知
		var repUserId sql.NullInt64
		var creatorName string
		err = tx.QueryRow("SELECT r.user_id, creator.username as creator_name FROM deck_reports r JOIN users creator ON r.reported_user_id = creator.user_id WHERE r.report_id = ?", reportId).Scan(&repUserId, &creatorName)
		if err == nil && repUserId.Valid {
			c.sendNotificationTx(tx, int(repUserId.Int64), "通報いただいた件についてのご案内", fmt.Sprintf("ご報告いただいたユーザー「%s」の連投につきまして、確認の上、デッキ非公開および公開制限の処置を行いました。ご協力ありがとうございました。", creatorName), nil, nil)
		}
	}

	// 被通報者へのメッセージ構築
	count := len(deckNames)
	var deckListLines []string
	for _, n := range deckNames {
		deckListLines = append(deckListLines, "・"+n)
	}
	deckListStr := strings.Join(deckListLines, "\n")

	banNotice := ""
	if applyPublicBan {
		banNotice = fmt.Sprintf("\n\nまた、度重なる連投・規約違反に伴い、【1週間のデッキ公開禁止ペナルティ】を適用いたしました。\n期間: %s まで", time.Now().AddDate(0, 0, 7).Format("2006年01月02日 15:04"))
	}

	var title, msg string
	if count == 1 {
		title = "【重要】デッキ非公開のお知らせ"
		msg = fmt.Sprintf("作成されたデッキ「%s」は、以下の理由により非公開に設定されました。\n\n理由: %s%s\n\nマイデッキより内容をご確認ください。", deckNames[0], reason, banNotice)
	} else {
		title = fmt.Sprintf("【重要】複数のデッキ非公開のお知らせ (%d件)", count)
		msg = fmt.Sprintf("作成された以下のデッキ（%d件）は、規約違反等の理由により非公開に設定されました。\n\n【対象デッキ】\n%s\n\n理由: %s%s\n\nマイデッキより内容をご確認ください。", count, deckListStr, reason, banNotice)
	}

	url := "/mydecks"
	label := "マイデッキ一覧へ"
	c.sendNotificationTx(tx, userId, title, msg, &url, &label)

	if err := tx.Commit(); err != nil {
		w.WriteHeader(http.StatusInternalServerError)
		json.NewEncoder(w).Encode(map[string]string{"error": err.Error()})
		return
	}

	json.NewEncoder(w).Encode(map[string]bool{"success": true})
}

/**
 * 連投・不適切ユーザー名通報の専用処置＆通知API
 */
func (c *AdminController) ResolveUserReportApi(w http.ResponseWriter, r *http.Request) {
	if !c.ensureAdmin(w, r) {
		return
	}
	w.Header().Set("Content-Type", "application/json; charset=utf-8")

	var input map[string]interface{}
	json.NewDecoder(r.Body).Decode(&input)

	reportId := int(getFloat(input["report_id"]))
	action, _ := input["action"].(string)

	db, err := models.DatabaseConnect()
	if err != nil {
		http.Error(w, err.Error(), http.StatusInternalServerError)
		return
	}
	tx, err := db.Begin()
	if err != nil {
		w.WriteHeader(http.StatusInternalServerError)
		json.NewEncoder(w).Encode(map[string]string{"error": err.Error()})
		return
	}

	stmt := `
		SELECT r.reported_user_id, r.user_id, r.user_report_category, creator.username as creator_name
		FROM deck_reports r
		LEFT JOIN decks d ON r.deck_id = d.deck_id
		JOIN users creator ON r.reported_user_id = creator.user_id
		WHERE r.report_id = ?
	`
	var reportedUserId int
	var repUserId sql.NullInt64
	var userReportCategory, creatorName string

	err = tx.QueryRow(stmt, reportId).Scan(&reportedUserId, &repUserId, &userReportCategory, &creatorName)
	if err != nil {
		tx.Rollback()
		w.WriteHeader(http.StatusInternalServerError)
		json.NewEncoder(w).Encode(map[string]string{"error": "通報情報が見つかりません"})
		return
	}

	if action == "spam_penalty" {
		// 1. 作成者の公開中デッキを全非公開 ＋ 1週間公開禁止
		tx.Exec("UPDATE decks SET is_public = 0 WHERE user_id = ?", reportedUserId)
		tx.Exec("UPDATE users SET public_ban_until = DATE_ADD(NOW(), INTERVAL 1 WEEK) WHERE user_id = ?", reportedUserId)
		tx.Exec("UPDATE deck_reports SET status = 'resolved' WHERE report_id = ?", reportId)

		// 被通報者へ通知
		c.sendNotificationTx(tx, reportedUserId, "【重要】デッキ連投に伴う公開制限のお知らせ",
			fmt.Sprintf("デッキの連投・スパム行為が確認されたため、公開中のデッキを非公開に設定し、【1週間のデッキ公開禁止ペナルティ】を適用いたしました。\n期間: %s まで", time.Now().AddDate(0, 0, 7).Format("2006年01月02日 15:04")), nil, nil)

		// 通報者へ通知
		if repUserId.Valid {
			c.sendNotificationTx(tx, int(repUserId.Int64), "通報いただいた件についてのご案内",
				fmt.Sprintf("ご報告いただいたユーザー「%s」の連投につきまして、確認の上、デッキの非公開および公開制限の処置を行いました。ご協力ありがとうございました。", creatorName), nil, nil)
		}

	} else if action == "reset_name" {
		// 2. ユーザー名を「ユーザー」に変更
		tx.Exec("UPDATE users SET username = 'ユーザー', updated_at = NOW() WHERE user_id = ?", reportedUserId)
		tx.Exec("UPDATE deck_reports SET status = 'resolved' WHERE report_id = ?", reportId)

		// 被通報者へ通知
		c.sendNotificationTx(tx, reportedUserId, "【重要】ユーザー名変更のお知らせ", "不適切なユーザー名が確認されたため、ユーザー名を「ユーザー」に変更いたしました。アカウント設定より適切な名前に変更してください。", nil, nil)

		// 通報者へ通知
		if repUserId.Valid {
			c.sendNotificationTx(tx, int(repUserId.Int64), "通報いただいた件についてのご案内", "ご報告いただいたユーザー名につきまして、確認の上、変更措置を行いました。ご協力ありがとうございました。", nil, nil)
		}

	} else if action == "dismiss" {
		// 3. 通報却下
		tx.Exec("UPDATE deck_reports SET status = 'dismissed' WHERE report_id = ?", reportId)

		if repUserId.Valid {
			targetText := "デッキ・行動"
			if userReportCategory == "inappropriate_name" {
				targetText = "ユーザー名"
			}
			c.sendNotificationTx(tx, int(repUserId.Int64), "通報いただいた件についてのご案内",
				fmt.Sprintf("ご報告いただいた%sにつきまして、確認を行いましたが問題は見られなかったため、対応を見送らせていただきました。", targetText), nil, nil)
		}

	} else if action == "penalize_reporter" {
		// 4. 通報者へ1週間通報禁止ペナルティ
		if repUserId.Valid {
			tx.Exec("UPDATE users SET report_ban_until = DATE_ADD(NOW(), INTERVAL 1 WEEK) WHERE user_id = ?", repUserId.Int64)
			c.sendNotificationTx(tx, int(repUserId.Int64), "【重要】通報機能利用制限のお知らせ",
				fmt.Sprintf("過度・不適切な通報が確認されたため、【1週間の通報機能利用制限ペナルティ】を適用いたしました。\n期間: %s まで", time.Now().AddDate(0, 0, 7).Format("2006年01月02日 15:04")), nil, nil)
		}
		tx.Exec("UPDATE deck_reports SET status = 'dismissed' WHERE report_id = ?", reportId)
	}

	if err := tx.Commit(); err != nil {
		w.WriteHeader(http.StatusInternalServerError)
		json.NewEncoder(w).Encode(map[string]string{"error": err.Error()})
		return
	}

	json.NewEncoder(w).Encode(map[string]bool{"success": true})
}

/**
 * ロール変更API（admin以外のユーザーのみ変更可）
 */
func (c *AdminController) UpdateUserRoleApi(w http.ResponseWriter, r *http.Request) {
	if !c.ensureAdmin(w, r) {
		return
	}
	w.Header().Set("Content-Type", "application/json; charset=utf-8")

	var input map[string]interface{}
	json.NewDecoder(r.Body).Decode(&input)

	userId := int(getFloat(input["user_id"]))
	newRole, _ := input["role"].(string)
	newRole = strings.TrimSpace(newRole)

	// 許可するロール
	if userId == 0 || (newRole != "user" && newRole != "developer") {
		w.WriteHeader(http.StatusBadRequest)
		json.NewEncoder(w).Encode(map[string]string{"error": "無効な指定です"})
		return
	}

	db, err := models.DatabaseConnect()
	if err != nil {
		http.Error(w, err.Error(), http.StatusInternalServerError)
		return
	}

	// 対象がadmin、または自分自身でないか確認
	var role string
	err = db.QueryRow("SELECT role FROM users WHERE user_id = ?", userId).Scan(&role)
	currentSessionUserId := int(getFloat(getSessionValue(r, "user_id")))

	if err != nil || role == "admin" || userId == currentSessionUserId {
		w.WriteHeader(http.StatusForbidden)
		json.NewEncoder(w).Encode(map[string]string{"error": "admin権限を持つユーザーのロールは変更できません"})
		return
	}

	_, err = db.Exec("UPDATE users SET role = ?, updated_at = NOW() WHERE user_id = ?", newRole, userId)
	if err != nil {
		w.WriteHeader(http.StatusInternalServerError)
		json.NewEncoder(w).Encode(map[string]string{"error": err.Error()})
		return
	}

	json.NewEncoder(w).Encode(map[string]bool{"success": true})
}

/**
 * ユーザー削除API（adminは削除不可）
 */
func (c *AdminController) DeleteUserApi(w http.ResponseWriter, r *http.Request) {
	if !c.ensureAdmin(w, r) {
		return
	}
	w.Header().Set("Content-Type", "application/json; charset=utf-8")

	var input map[string]interface{}
	json.NewDecoder(r.Body).Decode(&input)

	userId := int(getFloat(input["user_id"]))
	if userId == 0 {
		w.WriteHeader(http.StatusBadRequest)
		json.NewEncoder(w).Encode(map[string]string{"error": "ユーザーID不足"})
		return
	}

	db, err := models.DatabaseConnect()
	if err != nil {
		http.Error(w, err.Error(), http.StatusInternalServerError)
		return
	}
	var role string
	// 修正: := から = に変更
	err = db.QueryRow("SELECT role FROM users WHERE user_id = ?", userId).Scan(&role)
	currentSessionUserId := int(getFloat(getSessionValue(r, "user_id")))

	if err != nil || role == "admin" || userId == currentSessionUserId {
		w.WriteHeader(http.StatusForbidden)
		json.NewEncoder(w).Encode(map[string]string{"error": "管理者アカウントは削除できません"})
		return
	}

	_, err = db.Exec("DELETE FROM users WHERE user_id = ?", userId)
	if err != nil {
		w.WriteHeader(http.StatusInternalServerError)
		json.NewEncoder(w).Encode(map[string]string{"error": err.Error()})
		return
	}

	json.NewEncoder(w).Encode(map[string]bool{"success": true})
}

/**
 * 処理済み（対応済・却下）の通報を一括削除するAPI
 */
func (c *AdminController) CleanReportsApi(w http.ResponseWriter, r *http.Request) {
	if !c.ensureAdmin(w, r) {
		return
	}
	w.Header().Set("Content-Type", "application/json; charset=utf-8")

	db, err := models.DatabaseConnect()
	if err != nil {
		http.Error(w, err.Error(), http.StatusInternalServerError)
		return
	}
	res, err := db.Exec("DELETE FROM deck_reports WHERE status IN ('resolved', 'dismissed')")
	if err != nil {
		w.WriteHeader(http.StatusInternalServerError)
		json.NewEncoder(w).Encode(map[string]string{"error": err.Error()})
		return
	}

	count, _ := res.RowsAffected()
	json.NewEncoder(w).Encode(map[string]interface{}{
		"success":       true,
		"deleted_count": count,
	})
}

// 数値インターフェース補助関数
func getFloat(val interface{}) float64 {
	switch v := val.(type) {
	case float64:
		return v
	case int:
		return float64(v)
	case int64:
		return float64(v)
	case string:
		f, _ := strconv.ParseFloat(v, 64)
		return f
	default:
		return 0
	}
}
