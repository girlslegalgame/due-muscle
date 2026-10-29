package controllers

import (
	"database/sql"
	"encoding/json"
	"fmt"
	"net/http"
	"time"

	models "deck-maker/app/Models"
	views "deck-maker/app/Views"
)

type NotificationController struct{}

// セッションからuser_idを取得するヘルパー関数
func getSessionUserId(r *http.Request) interface{} {
	// 既存のセッション実装に合わせて取得してください
	// 未ログイン時は nil を返します
	cookie, err := r.Cookie("user_id")
	if err != nil || cookie.Value == "" {
		return nil
	}
	return views.GetSessionUserID(r)
}

func (c *NotificationController) Index(w http.ResponseWriter, r *http.Request) {
	db, err := models.DatabaseConnect()
	if err != nil {
		http.Error(w, err.Error(), http.StatusInternalServerError)
		return
	}
	

	userId := getSessionUserId(r)

	if userId != nil {
		// 1週間以上経過した既読お知らせを自動論理削除
		stmt, err := db.Prepare(`
			UPDATE user_notification_status 
			SET is_deleted = 1 
			WHERE user_id = ? AND read_at IS NOT NULL AND read_at < DATE_SUB(NOW(), INTERVAL 1 WEEK)
		`)
		if err == nil {
			stmt.Exec(userId)
			stmt.Close()
		}
	}

	// 全体お知らせ取得
	uidParam := userId
	if uidParam == nil {
		uidParam = 0
	}

	sqlGlobal := `
		SELECT n.*, (s.read_at IS NOT NULL) as is_read
		FROM notifications n
		LEFT JOIN user_notification_status s ON n.notification_id = s.notification_id AND s.user_id = ?
		WHERE n.user_id IS NULL
		  AND (s.is_deleted IS NULL OR s.is_deleted = 0)
		ORDER BY n.created_at DESC
	`
	rowsG, _ := db.Query(sqlGlobal, uidParam)
	globalNotifications := fetchAllAssoc(rowsG)
	if rowsG != nil {
		rowsG.Close()
	}

	// 個別お知らせ取得
	userNotifications := []map[string]interface{}{}
	if userId != nil {
		sqlUser := `
			SELECT n.*, (s.read_at IS NOT NULL) as is_read
			FROM notifications n
			LEFT JOIN user_notification_status s ON n.notification_id = s.notification_id AND s.user_id = ?
			WHERE n.user_id = ?
			  AND (s.is_deleted IS NULL OR s.is_deleted = 0)
			ORDER BY n.created_at DESC
		`
		rowsU, _ := db.Query(sqlUser, userId, userId)
		userNotifications = fetchAllAssoc(rowsU)
		if rowsU != nil {
			rowsU.Close()
		}
	}

	isLoggedIn := (userId != nil && fmt.Sprintf("%v", userId) != "" && fmt.Sprintf("%v", userId) != "0")

	views.RenderView(w, r, "notification/index.html", map[string]interface{}{
		"globalNotifications": globalNotifications,
		"userNotifications":   userNotifications,
		"IsLoggedIn":          isLoggedIn,
		"UserID":              userId,
	})
}

/**
 * 既読化API
 */
func (c *NotificationController) MarkAsReadApi(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json; charset=utf-8")
	userId := getSessionUserId(r)

	if userId == nil {
		json.NewEncoder(w).Encode(map[string]interface{}{"success": true})
		return
	}

	var input map[string]interface{}
	json.NewDecoder(r.Body).Decode(&input)

	var notifId int64
	if val, ok := input["notification_id"]; ok {
		switch v := val.(type) {
		case float64:
			notifId = int64(v)
		case int:
			notifId = int64(v)
		}
	}

	if notifId == 0 {
		w.WriteHeader(http.StatusBadRequest)
		json.NewEncoder(w).Encode(map[string]string{"error": "ID不足"})
		return
	}

	db, err := models.DatabaseConnect()
	if err != nil {
		http.Error(w, err.Error(), http.StatusInternalServerError)
		return
	}
	

	stmt, err := db.Prepare(`
		INSERT INTO user_notification_status (notification_id, user_id, read_at) 
		VALUES (?, ?, NOW())
		ON DUPLICATE KEY UPDATE read_at = COALESCE(read_at, NOW())
	`)
	if err == nil {
		stmt.Exec(notifId, userId)
		stmt.Close()
	}

	json.NewEncoder(w).Encode(map[string]interface{}{"success": true})
}

/**
 * 一括削除API（既読のみ / すべて）
 */
func (c *NotificationController) DeleteNotificationsApi(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("Content-Type", "application/json; charset=utf-8")
	userId := getSessionUserId(r)

	if userId == nil {
		w.WriteHeader(http.StatusUnauthorized)
		json.NewEncoder(w).Encode(map[string]string{"error": "ログインが必要です"})
		return
	}

	var input map[string]interface{}
	json.NewDecoder(r.Body).Decode(&input)

	typeVal, _ := input["type"].(string)
	if typeVal == "" {
		typeVal = "read"
	}

	tabVal, _ := input["tab"].(string)
	if tabVal == "" {
		tabVal = "all"
	}

	db, err := models.DatabaseConnect()
	if err != nil {
		http.Error(w, err.Error(), http.StatusInternalServerError)
		return
	}
	

	// 対象のお知らせIDを抽出
	whereTab := ""
	if tabVal == "global" {
		whereTab = "AND n.user_id IS NULL"
	}
	if tabVal == "personal" {
		whereTab = fmt.Sprintf("AND n.user_id = %v", userId)
	}

	sqlQuery := fmt.Sprintf(`
		SELECT n.notification_id 
		FROM notifications n
		LEFT JOIN user_notification_status s ON n.notification_id = s.notification_id AND s.user_id = ?
		WHERE (n.user_id IS NULL OR n.user_id = ?)
		  %s
	`, whereTab)

	if typeVal == "read" {
		sqlQuery += " AND s.read_at IS NOT NULL"
	}

	rows, err := db.Query(sqlQuery, userId, userId)
	var targetIds []int64
	if err == nil && rows != nil {
		defer rows.Close()
		for rows.Next() {
			var id int64
			if err := rows.Scan(&id); err == nil {
				targetIds = append(targetIds, id)
			}
		}
	}

	if len(targetIds) > 0 {
		stmtInsert, err := db.Prepare(`
			INSERT INTO user_notification_status (notification_id, user_id, read_at, is_deleted)
			VALUES (?, ?, NOW(), 1)
			ON DUPLICATE KEY UPDATE is_deleted = 1
		`)
		if err == nil {
			defer stmtInsert.Close()
			for _, nid := range targetIds {
				stmtInsert.Exec(nid, userId)
			}
		}
	}

	json.NewEncoder(w).Encode(map[string]interface{}{"success": true})
}

// PDO::FETCH_ASSOC 互換のデータ取得ヘルパー
func fetchAllAssoc(rows *sql.Rows) []map[string]interface{} {
	var result []map[string]interface{}
	if rows == nil {
		return result
	}
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
			} else if t, ok := (*val).(time.Time); ok { // ★ここを追加
				rowMap[colName] = t.Format("2006-01-02 15:04:05")
			} else {
				rowMap[colName] = *val
			}
		}
		result = append(result, rowMap)
	}
	return result
}
