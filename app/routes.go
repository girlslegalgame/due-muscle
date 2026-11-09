package app

import (
	"bufio"
	"bytes"
	"encoding/json"
	"fmt"
	"io"
	"log"
	"net/http"
	"os"
	"path/filepath"
	"strings"
	"time"

	controllers "deck-maker/app/Controllers"
	models "deck-maker/app/Models" // ★ この行を追加
	views "deck-maker/app/Views"
	deckViews "deck-maker/app/Views/deck"

	"github.com/go-chi/chi/v5"
)

func RegisterRoutes(r chi.Router) {
	cardCtrl := &controllers.CardController{}
	deckCtrl := &controllers.DeckController{}
	authCtrl := &controllers.AuthController{}
	homeCtrl := controllers.NewHomeController()
	adminCtrl := &controllers.AdminController{}
	ruleBotCtrl := &controllers.RuleBotController{}
	notifCtrl := &controllers.NotificationController{}

	// トップページ
	r.Get("/", homeCtrl.Index)

	r.Get("/ads.txt", func(w http.ResponseWriter, r *http.Request) {
		http.ServeFile(w, r, "public/ads.txt")
	})
	// ★ 追加: ヘルプページ
	r.Get("/help", func(w http.ResponseWriter, r *http.Request) {
		views.RenderView(w, r, "help/index.html", map[string]interface{}{})
	})
	r.Get("/help/search", deckViews.HelpSearchHandler)
	r.Get("/tools/ccfolia", func(w http.ResponseWriter, r *http.Request) {
		views.RenderView(w, r, "tools/ccfolia.html", map[string]interface{}{})
	})
	r.Get("/terms", func(w http.ResponseWriter, r *http.Request) {
		views.RenderView(w, r, "legal/terms.html", map[string]interface{}{})
	})

	// マイデッキ一覧などの既存ルート
	r.Get("/mydecks", deckCtrl.MyDecks)
	r.Get("/search", deckCtrl.Search)
	r.Get("/decks/new", deckCtrl.Create)
	r.Get("/decks/edit", deckCtrl.Edit)
	r.Get("/decks/playtest", deckCtrl.Playtest)
	r.Get("/decks/share", deckCtrl.Share)
	r.Get("/users/decks", deckCtrl.UserPublicDecks)
	// AIジャッジ専用ページ
	r.Get("/rule-bot", ruleBotCtrl.Index)
	r.Get("/notifications", notifCtrl.Index)
	r.Get("/profile", func(w http.ResponseWriter, r *http.Request) {
		views.RenderView(w, r, "home/profile.html", map[string]interface{}{})
	})
	// お問い合わせ画面（GET）
	r.Get("/contact", func(w http.ResponseWriter, r *http.Request) {
		sess := views.GetSession(r)
		views.RenderView(w, r, "home/contact.html", map[string]interface{}{
			"IsLoggedIn": sess["user_id"] != nil,
		})
	})

	// ★ 追加: サイト内お問い合わせ送信処理（POST /contact/site）
	r.Post("/contact/site", func(w http.ResponseWriter, r *http.Request) {
		if err := r.ParseForm(); err != nil {
			views.RenderView(w, r, "home/contact.html", map[string]interface{}{"error": "データ解析失敗"})
			return
		}
		subject := strings.TrimSpace(r.FormValue("subject"))
		message := strings.TrimSpace(r.FormValue("message"))
		guestName := strings.TrimSpace(r.FormValue("guest_name"))

		if subject == "" || message == "" {
			views.RenderView(w, r, "home/contact.html", map[string]interface{}{"error": "件名と内容は必須です。"})
			return
		}

		sess := views.GetSession(r)
		var userId interface{} = nil
		if sess["user_id"] != nil {
			userId = sess["user_id"]
		} else if guestName == "" {
			guestName = "ゲスト"
		}

		db, err := models.DatabaseConnect()
		if err != nil {
			views.RenderView(w, r, "home/contact.html", map[string]interface{}{"error": "DB接続エラー"})
			return
		}

		_, err = db.Exec("INSERT INTO inquiries (user_id, guest_name, subject, message) VALUES (?, ?, ?, ?)", userId, guestName, subject, message)
		if err != nil {
			views.RenderView(w, r, "home/contact.html", map[string]interface{}{"error": "送信に失敗しました。"})
			return
		}

		views.RenderView(w, r, "home/contact.html", map[string]interface{}{
			"success":    "お問い合わせをサイト内送信で受け付けました。返信はお知らせ通知またはサイト内にて行われます。",
			"IsLoggedIn": sess["user_id"] != nil,
		})
	})
	// お問い合わせ送信処理
	r.Post("/contact", func(w http.ResponseWriter, r *http.Request) {
		if err := r.ParseForm(); err != nil {
			views.RenderView(w, r, "home/contact.html", map[string]interface{}{
				"error": "送信データの解析に失敗しました。",
			})
			return
		}

		email := strings.TrimSpace(r.FormValue("email"))
		subject := strings.TrimSpace(r.FormValue("subject"))
		message := strings.TrimSpace(r.FormValue("message"))

		if email == "" || subject == "" || message == "" {
			views.RenderView(w, r, "home/contact.html", map[string]interface{}{
				"error": "すべての項目を入力してください。",
			})
			return
		}

		// 管理者宛てにメール送信
		if err := sendContactEmail(email, subject, message); err != nil {
			log.Printf("[Contact Error] メール送信失敗: %v", err)
			views.RenderView(w, r, "home/contact.html", map[string]interface{}{
				"error": "メール送信中にエラーが発生しました。時間をおいて再送信してください。",
			})
			return
		}

		views.RenderView(w, r, "home/contact.html", map[string]interface{}{
			"success": "お問い合わせを受け付けました。ご入力いただいた内容を確認の上、必要に応じて返信いたします。",
		})
	})
	// API関連
	r.Route("/api", func(r chi.Router) {
		r.Get("/cards", cardCtrl.CardsApi)
		r.Get("/cards/combination", cardCtrl.CardCombinationApi)
		r.Get("/cards/versions", cardCtrl.CardVersionsApi)
		r.Get("/cards/help-search", cardCtrl.HelpSearchApi)
		r.Get("/cards/help-detail", cardCtrl.HelpDetailApi) // ★ 追加
		r.Post("/cards/help-update", cardCtrl.HelpUpdateApi)
		r.Get("/master-data", cardCtrl.MasterDataApi)
		r.Get("/master-data-extended", cardCtrl.MasterDataExtendedApi)
		r.Post("/notifications/read", notifCtrl.MarkAsReadApi)
		r.Post("/notifications/delete", notifCtrl.DeleteNotificationsApi)
		// デッキ関連API
		r.Route("/decks", func(r chi.Router) {
			r.Post("/", deckCtrl.StoreDeckApi)
			r.Put("/", deckCtrl.UpdateDeckApi)
			r.Delete("/", deckCtrl.DeleteDeckApi)
			r.Get("/view", deckCtrl.ViewDeckApi)
			r.Post("/copy", deckCtrl.CopyDeckApi)
			r.Put("/set_public", deckCtrl.SetDeckPublicApi)
			r.Post("/report", deckCtrl.ReportDeckApi)
			r.Get("/estimate-price", deckCtrl.EstimatePriceApi)
			// ★ /api/decks/owned-cards
			r.Get("/owned-cards", deckCtrl.GetOwnedCardsApi)
			r.Post("/owned-cards", deckCtrl.SaveOwnedCardsApi)
		})
		// API関連内（r.Route("/api", ...) の中）
		r.Post("/bot/chat", ruleBotCtrl.ChatApi)
	})

	r.Get("/api/decks/owned-cards", deckCtrl.GetOwnedCardsApi)
	r.Post("/api/decks/owned-cards", deckCtrl.SaveOwnedCardsApi)

	r.Get("/account", authCtrl.ShowAccountForm)
	r.Post("/account", authCtrl.UpdateAccount)
	// 認証関連
	r.Get("/login", func(w http.ResponseWriter, r *http.Request) {
		authCtrl.ShowLoginForm(w, r, nil)
	})
	r.Get("/register", func(w http.ResponseWriter, r *http.Request) {
		authCtrl.ShowRegisterForm(w, r, nil)
	})
	r.Post("/register", authCtrl.SendVerificationCode)
	r.Get("/register/verify", authCtrl.ShowVerifyForm)
	r.Post("/register/verify", authCtrl.VerifyRegister)
	r.Post("/login", authCtrl.Login)
	r.Get("/login/verify", authCtrl.ShowLoginVerifyForm)
	r.Post("/login/verify", authCtrl.VerifyLogin)
	r.Post("/logout", authCtrl.Logout)

	// ★ 管理者専用ページ
	r.Get("/admin", adminCtrl.Index)

	// ★ 管理者専用API
	r.Route("/api/admin", func(r chi.Router) {
		r.Get("/user-decks", adminCtrl.UserDecksApi)
		r.Post("/penalty", adminCtrl.ApplyPenaltyApi)
		r.Post("/users/update", adminCtrl.UpdateUserApi)
		r.Post("/users/role", adminCtrl.UpdateUserRoleApi)
		r.Post("/users/delete", adminCtrl.DeleteUserApi)
		r.Post("/reports/action", adminCtrl.HandleReportApi)
		r.Post("/reports/resolve-user", adminCtrl.ResolveUserReportApi)
		r.Post("/reports/clean", adminCtrl.CleanReportsApi)
		r.Post("/notifications", adminCtrl.SendNotificationApi)
		r.Post("/decks/bulk-private", adminCtrl.BulkMakeDecksPrivateApi)
		r.Post("/inquiries/reply", adminCtrl.ReplyInquiryApi)
	})
}
// 環境変数を取得（未設定時は .env からフォールバック取得）
func getEnvWithFallback(key string) string {
	val := os.Getenv(key)
	if val != "" {
		return val
	}
	baseDir, _ := os.Getwd()
	envPaths := []string{filepath.Join(baseDir, ".env"), filepath.Join(filepath.Dir(baseDir), ".env")}
	for _, ep := range envPaths {
		if f, err := os.Open(ep); err == nil {
			scanner := bufio.NewScanner(f)
			for scanner.Scan() {
				line := strings.TrimSpace(scanner.Text())
				if strings.HasPrefix(line, key+"=") {
					val = strings.TrimSpace(strings.TrimPrefix(line, key+"="))
					f.Close()
					return val
				}
			}
			f.Close()
		}
	}
	return ""
}

