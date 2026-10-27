package controllers

import (
	"bytes"
	"crypto/hmac"
	"crypto/sha256"
	"crypto/subtle"
	"database/sql"
	"encoding/hex"
	"encoding/json"
	"fmt"
	"html"
	"io"
	"log"
	"math/rand"
	"net/http"
	"os"
	"regexp"
	"strconv"
	"strings"
	"time"

	models "deck-maker/app/Models"
	views "deck-maker/app/Views"

	"golang.org/x/crypto/bcrypt"
)

type AuthController struct {
	db *sql.DB
}

func NewAuthController(db *sql.DB) *AuthController {
	return &AuthController{
		db: db,
	}
}

func (c *AuthController) ShowRegisterForm(w http.ResponseWriter, r *http.Request, errors []string) {
	session := views.GetSession(r)
	if session["user_id"] != nil {
		http.Redirect(w, r, "/mydecks", http.StatusFound)
		return
	}
	views.RenderView(w, r, "auth/register.html", map[string]interface{}{"errors": errors})
}

func (c *AuthController) ShowLoginForm(w http.ResponseWriter, r *http.Request, errors []string) {
	session := views.GetSession(r)
	if session["user_id"] != nil {
		http.Redirect(w, r, "/mydecks", http.StatusFound)
		return
	}

	errorMsg := ""
	if len(errors) > 0 {
		errorMsg = strings.Join(errors, "<br>")
	}

	views.RenderView(w, r, "auth/login.html", map[string]interface{}{
		"errors": errors,
		"Errors": errors,
		"error":  errorMsg,
		"Error":  errorMsg,
	})
}

/**
 * Brevo API (HTTPS) を使用したメール送信
 * (SMTPが遮断される環境でも100%確実に届きます)
 */
func (c *AuthController) sendEmail(to, subject, body string) bool {
	// 開発用にログ出力は残しておきます
	log.Printf("【デバッグ】Brevo APIからメール送信を試みます。送信先: %s, 件名: %s", to, subject)

	apiKey := os.Getenv("BREVO_API_KEY")
	if apiKey == "" {
		log.Println("メール送信エラー: BREVO_API_KEY が環境変数に設定されていません。")
		return false
	}

	// 送信元のメールアドレス（Brevoに登録したご自身のアドレス）
	fromEmail := os.Getenv("SMTP_FROM")
	if fromEmail == "" {
		fromEmail = "your-brevo-registered-email@gmail.com"
	}

	url := "https://api.brevo.com/v3/smtp/email"

	// 送信データの設定（JSON形式）
	payload := map[string]interface{}{
		"sender": map[string]string{
			"name":  "デュエ筋",
			"email": fromEmail,
		},
		"to": []map[string]string{
			{"email": to},
		},
		"subject":     subject,
		"htmlContent": body,
	}

	jsonData, err := json.Marshal(payload)
	if err != nil {
		log.Println("JSONエンコードエラー:", err)
		return false
	}

	req, err := http.NewRequest("POST", url, bytes.NewBuffer(jsonData))
	if err != nil {
		log.Println("リクエスト作成エラー:", err)
		return false
	}

	req.Header.Set("api-key", apiKey)
	req.Header.Set("Content-Type", "application/json")
	req.Header.Set("Accept", "application/json")

	client := &http.Client{Timeout: 10 * time.Second}
	resp, err := client.Do(req)
	if err != nil {
		log.Printf("Brevo API Connection Error: %v", err)
		return false
	}
	defer resp.Body.Close()

	respBody, _ := io.ReadAll(resp.Body)

	// ステータスコードが2xxであれば送信成功
	if resp.StatusCode >= 200 && resp.StatusCode < 300 {
		log.Println("Brevo API経由でのメール送信に成功しました。")
		return true
	} else {
		log.Printf("Brevo API Error (HTTP %d): %s", resp.StatusCode, string(respBody))
		return false
	}
}

/**
 * ログイン処理（1ステップ目：パスワード検証と認証コード送信）
 */
