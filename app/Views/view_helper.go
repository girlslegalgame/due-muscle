package views

import (
	"bytes"
	"crypto/hmac"
	"crypto/sha256"
	"encoding/base64"
	"encoding/hex"
	"encoding/json"
	"fmt"
	"html/template"
	"net/http"
	"path/filepath"
	"strings"

	// プロジェクトのルートモジュール名に合わせて修正
	models "deck-maker/app/Models"
	"deck-maker/app/Views/layouts"
)

const sessionSecret = "dm_deck_maker_session_secret_key_2025"

var GetSessionUserID = func(r *http.Request) interface{} {
	sess := GetSession(r)
	return sess["user_id"]
}

const sessionDuration = 30 * 24 * 60 * 60 // 30日間（秒）

var GetSession = func(r *http.Request) map[string]interface{} {
	sess := make(map[string]interface{})
	if s, ok := r.Context().Value("session").(map[string]interface{}); ok && s != nil {
		return s
	}

	// 署名付きセッションCookieから復元
	if cookie, err := r.Cookie("app_session"); err == nil && cookie.Value != "" {
		parts := strings.SplitN(cookie.Value, ".", 2)
		if len(parts) == 2 {
			payloadB64, sig := parts[0], parts[1]
			h := hmac.New(sha256.New, []byte(sessionSecret))
			h.Write([]byte(payloadB64))
			expectedSig := hex.EncodeToString(h.Sum(nil))

			if hmac.Equal([]byte(sig), []byte(expectedSig)) {
				if jsonBytes, err := base64.RawURLEncoding.DecodeString(payloadB64); err == nil {
					_ = json.Unmarshal(jsonBytes, &sess)
				}
			}
		}
	}

	// 既存の user_id 単体Cookieからのフォールバック
	if sess["user_id"] == nil {
		if cookie, err := r.Cookie("user_id"); err == nil && cookie.Value != "" {
			sess["user_id"] = cookie.Value
		}
	}

	// ★ 30日アクセスの有効期限チェック
	if sess["user_id"] != nil {
		var lastActive int64
		switch v := sess["last_active"].(type) {
		case float64:
			lastActive = int64(v)
		case int64:
			lastActive = v
		}

		now := time.Now().Unix()
		// 最終アクセスから30日経過している場合はセッションを失効
		if lastActive > 0 && (now-lastActive) > sessionDuration {
			return make(map[string]interface{})
		}
	}

	return sess
}

var SaveSession = func(w http.ResponseWriter, session map[string]interface{}) {
	now := time.Now()

	// ログイン中の場合は最終アクセス日時を常に更新
	if session["user_id"] != nil {
		session["last_active"] = now.Unix()
	}

	jsonBytes, err := json.Marshal(session)
	if err == nil {
		payloadB64 := base64.RawURLEncoding.EncodeToString(jsonBytes)
		h := hmac.New(sha256.New, []byte(sessionSecret))
		h.Write([]byte(payloadB64))
		sig := hex.EncodeToString(h.Sum(nil))

		cookieVal := fmt.Sprintf("%s.%s", payloadB64, sig)

		http.SetCookie(w, &http.Cookie{
			Name:     "app_session",
			Value:    cookieVal,
			Path:     "/",
			MaxAge:   sessionDuration,
			Expires:  now.Add(30 * 24 * time.Hour),
			HttpOnly: true,
			SameSite: http.SameSiteLaxMode,
		})
	}

	// user_id Cookie も30日間有効・延長
	if uid, ok := session["user_id"]; ok && uid != nil {
		http.SetCookie(w, &http.Cookie{
			Name:     "user_id",
			Value:    fmt.Sprintf("%v", uid),
			Path:     "/",
			MaxAge:   sessionDuration,
			Expires:  now.Add(30 * 24 * time.Hour),
			HttpOnly: true,
			SameSite: http.SameSiteLaxMode,
		})
	}
}

/**
 * ビューをレンダリングするためのヘルパー関数
 *
 * @param w http.ResponseWriter
 * @param viewPath Viewsディレクトリからの相対パス
 * @param data ビューに渡すデータ（マップ）
 */
// app/Views/view_helper.go の RenderView 関数