// 管理者宛にお問い合わせ通知メールを送信（Resend API 利用）
func sendContactEmail(senderEmail, subject, content string) error {
	apiKey := getEnvWithFallback("RESEND_API_KEY")
	adminEmail := getEnvWithFallback("ADMIN_EMAIL")

	if apiKey == "" {
		return fmt.Errorf("RESEND_API_KEY が設定されていません")
	}
	if adminEmail == "" {
		return fmt.Errorf("ADMIN_EMAIL が設定されていません")
	}

	// ドメイン認証が完了していない場合は "onboarding@resend.dev" を使用
	fromEmail := getEnvWithFallback("FROM_EMAIL")
	if fromEmail == "" {
		fromEmail = "onboarding@resend.dev"
	}

	bodyText := fmt.Sprintf("お問い合わせを受信しました。\n\n【差出人】: %s\n【件名】: %s\n【内容】:\n%s\n",
		senderEmail, subject, content)

	payload := map[string]interface{}{
		"from":     fmt.Sprintf("Due-Muscle お問い合わせ <%s>", fromEmail),
		"to":       []string{adminEmail},
		"reply_to": senderEmail,
		"subject":  fmt.Sprintf("[お問い合わせ] %s", subject),
		"text":     bodyText,
	}

	jsonBytes, err := json.Marshal(payload)
	if err != nil {
		return fmt.Errorf("リクエストJSON作成失敗: %w", err)
	}

	req, err := http.NewRequest("POST", "https://api.resend.com/emails", bytes.NewBuffer(jsonBytes))
	if err != nil {
		return fmt.Errorf("HTTPリクエスト作成失敗: %w", err)
	}

	req.Header.Set("Authorization", "Bearer "+apiKey)
	req.Header.Set("Content-Type", "application/json")

	client := &http.Client{Timeout: 10 * time.Second}
	resp, err := client.Do(req)
	if err != nil {
		return fmt.Errorf("Resend API接続失敗: %w", err)
	}
	defer resp.Body.Close()

	if resp.StatusCode < 200 || resp.StatusCode >= 300 {
		resBody, _ := io.ReadAll(resp.Body)
		return fmt.Errorf("Resend APIエラー (Status %d): %s", resp.StatusCode, string(resBody))
	}

	return nil
}