func (c *AuthController) Login(w http.ResponseWriter, r *http.Request) {
	defer func() {
		if err := recover(); err != nil {
			w.Header().Set("Content-Type", "text/html; charset=utf-8")
			fmt.Fprintf(w, "<h3>[デバッグ] ログイン処理中にエラーが発生しました</h3>")
			fmt.Fprintf(w, "<strong>エラーメッセージ:</strong> %s<br>", html.EscapeString(fmt.Sprintf("%v", err)))
			return
		}
	}()

	if r.Method == http.MethodPost {
		_ = r.ParseForm()
		loginID := strings.TrimSpace(r.FormValue("login_id"))
		password := r.FormValue("password")

		var errors []string

		if loginID == "" || password == "" {
			errors = append(errors, "ユーザーID（またはメールアドレス）とパスワードを入力してください。")
		}

		if len(errors) == 0 {
			db, err := models.DatabaseConnect()
			if err != nil {
				c.ShowLoginForm(w, r, []string{"データベース接続に失敗しました: " + err.Error()})
				return
			}
			userModel := models.NewUser(db)

			// ★ FindByLoginID から FindByIdentifier に変更
			user, err := userModel.FindByIdentifier(loginID)
			if err != nil || user == nil {
				errors = append(errors, "ユーザーID（またはメールアドレス）かパスワードが間違っています。")
			} else if bcrypt.CompareHashAndPassword([]byte(user.PasswordHash), []byte(password)) != nil {
				errors = append(errors, "ユーザーID（またはメールアドレス）かパスワードが間違っています。")
			} else {
				// ★ ローカル環境判定（RAILWAY環境変数が無い、またはホスト名等で判定）
				isLocal := os.Getenv("RAILWAY_ENVIRONMENT") == ""

				// ローカル環境の場合は設定値に関わらず二段階認証をスキップ
				skip2FA := isLocal || !user.TwoFactorEnabled || user.Email == "" || c.isKnownDevice(r, user)

				if skip2FA {
					session := views.GetSession(r)
					session["user_id"] = user.UserID
					session["username"] = user.Username
					role := user.Role
					if role == "" { role = "user" }
					session["role"] = role
					views.SaveSession(w, session)

					c.saveDeviceCookie(w, user.UserID, user.PasswordHash)
					http.Redirect(w, r, "/mydecks", http.StatusFound)
					return
				}

				// 二段階認証コード送信フロー
				code := fmt.Sprintf("%06d", rand.Intn(1000000))
				expiresAt := time.Now().Unix() + 600

				subject := "【デュエ筋】ログイン認証コード"
				body := fmt.Sprintf("<p>ログイン認証コード: <h2>%s</h2> 有効期限: 10分</p>", code)

				if !c.sendEmail(user.Email, subject, body) {
					c.ShowLoginForm(w, r, []string{"認証メールの送信に失敗しました。"})
					return
				}

				session := views.GetSession(r)
				session["temp_login"] = map[string]interface{}{
					"user_id":    user.UserID,
					"username":   user.Username,
					"email":      user.Email,
					"code":       code,
					"expires_at": expiresAt,
					"role":       user.Role,
				}
				views.SaveSession(w, session)
				http.Redirect(w, r, "/login/verify", http.StatusFound)
				return
			}
		}

		c.ShowLoginForm(w, r, errors)
	} else {
		c.ShowLoginForm(w, r, []string{})
	}
}

/**
 * ログイン認証コード入力画面の表示
 */
func (c *AuthController) ShowLoginVerifyForm(w http.ResponseWriter, r *http.Request) {
	session := views.GetSession(r)
	if session["temp_login"] == nil {
		session["error"] = "ログイン情報のセッションが切れました。最初からログインし直してください。"
		views.SaveSession(w, session)
		http.Redirect(w, r, "/login", http.StatusFound)
		return
	}

	views.RenderView(w, r, "auth/verify.html", map[string]interface{}{"action_url": "/login/verify"})
}

/**
 * ログイン認証コードの確認処理
 */
