package models

import (
	"database/sql"

	"golang.org/x/crypto/bcrypt"
)

type User struct {
	db *sql.DB
}

func NewUser(db *sql.DB) *User {
	return &User{db: db}
}

/**
 * 新しいユーザーを作成し、データベースに保存します。
 *
 * @param username
 * @param email
 * @param password
 * @return 成功した場合はtrue、失敗した場合はfalse
 */
func (u *User) Create(username string, email string, password string) bool {
	// パスワードをハッシュ化 (PHPの PASSWORD_DEFAULT 相当の bcrypt)
	passwordHash, err := bcrypt.GenerateFromPassword([]byte(password), bcrypt.DefaultCost)
	if err != nil {
		return false
	}

	stmt, err := u.db.Prepare("INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)")
	if err != nil {
		return false
	}
	defer stmt.Close()

	_, err = stmt.Exec(username, email, string(passwordHash))
	return err == nil
}

/**
 * ユーザー名でユーザーを検索します。
 *
 * @param username
 * @return ユーザーデータが見つかった場合は連想配列(map)、見つからない場合はnil (PHPのfalse相当)
 */
func (u *User) FindByUsername(username string) map[string]interface{} {
	stmt, err := u.db.Prepare("SELECT * FROM users WHERE username = ?")
	if err != nil {
		return nil
	}
	defer stmt.Close()

	rows, err := stmt.Query(username)
	if err != nil {
		return nil
	}
	defer rows.Close()

	return fetchAssoc(rows)
}

/**
 * メールアドレスでユーザーを検索します。
 *
 * @param email
 * @return ユーザーデータが見つかった場合は連想配列(map)、見つからない場合はnil (PHPのfalse相当)
 */
// app/Models/User.go
func (u *User) FindByEmail(email string) (*UserEntity, error) {
	stmt, err := u.db.Prepare("SELECT user_id, username, email, password_hash, COALESCE(role, 'user') FROM users WHERE email = ?")
	if err != nil {
		return nil, err
	}
	defer stmt.Close()

	var entity UserEntity
	err = stmt.QueryRow(email).Scan(&entity.UserID, &entity.Username, &entity.Email, &entity.PasswordHash, &entity.Role)
	if err != nil {
		return nil, err
	}
	return &entity, nil
}

/**
 * IDでユーザーを検索します。
 *
 * @param userId
 * @return ユーザーデータが見つかった場合は連想配列(map)、見つからない場合はnil (PHPのfalse相当)
 */
func (u *User) FindById(userId int) map[string]interface{} {
	stmt, err := u.db.Prepare("SELECT * FROM users WHERE user_id = ?")
	if err != nil {
		return nil
	}
	defer stmt.Close()

	rows, err := stmt.Query(userId)
	if err != nil {
		return nil
	}
	defer rows.Close()

	return fetchAssoc(rows)
}

/**
 * PDO::FETCH_ASSOC の動作を再現するヘルパー関数
 */
func fetchAssoc(rows *sql.Rows) map[string]interface{} {
	if !rows.Next() {
		return nil
	}

	columns, err := rows.Columns()
	if err != nil {
		return nil
	}

	values := make([]interface{}, len(columns))
	valuePtrs := make([]interface{}, len(columns))
	for i := range values {
		valuePtrs[i] = &values[i]
	}

	if err := rows.Scan(valuePtrs...); err != nil {
		return nil
	}

	entry := make(map[string]interface{})
	for i, col := range columns {
		val := values[i]
		if b, ok := val.([]byte); ok {
			entry[col] = string(b)
		} else {
			entry[col] = val
		}
	}

	return entry
}

type UserEntity struct {
	UserID       int    `db:"user_id"`
	Username     string `db:"username"`
	Email        string `db:"email"`
	PasswordHash string `db:"password_hash"`
	Role         string `db:"role"`
}

/**
 * IDでユーザーを検索し、UserEntityのポインタを返します（auth.go用）
 */
func (u *User) FindByID(userId int) (*UserEntity, error) {
	stmt, err := u.db.Prepare("SELECT user_id, username, email, password_hash, COALESCE(role, 'user') FROM users WHERE user_id = ?")
	if err != nil {
		return nil, err
	}
	defer stmt.Close()

	var entity UserEntity
	err = stmt.QueryRow(userId).Scan(&entity.UserID, &entity.Username, &entity.Email, &entity.PasswordHash, &entity.Role)
	if err != nil {
		return nil, err
	}
	return &entity, nil
}
