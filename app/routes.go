package app

import (
	"bufio"
	"log"
	"net/smtp"
	"os"
	"path/filepath"
	"strings"
	"fmt"
	controllers "deck-maker/app/Controllers"
	"net/http"
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
	r.Get("/decks/playtest", deckCtrl.Playtest)
	r.Get("/decks/share", deckCtrl.Share)
	r.Get("/users/decks", deckCtrl.UserPublicDecks)
	// AIジャッジ専用ページ
	r.Get("/rule-bot", ruleBotCtrl.Index)
	r.Get("/notifications", notifCtrl.Index)
	r.Get("/profile", func(w http.ResponseWriter, r *http.Request) {
		views.RenderView(w, r, "home/profile.html", map[string]interface{}{})
	})
	// お問い合わせ画面
	r.Get("/contact", func(w http.ResponseWriter, r *http.Request) {
		views.RenderView(w, r, "home/contact.html", map[string]interface{}{})
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

// 管理者宛にお問い合わせ通知メールを送信
func sendContactEmail(senderEmail, subject, content string) error {
	adminEmail := getEnvWithFallback("ADMIN_EMAIL")
	if adminEmail == "" {
		return fmt.Errorf("ADMIN_EMAIL が設定されていません")
	}

	smtpHost := getEnvWithFallback("SMTP_HOST")
	smtpPort := getEnvWithFallback("SMTP_PORT")
	smtpUser := getEnvWithFallback("SMTP_USER")
	smtpPass := getEnvWithFallback("SMTP_PASS")

	// SMTP設定がない場合はコンソールログに出力して終了
	if smtpHost == "" || smtpPort == "" {
		log.Printf("[Contact] SMTP未設定のためメール送信をスキップ: 送信先=%s, 件名=%s, 差出人=%s", adminEmail, subject, senderEmail)
		return nil
	}

	body := fmt.Sprintf("From: %s\r\nTo: %s\r\nReply-To: %s\r\nSubject: [お問い合わせ] %s\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\nお問い合わせを受信しました。\r\n\r\n【差出人】: %s\r\n【件名】: %s\r\n【内容】:\r\n%s\r\n",
		smtpUser, adminEmail, senderEmail, subject, senderEmail, subject, content)

	auth := smtp.PlainAuth("", smtpUser, smtpPass, smtpHost)
	return smtp.SendMail(smtpHost+":"+smtpPort, auth, smtpUser, []string{adminEmail}, []byte(body))
}