func (c *AuthController) VerifyLogin(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodPost {
		http.Redirect(w, r, "/login", http.StatusFound)
		return
	}

	_ = r.ParseForm()
	inputCode := strings.TrimSpace(r.FormValue("code"))

	session := views.GetSession(r)
	tempLoginRaw := session["temp_login"]
	if tempLoginRaw == nil {
		session["error"] = "セッションが存在しません。ログインをやり直してください。"
		views.SaveSession(w, session)
		http.Redirect(w, r, "/login", http.StatusFound)
		return
	}

	tempLogin := tempLoginRaw.(map[string]interface{})

	// JSON経由での復元に対応（float64またはint64）
	var expiresAt int64
	switch v := tempLogin["expires_at"].(type) {
	case float64:
		expiresAt = int64(v)
	case int64:
		expiresAt = v
	default:
		expiresAt = 0
	}

	if time.Now().Unix() > expiresAt {
		delete(session, "temp_login")
		session["error"] = "認証コードの期限が切れました。ログインを最初からやり直してください。"
		views.SaveSession(w, session)
		http.Redirect(w, r, "/login", http.StatusFound)
		return
	}

	if inputCode != tempLogin["code"].(string) {
		session["error"] = "認証コードが正しくありません。"
		views.SaveSession(w, session)
		http.Redirect(w, r, "/login/verify", http.StatusFound)
		return
	}

	// 正式ログイン完了
	session["user_id"] = tempLogin["user_id"]
	session["username"] = tempLogin["username"]
	role := "user"
	if r, ok := tempLogin["role"].(string); ok && r != "" {
		role = r
	}
	session["role"] = role

	// ★修正：認証に成功したため、この端末（ブラウザ）を30日間記憶する
	func() {
		defer func() {
			if e := recover(); e != nil {
				log.Printf("Device Cookie Save Error: %v", e)
			}
		}()
		db, err := models.DatabaseConnect()
		if err != nil {
			// エラー処理（例: ログ出力やpanic、リダイレクトなど）
			log.Printf("DB接続エラー: %v", err)
			return
		}
		var passwordHash string
		err = db.QueryRow("SELECT password_hash FROM users WHERE user_id = ?", tempLogin["user_id"]).Scan(&passwordHash)
		if err == nil {
			c.saveDeviceCookie(w, tempLogin["user_id"], passwordHash)
		}
	}()

	delete(session, "temp_login")
	views.SaveSession(w, session)

	// すべての準備が整った段階でリダイレクトします
	http.Redirect(w, r, "/mydecks", http.StatusFound)
}

func (c *AuthController) Logout(w http.ResponseWriter, r *http.Request) {
	session := views.GetSession(r)

	// 自動ログイン用Cookieの削除
	if userId := session["user_id"]; userId != nil {
		http.SetCookie(w, &http.Cookie{
			Name:     fmt.Sprintf("known_device_%v", userId),
			Value:    "",
			Expires:  time.Unix(0, 0),
			MaxAge:   -1,
			Path:     "/",
			HttpOnly: true,
		})
	}

	// ログイン状態を保持している user_id Cookie を削除・無効化する
	http.SetCookie(w, &http.Cookie{
		Name:     "user_id",
		Value:    "",
		Path:     "/",
		MaxAge:   -1,
		Expires:  time.Unix(0, 0),
		HttpOnly: true,
	})

	// セッションCookie（app_session）の破棄
	http.SetCookie(w, &http.Cookie{
		Name:     "app_session",
		Value:    "",
		Path:     "/",
		MaxAge:   -1,
		Expires:  time.Unix(0, 0),
		HttpOnly: true,
	})

	http.Redirect(w, r, "/login", http.StatusFound)
}

var loginIDRegex = regexp.MustCompile(`^[a-zA-Z0-9_]{5,15}$`)

