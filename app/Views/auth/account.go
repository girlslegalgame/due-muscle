package auth

import (
	"html/template"
	"io"
)

// User 表示用ユーザー構造体
type User struct {
	Username string
	Email    string
}

// AccountViewData ビューに渡すデータ構造体
type AccountViewData struct {
	Success string
	Error   string
	User    *User
}

// RenderAccount テンプレートを描画する関数
func RenderAccount(w io.Writer, data AccountViewData) error {
	tmpl, err := template.ParseFiles("app/Views/auth/account.html")
	if err != nil {
		return err
	}
	return tmpl.Execute(w, data)
}