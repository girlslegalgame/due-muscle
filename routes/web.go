package routes

import (
	controllers "deck-maker/app/Controllers"
	views "deck-maker/app/Views"
	deckViews "deck-maker/app/Views/deck" // ★ 追加

	"github.com/gin-gonic/gin"
)

func RegisterRoutes(r *gin.Engine) {
	cardCtrl := &controllers.CardController{}
	deckCtrl := &controllers.DeckController{}
	authCtrl := &controllers.AuthController{}
	homeCtrl := &controllers.HomeController{}
	adminCtrl := &controllers.AdminController{} // ★ 追加

	// ★ 静的ファイルの配信設定（CSS, JS, 画像）
	r.Static("/css", "./public/css")
	r.Static("/js", "./public/js")
	r.Static("/images", "./public/images")

	// トップページ
	r.GET("/", gin.WrapF(homeCtrl.Index))

	// ★ 追加: ヘルプページ
	r.GET("/help", func(c *gin.Context) {
		views.RenderView(c.Writer, c.Request, "help/index.html", map[string]interface{}{})
	})
	r.GET("/help/search", gin.WrapF(deckViews.HelpSearchHandler))

	// マイデッキ一覧などの既存ルート
	r.GET("/account", gin.WrapF(authCtrl.ShowAccountForm))
	r.GET("/mydecks", gin.WrapF(deckCtrl.MyDecks))
	r.GET("/search", gin.WrapF(deckCtrl.Search))
	r.GET("/decks/new", gin.WrapF(deckCtrl.Create))
	r.GET("/decks/playtest", gin.WrapF(deckCtrl.Playtest))

	// API: カード関連 (CardController)
	api := r.Group("/api")
	{
		api.GET("/cards", gin.WrapF(cardCtrl.CardsApi))
		api.GET("/cards/combination", gin.WrapF(cardCtrl.CardCombinationApi))
		api.GET("/cards/versions", gin.WrapF(cardCtrl.CardVersionsApi))
		api.GET("/cards/help-search", gin.WrapF(cardCtrl.HelpSearchApi))
		api.GET("/cards/help-detail", gin.WrapF(cardCtrl.HelpDetailApi)) // ★ 追加
		api.POST("/cards/help-update", gin.WrapF(cardCtrl.HelpUpdateApi))

		// API: デッキ関連 (DeckController)
		api.POST("/decks", gin.WrapF(deckCtrl.StoreDeckApi))
		api.PUT("/decks", gin.WrapF(deckCtrl.UpdateDeckApi)) // 修正箇所
		api.DELETE("/decks", gin.WrapF(deckCtrl.DeleteDeckApi))
		api.GET("/decks/view", gin.WrapF(deckCtrl.ViewDeckApi))
		api.POST("/decks/copy", gin.WrapF(deckCtrl.CopyDeckApi))
		api.PUT("/decks/set_public", gin.WrapF(deckCtrl.SetDeckPublicApi))
		api.POST("/decks/report", gin.WrapF(deckCtrl.ReportDeckApi))
		api.GET("/decks/estimate-price", gin.WrapF(deckCtrl.EstimatePriceApi))
	}

	// 認証関連 (AuthController)
	r.POST("/account", gin.WrapF(authCtrl.UpdateAccount))
	r.POST("/login", gin.WrapF(authCtrl.Login))
	r.POST("/logout", gin.WrapF(authCtrl.Logout))
	r.POST("/register", gin.WrapF(authCtrl.SendVerificationCode))

	// ★ 管理者専用ページ
	r.GET("/admin", gin.WrapF(adminCtrl.Index))

	// API関連
	api := r.Group("/api")
	{
		// ★ 管理者専用API
		adminApi := api.Group("/admin")
		{
			adminApi.GET("/user-decks", gin.WrapF(adminCtrl.UserDecksApi))
			adminApi.POST("/penalty", gin.WrapF(adminCtrl.ApplyPenaltyApi))
			adminApi.POST("/users/update", gin.WrapF(adminCtrl.UpdateUserApi))
			adminApi.POST("/users/role", gin.WrapF(adminCtrl.UpdateUserRoleApi))
			adminApi.POST("/users/delete", gin.WrapF(adminCtrl.DeleteUserApi))
			adminApi.POST("/reports/action", gin.WrapF(adminCtrl.HandleReportApi))
			adminApi.POST("/reports/resolve-user", gin.WrapF(adminCtrl.ResolveUserReportApi))
			adminApi.POST("/reports/clean", gin.WrapF(adminCtrl.CleanReportsApi))
			adminApi.POST("/notifications", gin.WrapF(adminCtrl.SendNotificationApi))
			adminApi.POST("/decks/bulk-private", gin.WrapF(adminCtrl.BulkMakeDecksPrivateApi))
		}
}
