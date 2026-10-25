package deck

import (
	"html/template"
	"io"
)

// RenderDeckDetailModal はデッキ詳細モーダルのHTMLを出力します
func RenderDeckDetailModal(w io.Writer) error {
	tmpl, err := template.ParseFiles("app/Views/deck/deck_detail_modal.html")
	if err != nil {
		return err
	}
	return tmpl.Execute(w, nil)
}