func (c *AuthController) SendVerificationCode(w http.ResponseWriter, r *http.Request) {
	session := views.GetSession(r)

	// 1デバイス1アカウント制限チェック
	if cookie, err := r.Cookie("device_has_registered"); err == nil && cookie.Value == "1" {
		session["error"] = "この端末では既にアカウントが作成されています。1端末につき作成できるアカウントは1つまでです。"
		views.SaveSession(w, session)
		http.Redirect(w, r, "/register", http.StatusFound)
		return
	}

	_ = r.ParseForm()
	loginID := strings.TrimSpace(r.FormValue("login_id"))
	username := strings.TrimSpace(r.FormValue("username"))
	email := strings.TrimSpace(r.FormValue("email"))
	password := r.FormValue("password")
	passwordConfirm := r.FormValue("password_confirm")
	twoFactor := r.FormValue("two_factor") == "1"
	if email == "" {
		twoFactor = false
	}
	if !loginIDRegex.MatchString(loginID) {
		session["error"] = "ユーザーIDは5〜15文字の半角英数字およびアンダースコア（_）で入力してください。"
		views.SaveSession(w, session)
		http.Redirect(w, r, "/register", http.StatusFound)
		return
	}

	if username == "" || password == "" {
		session["error"] = "ユーザー名とパスワードを入力してください。"
		views.SaveSession(w, session)
		http.Redirect(w, r, "/register", http.StatusFound)
		return
	}

	if password != passwordConfirm || len(password) < 8 {
		session["error"] = "パスワードの入力内容をご確認ください（8文字以上、確認用と一致）。"
		views.SaveSession(w, session)
		http.Redirect(w, r, "/register", http.StatusFound)
		return
	}

	db, err := models.DatabaseConnect()
	if err != nil {
		log.Printf("DB接続エラー: %v", err)
		return
	}

	// ユーザーID重複チェック
	var exists int
	if err := db.QueryRow("SELECT user_id FROM users WHERE login_id = ?", loginID).Scan(&exists); err == nil {
		session["error"] = "このユーザーIDは既に使用されています。"
		views.SaveSession(w, session)
		http.Redirect(w, r, "/register", http.StatusFound)
		return
	}

	// メールアドレス重複チェック（入力時のみ）
	if email != "" {
		if err := db.QueryRow("SELECT user_id FROM users WHERE email = ?", email).Scan(&exists); err == nil {
			session["error"] = "このメールアドレスは既に使用されています。"
			views.SaveSession(w, session)
			http.Redirect(w, r, "/register", http.StatusFound)
			return
		}
	} else {
		twoFactor = false // メールが無ければ二段階認証は強制無効
	}

	hashedBytes, _ := bcrypt.GenerateFromPassword([]byte(password), bcrypt.DefaultCost)
	passwordHash := string(hashedBytes)

	// メール認証を行わない場合（メール未入力、二段階認証OFF、またはローカル環境）は即時本登録
	isLocal := os.Getenv("RAILWAY_ENVIRONMENT") == ""
	if email == "" || !twoFactor || isLocal {
		res, err := db.Exec("INSERT INTO users (login_id, username, email, password_hash, two_factor_enabled, created_at, updated_at) VALUES (?, ?, NULLIF(?, ''), ?, ?, NOW(), NOW())",
			loginID, username, email, passwordHash, twoFactor)
		if err != nil {
			session["error"] = "登録に失敗しました: " + err.Error()
			views.SaveSession(w, session)
			http.Redirect(w, r, "/register", http.StatusFound)
			return
		}

		userId, _ := res.LastInsertId()
		session["user_id"] = userId
		session["username"] = username
		session["role"] = "user"
		c.saveDeviceCookie(w, userId, passwordHash)

		// 端末作成済みCookieを付与（10年間有効）
		http.SetCookie(w, &http.Cookie{
			Name: "device_has_registered", Value: "1", Path: "/", MaxAge: 315360000, HttpOnly: true, SameSite: http.SameSiteLaxMode,
		})

		session["success"] = "アカウント登録が完了しました！"
		views.SaveSession(w, session)
		http.Redirect(w, r, "/mydecks", http.StatusFound)
		return
	}

	// メール認証が必要な場合（メールあり かつ 二段階認証ON）
	code := fmt.Sprintf("%06d", rand.Intn(1000000))
	expiresAt := time.Now().Unix() + 600

	session["temp_register"] = map[string]interface{}{
		"login_id":           loginID,
		"username":           username,
		"email":              email,
		"password_hash":      passwordHash,
		"two_factor_enabled": twoFactor,
		"code":               code,
		"expires_at":         expiresAt,
	}

	c.sendEmail(email, "【デュエ筋】アカウント新規作成 認証コード", fmt.Sprintf("認証コード: %s (有効期限10分)", code))
	views.SaveSession(w, session)
	http.Redirect(w, r, "/register/verify", http.StatusFound)
}

