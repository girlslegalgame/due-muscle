package config

import (
	"fmt"
	"os"

	_ "github.com/go-sql-driver/mysql"
	"github.com/jmoiron/sqlx"
)

var DB *sqlx.DB

func ConnectDB() (*sqlx.DB, error) {
	// 環境変数またはデフォルト値から接続文字列を生成
	user := os.Getenv("DB_USER")
	if user == "" { user = "root" }
	pass := os.Getenv("DB_PASSWORD")
	host := os.Getenv("DB_HOST")
	if host == "" { host = "127.0.0.1" }
	port := os.Getenv("DB_PORT")
	if port == "" { port = "3306" }
	name := os.Getenv("DB_NAME")
	if name == "" { name = "duel_masters" }

	dsn := fmt.Sprintf("%s:%s@tcp(%s:%s)/%s?parseTime=true&charset=utf8mb4", user, pass, host, port, name)
	db, err := sqlx.Connect("mysql", dsn)
	if err != nil {
		return nil, err
	}

	DB = db
	return db, nil
}