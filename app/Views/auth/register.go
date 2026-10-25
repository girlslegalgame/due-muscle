package auth

import (
	"html/template"
	"net/http"
)

// RegisterViewData はテンプレートに渡すデータ構造体です
type RegisterViewData struct {
	SessionError string
	Errors       []string
	Username     string
	Email        string
}

// RenderRegister は register.html を描画します
func RenderRegister(w http.ResponseWriter, data RegisterViewData) error {
	tmpl, err := template.ParseFiles("app/Views/auth/register.html")
	if err != nil {
		http.Error(w, err.Error(), http.StatusInternalServerError)
		return err
	}
	return tmpl.Execute(w, data)
}