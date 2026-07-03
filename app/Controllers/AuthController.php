<?php namespace Controllers;

use Models\User;
use Models\Database;

require_once __DIR__ . '/../Views/view_helper.php';

class AuthController {
    
    public function showRegisterForm($errors =[]) {
        renderView('auth/register.php', ['errors' => $errors]);
    }

    public function showLoginForm($errors =[]) {
        renderView('auth/login.php', ['errors' => $errors]);
    }

    public function login() {
        try {
            // すべての処理を try ブロックで囲み、エラーを監視します
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $email = $_POST['email'] ?? '';
                $password = $_POST['password'] ?? '';

                $errors = [];

                if (empty($email) || empty($password)) {
                    $errors[] = 'メールアドレスとパスワードを入力してください。';
                }

                if (empty($errors)) {
                    $pdo = \Models\Database::connect();
                    $userModel = new \Models\User($pdo);

                    $user = $userModel->findByEmail($email);

                    if ($user && password_verify($password, $user['password_hash'])) {
                        $_SESSION['user_id'] = $user['user_id'];
                        $_SESSION['username'] = $user['username'];
                        header('Location: /mydecks');
                        exit;
                    } else {
                        $errors[] = 'メールアドレスまたはパスワードが間違っています。';
                    }
                }
                
                $this->showLoginForm($errors);

            } else {
                $this->showLoginForm();
            }

        } catch (\Throwable $e) {
            // ★ルーターによる404丸め込みを阻止し、真のエラーを画面に直接出力します
            echo "<h3>[デバッグ] ログイン処理中にエラーが発生しました</h3>";
            echo "<strong>エラーメッセージ:</strong> " . htmlspecialchars($e->getMessage()) . "<br>";
            echo "<strong>発生場所:</strong> " . htmlspecialchars($e->getFile()) . " の " . $e->getLine() . "行目<br>";
            echo "<h4>スタックトレース:</h4>";
            echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
            exit; // 処理をここで強制停止して表示
        }
    }

    public function logout() {
        session_start();
        session_unset();
        session_destroy();
        header('Location: /login');
        exit;
    }