/**
 * 新規登録用認証コード入力画面の表示
 */
func (c *AuthController) ShowVerifyForm(w http.ResponseWriter, r *http.Request) {
	session := views.GetSession(r)
	if session["temp_register"] == nil {
		session["error"] = "登録の有効期限が切れたか、無効なアクセスです。"
		views.SaveSession(w, session)
		http.Redirect(w, r, "/register", http.StatusFound)
		return
	}

	views.RenderView(w, r, "auth/verify.html", map[string]interface{}{"action_url": "/register/verify"})
}

/**
 * 新規登録用認証コードの確認処理（本登録）
 */
func (c *AuthController) VerifyRegister(w http.ResponseWriter, r *http.Request) {
	if r.Method != http.MethodPost {
		http.Redirect(w, r, "/register", http.StatusFound)
		return
	}

	_ = r.ParseForm()
	inputCode := strings.TrimSpace(r.FormValue("code"))

	session := views.GetSession(r)
	tempUserRaw := session["temp_register"]
	if tempUserRaw == nil {
		session["error"] = "登録情報のセッションが切れました。最初から登録し直してください。"
		views.SaveSession(w, session)
		http.Redirect(w, r, "/register", http.StatusFound)
		return
	}

	tempUser := tempUserRaw.(map[string]interface{})
	expiresAt := tempUser["expires_at"].(int64)

	// 有効期限検証
	if time.Now().Unix() > expiresAt {
		delete(session, "temp_register")
		session["error"] = "認証コードの期限（10分）が切れています。最初からやり直してください。"
		views.SaveSession(w, session)
		http.Redirect(w, r, "/register", http.StatusFound)
		return
	}

	// コード一致検証
	if inputCode != tempUser["code"].(string) {
		session["error"] = "認証コードが正しくありません。"
		views.SaveSession(w, session)
		http.Redirect(w, r, "/register/verify", http.StatusFound)
		return
	}

	defer func() {
		if e := recover(); e != nil {
			session["error"] = fmt.Sprintf("登録完了処理中にエラーが発生しました: %v", e)
			views.SaveSession(w, session)
			http.Redirect(w, r, "/register", http.StatusFound)
		}
	}()

	db, err := models.DatabaseConnect()
	if err != nil {
		// エラー処理（例: ログ出力やpanic、リダイレクトなど）
		log.Printf("DB接続エラー: %v", err)
		return
	}

	loginIDStr, _ := tempUser["login_id"].(string)
	emailStr, _ := tempUser["email"].(string)
	twoFactorVal, _ := tempUser["two_factor_enabled"].(bool)
	pwdHash, _ := tempUser["password_hash"].(string)

	var existingUserId int
	err = db.QueryRow("SELECT user_id FROM users WHERE login_id = ? OR (? != '' AND email = ?)", 
		loginIDStr, emailStr, emailStr).Scan(&existingUserId)
	if err == nil {
		delete(session, "temp_register")
		session["error"] = "すでに登録されているユーザーIDまたはメールアドレスです。"
		views.SaveSession(w, session)
		http.Redirect(w, r, "/register", http.StatusFound)
		return
	}

	res, err := db.Exec("INSERT INTO users (login_id, username, email, password_hash, two_factor_enabled, created_at, updated_at) VALUES (?, ?, NULLIF(?, ''), ?, ?, NOW(), NOW())",
		loginIDStr, tempUser["username"].(string), emailStr, pwdHash, twoFactorVal)
	if err != nil {
		panic(err)
	}

	userId, _ := res.LastInsertId()
	session["user_id"] = userId
	session["username"] = tempUser["username"]
	session["role"] = "user"

	c.saveDeviceCookie(w, userId, pwdHash)

	// ★ 端末作成済みCookieを付与
	http.SetCookie(w, &http.Cookie{
		Name: "device_has_registered", Value: "1", Path: "/", MaxAge: 315360000, HttpOnly: true, SameSite: http.SameSiteLaxMode,
	})

	delete(session, "temp_register") // 一時データの消去

	session["success"] = "アカウント登録が完了しました！"
	views.SaveSession(w, session)
	http.Redirect(w, r, "/mydecks", http.StatusFound)
}

