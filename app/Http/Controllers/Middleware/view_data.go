// app/middleware/view_data.go
package middleware

import (
	"github.com/gin-contrib/sessions"
	"github.com/gin-gonic/gin"
)

func SetGlobalViewData() gin.HandlerFunc {
	return func(c *gin.Context) {
		session := sessions.Default(c)
		uid := session.Get("user_id")
		role := session.Get("role")

		c.Set("isLoggedIn", uid != nil)
		c.Set("role", role)
		c.Next()
	}
}