// --- AuthController.php への追加メソッド ---

    /**
     * アカウント設定画面の表示
     */
    public function showAccountForm() {
        if (!isset($_SESSION['user_id'])) {
            header('Location: /login');
            exit;
        }

        $pdo = \Models\Database::connect();
        // 1. SQLをプレペア
        $stmt = $pdo->prepare("SELECT username, email FROM users WHERE user_id = :user_id");
        
        // 2. bindValueで型を明示して安全にパラメータを割り当て（128行目付近のエラーを回避）
        $stmt->bindValue(':user_id', $_SESSION['user_id'], \PDO::PARAM_INT);
        
        // 3. パラメータなしで実行
        $stmt->execute();
        
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$user) {
            header('Location: /login');
            exit;
        }

        // ビューヘルパーを使ってレンダリング
        renderView('auth/account.php', ['user' => $user]);
    }

    /**
     * アカウント情報の変更処理
     */
    public function updateAccount() {
        if (!isset($_SESSION['user_id'])) {
            header('Location: /login');
            exit;
        }

        $userId = $_SESSION['user_id'];
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $passwordConfirm = $_POST['password_confirm'] ?? '';

        if (empty($username) || empty($email)) {
            $_SESSION['error'] = 'ユーザー名とメールアドレスは必須です。';
            header('Location: /account');
            exit;
        }

        try {
            $pdo = \Models\Database::connect();

            // 1. 重複チェック（bindValueを使い、プレースホルダーとバインドを完全に統一）
            $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = :email AND user_id != :user_id");
            $stmt->bindValue(':email', $email, \PDO::PARAM_STR);
            $stmt->bindValue(':user_id', $userId, \PDO::PARAM_INT);
            $stmt->execute();
            
            if ($stmt->fetch()) {
                $_SESSION['error'] = 'このメールアドレスは既に他のアカウントで使用されています。';
                header('Location: /account');
                exit;
            }

            // 2. 基本情報の更新
            if (!empty($password)) {
                // パスワードを変更する場合
                if ($password !== $passwordConfirm) {
                    $_SESSION['error'] = '新しいパスワードと確認用入力が一致しません。';
                    header('Location: /account');
                    exit;
                }
                if (strlen($password) < 8) {
                    $_SESSION['error'] = 'パスワードは8文字以上で設定してください。';
                    header('Location: /account');
                    exit;
                }

                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                
                // すべて \PDO::PARAM_ 型指定を行い、プレースホルダー名も統一
                $stmt = $pdo->prepare("UPDATE users SET username = :name, email = :email, password_hash = :pass, updated_at = NOW() WHERE user_id = :user_id");
                $stmt->bindValue(':name', $username, \PDO::PARAM_STR);
                $stmt->bindValue(':email', $email, \PDO::PARAM_STR);
                $stmt->bindValue(':pass', $hashedPassword, \PDO::PARAM_STR);
                $stmt->bindValue(':user_id', $userId, \PDO::PARAM_INT);
                $stmt->execute();
            } else {
                // パスワードを変更しない場合
                $stmt = $pdo->prepare("UPDATE users SET username = :name, email = :email, updated_at = NOW() WHERE user_id = :user_id");
                $stmt->bindValue(':name', $username, \PDO::PARAM_STR);
                $stmt->bindValue(':email', $email, \PDO::PARAM_STR);
                $stmt->bindValue(':user_id', $userId, \PDO::PARAM_INT);
                $stmt->execute();
            }

            $_SESSION['success'] = 'アカウント設定を更新しました。';
            header('Location: /account');
            exit;

        } catch (\Exception $e) {
            $_SESSION['error'] = 'エラーが発生しました: ' . $e->getMessage();
            header('Location: /account');
            exit;
        }
    }  

    /**
     * 1. 登録情報を受け取り、認証コードを送信して一時保存する
     */
    public function sendVerificationCode() {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $passwordConfirm = $_POST['password_confirm'] ?? '';

        // 入力値の必須チェック
        if (empty($username) || empty($email) || empty($password)) {
            $_SESSION['error'] = 'すべての項目を入力してください。';
            header('Location: /register');
            exit;
        }

        // パスワード確認チェック
        if ($password !== $passwordConfirm) {
            $_SESSION['error'] = '確認用のパスワードが一致しません。';
            header('Location: /register');
            exit;
        }

        // パスワード文字数チェック
        if (strlen($password) < 8) {
            $_SESSION['error'] = 'パスワードは8文字以上で設定してください。';
            header('Location: /register');
            exit;
        }

        try {
            $pdo = \Models\Database::connect();

            // 重複チェック
            $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = :email");
            $stmt->execute([':email' => $email]);
            if ($stmt->fetch()) {
                $_SESSION['error'] = 'このメールアドレスは既に登録されています。';
                header('Location: /register');
                exit;
            }

            // 直接本登録（usersテーブルにインサート）
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $stmtInsert = $pdo->prepare("INSERT INTO users (username, email, password_hash, created_at, updated_at) VALUES (:name, :email, :pass, NOW(), NOW())");
            $stmtInsert->execute([
                ':name' => $username,
                ':email' => $email,
                ':pass' => $passwordHash
            ]);

            $userId = $pdo->lastInsertId();

            // 登録したユーザー情報でそのままセッションログイン
            $_SESSION['user_id'] = $userId;
            $_SESSION['username'] = $username;

            $_SESSION['success'] = 'アカウント登録が完了しました！';
            header('Location: /mydecks');
            exit;

        } catch (\Exception $e) {
            $_SESSION['error'] = '登録処理中にエラーが発生しました: ' . $e->getMessage();
            header('Location: /register');
            exit;
        }
    }
    
}