/**
 * アカウント設定画面の表示
 */
func (c *AuthController) ShowAccountForm(w http.ResponseWriter, r *http.Request) {
	userID := views.GetSessionUserID(r)
	if userID == nil {
		http.Redirect(w, r, "/login", http.StatusSeeOther)
		return
	}

	db, err := models.DatabaseConnect()
	if err != nil {
		http.Error(w, "データベース接続エラー", http.StatusInternalServerError)
		return
	}

	// ShowAccountForm: SELECT句に login_id, two_factor_enabled を追加
	var username, email, loginID string
	var twoFactor bool
	err = db.QueryRowContext(r.Context(), "SELECT login_id, username, COALESCE(email, ''), two_factor_enabled FROM users WHERE user_id = ?", userID).
		Scan(&loginID, &username, &email, &twoFactor)

// dataマップに詰め直す
	data := map[string]interface{}{
		"User": map[string]interface{}{
			"LoginID":          loginID,
			"Username":         username,
			"Email":            email,
			"TwoFactorEnabled": twoFactor,
		},
	}

	views.RenderView(w, r, "auth/account.html", data)
}

/**
 * アカウント情報の変更処理
 */
func (c *AuthController) UpdateAccount(w http.ResponseWriter, r *http.Request) {
	session := views.GetSession(r)
	userId := session["user_id"]
	if userId == nil {
		http.Redirect(w, r, "/login", http.StatusFound)
		return
	}

	// UpdateAccount メソッド内のフォーム取得〜更新処理部分
	_ = r.ParseForm()
	loginID := strings.TrimSpace(r.FormValue("login_id")) // ★追加
	username := strings.TrimSpace(r.FormValue("username"))
	email := strings.TrimSpace(r.FormValue("email"))
	password := r.FormValue("password")
	passwordConfirm := r.FormValue("password_confirm")

	// ★追加: ユーザーIDの形式チェック
	if !loginIDRegex.MatchString(loginID) {
		session["error"] = "ユーザーIDは5〜15文字の半角英数字およびアンダースコア（_）で入力してください。"
		views.SaveSession(w, session)
		http.Redirect(w, r, "/account", http.StatusFound)
		return
	}

	if username == "" {
		session["error"] = "ユーザー名は必須です。"
		views.SaveSession(w, session)
		http.Redirect(w, r, "/account", http.StatusFound)
		return
	}

	twoFactor := r.FormValue("two_factor") == "1"
	if email == "" {
		twoFactor = false
	}

	defer func() {
		if e := recover(); e != nil {
			session["error"] = fmt.Sprintf("エラーが発生しました: %v", e)
			views.SaveSession(w, session)
			http.Redirect(w, r, "/account", http.StatusFound)
		}
	}()

	db, err := models.DatabaseConnect()
	if err != nil {
		log.Printf("DB接続エラー: %v", err)
		return
	}

	// ★追加: 他のユーザーとのユーザーID重複チェック
	var existingUserId int
	err = db.QueryRow("SELECT user_id FROM users WHERE login_id = ? AND user_id != ?", loginID, userId).Scan(&existingUserId)
	if err == nil {
		session["error"] = "このユーザーIDは既に他のアカウントで使用されています。"
		views.SaveSession(w, session)
		http.Redirect(w, r, "/account", http.StatusFound)
		return
	}

	// メール重複チェック（メールが入力されている場合のみチェック）
	if email != "" {
		err = db.QueryRow("SELECT user_id FROM users WHERE email = ? AND user_id != ?", email, userId).Scan(&existingUserId)
		if err == nil {
			session["error"] = "このメールアドレスは既に他のアカウントで使用されています。"
			views.SaveSession(w, session)
			http.Redirect(w, r, "/account", http.StatusFound)
			return
		}
	}

	if password != "" {
		if password != passwordConfirm {
			session["error"] = "新しいパスワードと確認用入力が一致しません。"
			views.SaveSession(w, session)
			http.Redirect(w, r, "/account", http.StatusFound)
			return
		}
		if len(password) < 8 {
			session["error"] = "パスワードは8文字以上で設定してください。"
			views.SaveSession(w, session)
			http.Redirect(w, r, "/account", http.StatusFound)
			return
		}

		hashedBytes, err := bcrypt.GenerateFromPassword([]byte(password), bcrypt.DefaultCost)
		if err != nil {
			panic(err)
		}
		hashedPassword := string(hashedBytes)

		// ★ login_id を UPDATE に追加
		_, err = db.Exec("UPDATE users SET login_id = ?, username = ?, email = NULLIF(?, ''), two_factor_enabled = ?, password_hash = ?, updated_at = NOW() WHERE user_id = ?",
			loginID, username, email, twoFactor, hashedPassword, userId)
		if err != nil {
			panic(err)
		}
	} else {
		// ★ login_id を UPDATE に追加
		_, err := db.Exec("UPDATE users SET login_id = ?, username = ?, email = NULLIF(?, ''), two_factor_enabled = ?, updated_at = NOW() WHERE user_id = ?",
			loginID, username, email, twoFactor, userId)
		if err != nil {
			panic(err)
		}
	}

	session["success"] = "アカウント設定を更新しました。"
	views.SaveSession(w, session)
	http.Redirect(w, r, "/account", http.StatusFound)
}