func RenderView(w http.ResponseWriter, r *http.Request, viewPath string, data map[string]interface{}) {
	if data == nil {
		data = make(map[string]interface{})
	}

	viewFullPath := filepath.Join("app", "Views", viewPath)

	// パース対象のHTMLファイル一覧
	files := []string{
		viewFullPath,
		filepath.Join("app", "Views", "deck", "deck_item.html"),
		filepath.Join("app", "Views", "deck", "deck_detail_modal.html"),
		filepath.Join("app", "Views", "deck", "card_detail_modal.html"),
		filepath.Join("app", "Views", "deck", "analysis_modal.html"), // ★ ここを追加
	}

	tmpl, err := template.New(filepath.Base(viewFullPath)).Funcs(template.FuncMap{
		"sub": func(a, b int) int { return a - b },
		"add": func(a, b int) int { return a + b },
		"nl2br": func(text string) template.HTML {
			escaped := template.HTMLEscapeString(text)
			return template.HTML(strings.ReplaceAll(escaped, "\n", "<br>"))
		},
		"slice": func(s string, start, end int) string {
			if len(s) < end {
				return s
			}
			return s[start:end]
		},
		"dict": func(values ...interface{}) (map[string]interface{}, error) {
			if len(values)%2 != 0 {
				return nil, fmt.Errorf("invalid dict call")
			}
			res := make(map[string]interface{}, len(values)/2)
			for i := 0; i < len(values); i += 2 {
				key, ok := values[i].(string)
				if !ok {
					return nil, fmt.Errorf("dict keys must be strings")
				}
				res[key] = values[i+1]
			}
			return res, nil
		},
		"gt": func(a, b int) bool { return a > b },
		"lt": func(a, b int) bool { return a < b },
		"ge": func(a, b int) bool { return a >= b },
		"le": func(a, b int) bool { return a <= b },
		"eq": func(a, b interface{}) bool { return a == b },
	}).ParseFiles(files...)
	if err != nil {
		http.Error(w, err.Error(), http.StatusInternalServerError)
		return
	}

	var buf bytes.Buffer
	if err := tmpl.Execute(&buf, data); err != nil {
		http.Error(w, err.Error(), http.StatusInternalServerError)
		return
	}

	data["content"] = template.HTML(buf.String())

	var sessionData *layouts.SessionData
	sess := GetSession(r)
	if uid, ok := sess["user_id"]; ok && uid != nil && fmt.Sprintf("%v", uid) != "" {
		role, _ := sess["role"].(string)

		// ★ 追加: セッションに role がない場合、DBから取得する
		if role == "" {
			if db, err := models.DatabaseConnect(); err == nil {
				_ = db.QueryRow("SELECT role FROM users WHERE user_id = ?", uid).Scan(&role)
				sess["role"] = role
			}
		}

		sessionData = &layouts.SessionData{
			UserID: fmt.Sprintf("%v", uid),
			Role:   role,
		}
	}

	content, _ := data["content"].(template.HTML)
	hideFooter, _ := data["hideFooter"].(bool)

	layouts.RenderApp(w, r, sessionData, layouts.AppLayoutData{
		Content:    content,
		HideFooter: hideFooter,
	})
}

/**
 * ログインユーザーの未読お知らせ件数を取得
 */
func GetUnreadNotificationCount(r *http.Request) int {
	userIDVal := GetSessionUserID(r)
	if userIDVal == nil {
		return 0
	}

	var uid int
	switch v := userIDVal.(type) {
	case int:
		uid = v
	case int64:
		uid = int(v)
	case float64:
		uid = int(v)
	default:
		return 0
	}

	if uid == 0 {
		return 0
	}

	// Models パッケージの DatabaseConnect() に合わせる
	db, err := models.DatabaseConnect()
	if err != nil {
		return 0
	}

	updateQuery := `
		UPDATE user_notification_status 
		SET is_deleted = 1 
		WHERE user_id = ? AND read_at IS NOT NULL AND read_at < DATE_SUB(NOW(), INTERVAL 1 WEEK)
	`
	_, err = db.Exec(updateQuery, uid)
	if err != nil {
		return 0
	}

	selectQuery := `
		SELECT COUNT(*) 
		FROM notifications n
		LEFT JOIN user_notification_status s ON n.notification_id = s.notification_id AND s.user_id = ?
		WHERE (n.user_id IS NULL OR n.user_id = ?)
		  AND (s.is_deleted IS NULL OR s.is_deleted = 0)
		  AND (s.read_at IS NULL)
	`
	var count int
	err = db.QueryRow(selectQuery, uid, uid).Scan(&count)
	if err != nil {
		return 0
	}

	return count
}

var tmplFuncs = template.FuncMap{
	"dict": func(values ...interface{}) (map[string]interface{}, error) {
		if len(values)%2 != 0 {
			return nil, fmt.Errorf("invalid dict call")
		}
		dict := make(map[string]interface{}, len(values)/2)
		for i := 0; i < len(values); i += 2 {
			key, ok := values[i].(string)
			if !ok {
				return nil, fmt.Errorf("dict keys must be strings")
			}
			dict[key] = values[i+1]
		}
		return dict, nil
	},
}
