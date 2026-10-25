package models

import (
	"bufio"
	"database/sql"
	"fmt"
	"os"
	"path/filepath"
	"runtime"
	"strings"
	"sync"

	_ "github.com/go-sql-driver/mysql"
)

type Database struct{}

var (
	pdo     *sql.DB
	once    sync.Once
	initErr error
)

// Connect データベース接続を確立し、*sql.DB インスタンスを返します。
func (d *Database) Connect() (*sql.DB, error) {
	return Connect()
}

// 静的メソッドのように直接呼び出すことも可能です: Models.Connect()
func Connect() (*sql.DB, error) {
	once.Do(func() {
		// __DIR__ . '/../../.env' のパス解決
		_, currentFile, _, _ := runtime.Caller(0)
		envPath := filepath.Join(filepath.Dir(currentFile), "../../.env")

		// .env ファイルが存在すれば読み込んで環境変数に設定
		if _, err := os.Stat(envPath); err == nil {
			loadEnv(envPath)
		}

		// 1. Railwayの環境変数(MYSQL...)を最優先し、なければLaravel用(DB_...)やローカルの初期値を使用します
		host := getEnv("MYSQLHOST", getEnv("DB_HOST", "127.0.0.1"))
		port := getEnv("MYSQLPORT", getEnv("DB_PORT", "3306"))
		dbname := getEnv("MYSQLDATABASE", getEnv("DB_DATABASE", "laravel"))
		user := getEnv("MYSQLUSER", getEnv("DB_USERNAME", "root"))
		password := getEnv("MYSQLPASSWORD", getEnv("DB_PASSWORD", ""))
		charset := "utf8mb4"

		// 2. DSN（接続文字列）を組み立てます
		// 形式: username:password@tcp(host:port)/dbname?charset=utf8mb4&parseTime=true
		dsn := fmt.Sprintf("%s:%s@tcp(%s:%s)/%s?charset=%s&parseTime=true",
			user, password, host, port, dbname, charset,
		)

		// 3. データベースへの接続を実行します
		db, err := sql.Open("mysql", dsn)
		if err != nil {
			initErr = fmt.Errorf("データベース接続エラー: %w", err)
			return
		}

		// 実際に疎通確認を行います
		if err := db.Ping(); err != nil {
			initErr = fmt.Errorf("データベース接続エラー: %w", err)
			return
		}

		pdo = db
	})

	if initErr != nil {
		return nil, initErr
	}

	return pdo, nil
}

// 環境変数を取得し、空文字列の場合はデフォルト値を返すヘルパー関数
func getEnv(key, fallback string) string {
	if val := os.Getenv(key); val != "" {
		return val
	}
	return fallback
}

// 簡易的な .env パーサー
func loadEnv(path string) {
	file, err := os.Open(path)
	if err != nil {
		return
	}
	defer file.Close()

	scanner := bufio.NewScanner(file)
	for scanner.Scan() {
		line := strings.TrimSpace(scanner.Text())
		if line == "" || strings.HasPrefix(line, "#") {
			continue
		}
		parts := strings.SplitN(line, "=", 2)
		if len(parts) == 2 {
			key := strings.TrimSpace(parts[0])
			val := strings.Trim(strings.TrimSpace(parts[1]), `"'`)
			os.Setenv(key, val)
		}
	}
}

// app/Models/Database.go に追加（または AuthController 側を Connect() に置換）
func DatabaseConnect() (*sql.DB, error) {
	return Connect()
}