/**
 * ユーザーID・パスワードハッシュ・有効期限からセキュアな端末識別用トークンを生成
 */
func (c *AuthController) generateDeviceToken(userId interface{}, passwordHash string, expires int64) string {
	salt := "dm_deck_app_device_salt_9876"
	message := fmt.Sprintf("%v_%d_%s", userId, expires, salt)
	h := hmac.New(sha256.New, []byte(passwordHash))
	h.Write([]byte(message))
	return hex.EncodeToString(h.Sum(nil))
}

/**
 * 端末識別用Cookieをブラウザに保存する（30日間有効・アクセス毎に延長）
 */
func (c *AuthController) saveDeviceCookie(w http.ResponseWriter, userId interface{}, passwordHash string) {
	expires := time.Now().Unix() + (30 * 24 * 60 * 60) // 30日後
	token := c.generateDeviceToken(userId, passwordHash, expires)
	cookieValue := fmt.Sprintf("%d:%s", expires, token)
	cookieName := fmt.Sprintf("known_device_%v", userId)

	http.SetCookie(w, &http.Cookie{
		Name:     cookieName,
		Value:    cookieValue,
		Expires:  time.Unix(expires, 0),
		Path:     "/",
		Secure:   true,
		HttpOnly: true,
		SameSite: http.SameSiteLaxMode,
	})
}

/**
 * この端末が「既知の端末」であるかチェックする（30日以内のアクセスか検証）
 */
func (c *AuthController) isKnownDevice(r *http.Request, user *models.UserEntity) bool {
	cookieName := fmt.Sprintf("known_device_%v", user.UserID)
	cookie, err := r.Cookie(cookieName)
	if err != nil {
		return false
	}

	parts := strings.SplitN(cookie.Value, ":", 2)
	if len(parts) != 2 {
		return false
	}

	expiresStr := parts[0]
	token := parts[1]

	expires, err := strconv.ParseInt(expiresStr, 10, 64)
	if err != nil {
		return false
	}

	// 30日以上アクセスがなかった場合（期限切れ）
	if time.Now().Unix() > expires {
		return false
	}

	expectedToken := c.generateDeviceToken(user.UserID, user.PasswordHash, expires)
	return subtle.ConstantTimeCompare([]byte(expectedToken), []byte(token)) == 1
}

/**
 * セッションが切れている場合、Cookieから自動復元する
 * （アクセスがあるたびに有効期限をさらに30日後へスライド更新します）
 */
