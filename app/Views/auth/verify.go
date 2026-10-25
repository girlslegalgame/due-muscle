package auth

import (
	"html/template"
	"net/http"
	"os"
)

// Viewに渡すデータ構造体
type VerifyViewData struct {
	ActionURL string
	Success   string
	Error     string
	IsLogin   bool
	IsLocal   bool
	DevCode   string
}

// verify.php と同じロジックを処理してレンダリングする関数
func RenderVerify(w http.ResponseWriter, r *http.Request, session map[string]interface{}, actionURL string) {
	// フラッシュメッセージの取得と破棄 (unset)
	var successMsg, errorMsg string
	if val, ok := session["success"].(string); ok && val != "" {
		successMsg = val
		delete(session, "success")
	}
	if val, ok := session["error"].(string); ok && val != "" {
		errorMsg = val
		delete(session, "error")
	}

	// ログイン判定
	isLogin := actionURL == "/login/verify"

	// ローカル環境判定（RAILWAY_ENVIRONMENTが空ならローカル）
	isLocal := os.Getenv("RAILWAY_ENVIRONMENT") == ""

	// 開発用コードの取得
	devCode := ""
	if isLocal {
		if code, ok := session["verification_code"].(string); ok && code != "" {
			devCode = code
		} else if code, ok := session["login_verify_code"].(string); ok && code != "" {
			devCode = code
		}
	}

	if actionURL == "" {
		actionURL = "/register/verify"
	}

	data := VerifyViewData{
		ActionURL: actionURL,
		Success:   successMsg,
		Error:     errorMsg,
		IsLogin:   isLogin,
		IsLocal:   isLocal,
		DevCode:   devCode,
	}

	// テンプレートのパースと実行
	tmpl := template.Must(template.ParseFiles("app/Views/auth/verify.html"))
	tmpl.Execute(w, data)
}