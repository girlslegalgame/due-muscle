package main

// main.go の import 部分を以下のように修正してください
import (
	"fmt"
	"log"
	"net/http"
	"os"

	"deck-maker/app"
	models "deck-maker/app/Models"

	"github.com/go-chi/chi/v5"
	"github.com/go-chi/chi/v5/middleware"
)

func main() {
	// 1. DB接続
	db, err := models.Connect()
	if err != nil {
		log.Fatalf("DB接続失敗: %v", err)
	}
	defer db.Close()

	// 2. コントローラー初期化
	// deckCtrl := &controllers.DeckController{}
	// cardCtrl := &controllers.CardController{}
	// authCtrl := &controllers.AuthController{}
	// main.go 内の該当箇所
	r := chi.NewRouter()

	r.Use(middleware.RequestID)
	r.Use(middleware.RealIP)
	r.Use(middleware.Logger)
	r.Use(middleware.Recoverer)
	r.Use(middleware.RedirectSlashes)

	// app/routes.go のルーティングを登録
	app.RegisterRoutes(r)

	port := os.Getenv("PORT")
	if port == "" {
		port = "8080"
	}
	// 4. 静的ファイル配信 (public/css, public/js, public/images)
	r.Handle("/css/*", http.StripPrefix("/css/", http.FileServer(http.Dir("./public/css"))))
	r.Handle("/js/*", http.StripPrefix("/js/", http.FileServer(http.Dir("./public/js"))))
	r.Handle("/images/*", http.StripPrefix("/images/", http.FileServer(http.Dir("./public/images"))))
	r.Get("/favicon.ico", func(w http.ResponseWriter, r *http.Request) {
		http.ServeFile(w, r, "./public/favicon.ico")
	})
	// // 5. ルーティング定義 (app/routes.php 準拠)
	// // 認証
	// r.Get("/login", authCtrl.Login)
	// r.Post("/login", authCtrl.Login)
	// r.Get("/login/verify", authCtrl.VerifyLogin)
	// r.Post("/login/verify", authCtrl.VerifyLogin)
	// r.Post("/logout", authCtrl.Logout)

	// // デッキAPI
	// r.Route("/api/decks", func(r chi.Router) {
	// 	r.Get("/view", deckCtrl.ViewDeckApi)
	// 	r.Delete("/", deckCtrl.DeleteDeckApi)
	// 	r.Post("/copy", deckCtrl.CopyDeckApi)
	// 	r.Put("/set_public", deckCtrl.SetDeckPublicApi)
	// 	r.Get("/estimate-price", deckCtrl.EstimatePriceApi)
	// })

	// // カードAPI
	// r.Route("/api", func(r chi.Router) {
	// 	r.Get("/cards", cardCtrl.CardsApi)
	// 	r.Get("/master-data", cardCtrl.MasterDataApi)
	// 	r.Get("/cards/combination", cardCtrl.CardCombinationApi)
	// })

	// 6. サーバー起動（Railway等のPORT環境変数に対応）

	fmt.Printf("Server starting on port %s...\n", port)
	if err := http.ListenAndServe(":"+port, r); err != nil {
		log.Fatalf("Server failed: %v", err)
	}
}
