package deck

import (
	_ "embed" // ← これを追加
	"fmt"
	"net/http"

    controllers "deck-maker/app/Controllers"
	views "deck-maker/app/Views"
)

// ↓ この2行だけで help_search.html の中身が自動的に mainHTML に読み込まれます
//
//go:embed help_search.html
var mainHTML string

func HelpSearchHandler(w http.ResponseWriter, r *http.Request) {
	if !controllers.CheckAdminOrDeveloper(w, r) {
		w.Header().Set("Content-Type", "text/html; charset=UTF-8")
		w.WriteHeader(http.StatusForbidden)
		fmt.Fprint(w, forbiddenHTML)
		return
	}

	// ★ RenderView を使用して共通レイアウト（ヘッダー・フッター）を適用
	views.RenderView(w, r, "deck/help_search.html", map[string]interface{}{})
}

// 403用エラー画面（短いのでそのまま残してもOK）
const forbiddenHTML = `<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>アクセス権限エラー</title>
    <!-- CSS等は元のコードと同じ -->
    <style>
        body { font-family: sans-serif; background: #f0f0f0; margin: 0; padding: 50px 20px; display: flex; justify-content: center; align-items: center; min-height: 50vh; }
        .error-box { background: white; padding: 40px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); text-align: center; max-width: 450px; width: 100%; }
        h2 { color: #dc3545; margin-top: 0; font-size: 22px; }
        p { color: #555; font-size: 14px; line-height: 1.6; margin-bottom: 25px; }
        .btn-back { display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; font-weight: bold; font-size: 14px; transition: background 0.2s; }
        .btn-back:hover { background: #0056b3; }
    </style>
</head>
<body>
    <div class="error-box">
        <h2>アクセス権限エラー</h2>
        <p>このページ（カード情報の検索・編集）を閲覧、または操作するための権限がありません。管理者または開発者アカウントでログインし直してください。</p>
        <a href="/mydecks" class="btn-back">マイページ（マイデッキ）へ戻る</a>
    </div>
</body>
</html>`
