package controllers

import (
	"fmt"
	"net/http"
	"net/mail"
	"net/smtp"
	"os"
	"strings"
	"time"

	models "deck-maker/app/Models"
	views "deck-maker/app/Views"
)

type HomeController struct{}

func NewHomeController() *HomeController {
	return &HomeController{}
}

func (c *HomeController) Index(w http.ResponseWriter, r *http.Request) {
	var recentNotifs []map[string]interface{}
	if db, err := models.DatabaseConnect(); err == nil {
		rows, err := db.Query(`
			SELECT notification_id, title, created_at 
			FROM notifications 
			WHERE user_id IS NULL 
			ORDER BY created_at DESC 
			LIMIT 5
		`)
		if err == nil {
			defer rows.Close()
			for rows.Next() {
				var id int
				var title string
				var createdAt time.Time
				if err := rows.Scan(&id, &title, &createdAt); err == nil {
					recentNotifs = append(recentNotifs, map[string]interface{}{
						"id":           id,
						"title":        title,
						"formatted_at": createdAt.Format("2006/01/02"),
					})
				}
			}
		}
	}

	views.RenderView(w, r, "home/index.html", map[string]interface{}{
		"recentNotifications": recentNotifs,
	})
}

/**
 * 利用規約画面の表示
 */
func (c *HomeController) Terms(w http.ResponseWriter, r *http.Request) {
	views.RenderView(w, r, "legal/terms.go", nil)
}

/**
 * ココフォリア用汎用出力ツールの表示
 */
func (c *HomeController) Ccfolia(w http.ResponseWriter, r *http.Request) {
	views.RenderView(w, r, "tools/ccfolia.go", nil)
}

// お問い合わせ画面の表示
func (c *HomeController) Contact(w http.ResponseWriter, r *http.Request) {
	session := views.GetSession(r)
	var contactSuccess, contactError interface{}

	if val, ok := session["contact_success"]; ok {
		contactSuccess = val
	}
	if val, ok := session["contact_error"]; ok {
		contactError = val
	}

	data := map[string]interface{}{
		"description": "デュエ筋は、デュエル・マスターズのデッキ作成・マナカーブ分析・一人回し・ココフォリア出力・価格査定・AIジャッジを無料で使えるデッキ制作ウェブアプリです。",
		"success":     contactSuccess,
		"error":       contactError,
	}

	// unset($_SESSION['contact_success'], $_SESSION['contact_error']);
	delete(session, "contact_success")
	delete(session, "contact_error")
	views.SaveSession(w, session)

	views.RenderView(w, r, "home/contact.go", data)
}

// お問い合わせ送信処理
func (c *HomeController) SendContact(w http.ResponseWriter, r *http.Request) {
	if err := r.ParseForm(); err != nil {
		http.Error(w, "Bad Request", http.StatusBadRequest)
		return
	}

	email := strings.TrimSpace(r.PostFormValue("email"))
	subject := strings.TrimSpace(r.PostFormValue("subject"))
	message := strings.TrimSpace(r.PostFormValue("message"))

	session := views.GetSession(r)

	if email == "" || subject == "" || message == "" {
		session["contact_error"] = "すべての項目を入力してください。"
		views.SaveSession(w, session)
		http.Redirect(w, r, "/contact", http.StatusFound)
		return
	}

	// メールアドレスのバリデーション (FILTER_VALIDATE_EMAIL の代替)
	if _, err := mail.ParseAddress(email); err != nil {
		session["contact_error"] = "有効なメールアドレスを入力してください。"
		views.SaveSession(w, session)
		http.Redirect(w, r, "/contact", http.StatusFound)
		return
	}

	// --- 管理者宛てメール送信設定 ---
	to := os.Getenv("ADMIN_EMAIL")
	if to == "" {
		to = "default@example.com"
	}

	mailSubject := fmt.Sprintf("【デュエ筋 お問い合わせ】%s", subject)
	mailBody := fmt.Sprintf("以下の内容でお問い合わせを受け付けました。\n\n送信者: %s\n件名: %s\n\n内容:\n%s\n", email, subject, message)

	headers := fmt.Sprintf("From: %s\r\nReply-To: %s\r\nContent-Type: text/plain; charset=UTF-8\r\nSubject: %s\r\n", email, email, mailSubject)
	msg := []byte(headers + "\r\n" + mailBody)

	// mb_send_mail の代替 (ローカルSMTPサーバー経由送信)
	smtpHost := os.Getenv("SMTP_HOST")
	if smtpHost == "" {
		smtpHost = "localhost"
	}
	smtpPort := os.Getenv("SMTP_PORT")
	if smtpPort == "" {
		smtpPort = "25"
	}

	err := smtp.SendMail(smtpHost+":"+smtpPort, nil, email, []string{to}, msg)
	if err == nil {
		session["contact_success"] = "お問い合わせを送信しました。折り返しご連絡いたします。"
	} else {
		session["contact_error"] = "送信に失敗しました。時間をおいて再度お試しください。"
	}

	views.SaveSession(w, session)
	http.Redirect(w, r, "/contact", http.StatusFound)
}

// 運営者情報の表示
func (c *HomeController) Profile(w http.ResponseWriter, r *http.Request) {
	views.RenderView(w, r, "home/profile.go", nil)
}
