package middleware

import (
	"crypto/hmac"
	"crypto/sha256"
	"encoding/hex"
	"fmt"
	"net/http"
	"strconv"
	"strings"
	"time"

	models "deck-maker/app/Models"

	"github.com/gin-contrib/sessions"
	"github.com/gin-gonic/gin"
)

// AutoLogin: Cookie (known_device_*) があればセッションを自動復元し、有効期限を延長する
func AutoLogin(userModel *models.User) gin.HandlerFunc {
	return func(c *gin.Context) {
		session := sessions.Default(c)
		uidVal := session.Get("user_id")

		// 1. 既にセッションが存在する場合: Cookieの有効期限を30日延長
		if uidVal != nil {
			userID := uidVal.(int)
			user, _ := userModel.FindByID(userID)
			if user != nil {
				refreshDeviceCookie(c, user)
			}
			c.Next()
			return
		}

		// 2. セッション切れの場合: known_device_ Cookieを探して自動ログイン
		for _, cookie := range c.Request.Cookies() {
			if strings.HasPrefix(cookie.Name, "known_device_") {
				uidStr := strings.TrimPrefix(cookie.Name, "known_device_")
				userID, err := strconv.Atoi(uidStr)
				if err != nil || userID <= 0 {
					continue
				}

				parts := strings.SplitN(cookie.Value, ":", 2)
				if len(parts) != 2 {
					continue
				}

				expires, err := strconv.ParseInt(parts[0], 10, 64)
				if err != nil || time.Now().Unix() > expires {
					continue
				}

				user, err := userModel.FindByID(userID)
				if err != nil || user == nil {
					continue
				}

				expected := generateToken(user.UserID, user.PasswordHash, expires)
				if hmac.Equal([]byte(expected), []byte(parts[1])) {
					// セッション復元
					session.Set("user_id", user.UserID)
					session.Set("username", user.Username)
					session.Set("role", user.Role)
					session.Save()

					// Cookieを30日延長
					refreshDeviceCookie(c, user)
					break
				}
			}
		}
		c.Next()
	}
}

// AuthRequired: ログイン必須チェック (Laravelの auth ミドルウェア相当)
func AuthRequired() gin.HandlerFunc {
	return func(c *gin.Context) {
		session := sessions.Default(c)
		if session.Get("user_id") == nil {
			// APIリクエストの場合は 401 JSON、通常ページは /login へリダイレクト
			if strings.HasPrefix(c.Request.URL.Path, "/api/") {
				c.AbortWithStatusJSON(http.StatusUnauthorized, gin.H{"error": "ログインが必要です。"})
			} else {
				c.Redirect(http.StatusFound, "/login")
				c.Abort()
			}
			return
		}
		c.Next()
	}
}

// AdminOrDeveloperRequired: 管理者/開発者権限チェック (Laravelの Gate/Policy 相当)
func AdminOrDeveloperRequired() gin.HandlerFunc {
	return func(c *gin.Context) {
		session := sessions.Default(c)
		roleVal := session.Get("role")
		role, _ := roleVal.(string)

		if role != "admin" && role != "developer" {
			if strings.HasPrefix(c.Request.URL.Path, "/api/") {
				c.AbortWithStatusJSON(http.StatusForbidden, gin.H{"error": "この操作を行う権限がありません。"})
			} else {
				c.Redirect(http.StatusFound, "/mydecks")
				c.Abort()
			}
			return
		}
		c.Next()
	}
}

func generateToken(userID int, passwordHash string, expires int64) string {
	data := fmt.Sprintf("%d_%d_dm_deck_app_device_salt_9876", userID, expires)
	h := hmac.New(sha256.New, []byte(passwordHash))
	h.Write([]byte(data))
	return hex.EncodeToString(h.Sum(nil))
}

func refreshDeviceCookie(c *gin.Context, user *models.UserEntity) {
	expires := time.Now().Add(30 * 24 * time.Hour).Unix()
	token := generateToken(user.UserID, user.PasswordHash, expires)
	val := fmt.Sprintf("%d:%s", expires, token)
	c.SetCookie(fmt.Sprintf("known_device_%d", user.UserID), val, 30*24*3600, "/", "", true, true)
}
