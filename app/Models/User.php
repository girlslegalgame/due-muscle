<?php namespace Models;

use PDO;

class User {
    private $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * 新しいユーザーを作成し、データベースに保存します。
     *
     * @param string $username
     * @param string $email
     * @param string $password
     * @return bool 成功した場合はtrue、失敗した場合はfalse
     */
    public function create(string $username, string $email, string $password): bool {
        $passwordHash = password_hash($password, PASSWORD_DEFAULT); // パスワードをハッシュ化

        $stmt = $this->pdo->prepare("INSERT INTO users (username, email, password_hash) VALUES (:username, :email, :password_hash)");

        return $stmt->execute([
            ':username' => $username,
            ':email' => $email,
            ':password_hash' => $passwordHash,
        ]);
    }
    /**
     * ユーザー名でユーザーを検索します。
     *
     * @param string $username
     * @return array|false
     */
    public function findByUsername(string $username) {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE username = :username");
        $stmt->execute([':username' => $username]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * メールアドレスでユーザーを検索します。
     *
     * @param string $email
     * @return array|false ユーザーデータが見つかった場合は連想配列、見つからない場合はfalse
     */
    public function findByEmail(string $email) {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE email = :email");
        $stmt->execute([':email' => $email]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * IDでユーザーを検索します。
     *
     * @param int $userId
     * @return array|false ユーザーデータが見つかった場合は連想配列、見つからない場合はfalse
     */
    public function findById(int $userId) {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE user_id = :user_id");
        $stmt->execute([':user_id' => $userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