func (c *AuthController) TryAutoLogin(w http.ResponseWriter, r *http.Request) bool {
	session := views.GetSession(r)

	// 既にセッションが存在する場合：アクセスがあったのでCookie期限を30日延長
	if userId := session["user_id"]; userId != nil {
		func() {
			defer func() {
				if e := recover(); e != nil {
					log.Printf("Device Cookie Refresh Error: %v", e)
				}
			}()
			db, err := models.DatabaseConnect()
			if err != nil {
				// エラー処理（例: ログ出力やpanic、リダイレクトなど）
				log.Printf("DB接続エラー: %v", err)
				return
			}
			var passwordHash string
			err = db.QueryRow("SELECT password_hash FROM users WHERE user_id = ?", userId).Scan(&passwordHash)
			if err == nil {
				c.saveDeviceCookie(w, userId, passwordHash)
			}
		}()
		return true
	}

	// セッションが切れている場合：Cookieからセッションを復元
	for _, cookie := range r.Cookies() {
		if strings.HasPrefix(cookie.Name, "known_device_") {
			userIdStr := strings.TrimPrefix(cookie.Name, "known_device_")
			userId, err := strconv.Atoi(userIdStr)
			if err == nil && userId > 0 {
				parts := strings.SplitN(cookie.Value, ":", 2)
				if len(parts) != 2 {
					continue
				}

				expiresStr := parts[0]
				token := parts[1]

				expires, err := strconv.ParseInt(expiresStr, 10, 64)
				if err != nil {
					continue
				}

				// 30日間アクセスがなければ無効化（自動ログインさせない）
				if time.Now().Unix() > expires {
					continue
				}

				db, err := models.DatabaseConnect()
				if err != nil {
					// エラー処理（例: ログ出力やpanic、リダイレクトなど）
					log.Printf("DB接続エラー: %v", err)
					return false
				}
				var u models.UserEntity
				err = db.QueryRow("SELECT user_id, username, password_hash, role FROM users WHERE user_id = ?", userId).
					Scan(&u.UserID, &u.Username, &u.PasswordHash, &u.Role)

				if err == nil {
					expectedToken := c.generateDeviceToken(u.UserID, u.PasswordHash, expires)
					if subtle.ConstantTimeCompare([]byte(expectedToken), []byte(token)) == 1 {
						// セッションを静かに復元（リダイレクトは行わない）
						session["user_id"] = u.UserID
						session["username"] = u.Username
						role := u.Role
						if role == "" {
							role = "user"
						}
						session["role"] = role
						views.SaveSession(w, session)

						// アクセスがあったため、有効期限を今日から30日後に延長
						c.saveDeviceCookie(w, u.UserID, u.PasswordHash)
						return true
					}
				}
			}
		}
	}
	return false
}

/**
 * ★追加機能：現在ログインしているユーザーが管理者（admin）または開発者（developer）であるか検証する。
 * ビューファイルや他のコントローラーから静的（static）に呼び出せるように定義します。
 *
 * @return bool
 */
func CheckAdminOrDeveloper(w http.ResponseWriter, r *http.Request) bool {
	session := views.GetSession(r)

	if session["user_id"] == nil {
		return false
	}

	// すでにセッションにロールが格納されている場合はそれを利用
	userRole, ok := session["role"].(string)

	// セッションにロールがない場合、データベースにアクセスして最新のロールを取得しキャッシュする
	if !ok || userRole == "" {
		db, err := models.DatabaseConnect()
		if err != nil {
			// エラー処理（例: ログ出力やpanic、リダイレクトなど）
			log.Printf("DB接続エラー: %v", err)
			return false
		}
		var role string
		err = db.QueryRow("SELECT role FROM users WHERE user_id = ? LIMIT 1", session["user_id"]).Scan(&role)
		if err != nil {
			log.Println("AuthController::checkAdminOrDeveloper verification error:", err)
			return false
		}

		userRole = role
		if userRole == "" {
			userRole = "user"
		}
		session["role"] = userRole // セッションに保存して次回以降のクエリを防止
		views.SaveSession(w, session)
	}

	return userRole == "admin" || userRole == "developer"
}
