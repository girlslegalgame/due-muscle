<?php namespace Controllers;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

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

    /**
     * Brevo API (HTTPS) を使用したメール送信
     * (SMTPが遮断される環境でも100%確実に届きます)
     */
    private function sendEmail($to, $subject, $body) {
        // 開発用にログ出力は残しておきます
        error_log("【デバッグ】Brevo APIからメール送信を試みます。送信先: $to, 件名: $subject");

        $apiKey = getenv('BREVO_API_KEY');
        if (empty($apiKey)) {
            error_log("メール送信エラー: BREVO_API_KEY が環境変数に設定されていません。");
            return false;
        }

        // 送信元のメールアドレス（Brevoに登録したご自身のアドレス）
        $fromEmail = getenv('SMTP_FROM') ?: 'your-brevo-registered-email@gmail.com'; 

        $url = 'https://api.brevo.com/v3/smtp/email';

        // 送信データの設定（JSON形式）
        $data = [
            'sender' => [
                'name' => 'デュエマデッキメーカー',
                'email' => $fromEmail
            ],
            'to' => [
                [
                    'email' => $to
                ]
            ],
            'subject' => $subject,
            'htmlContent' => $body
        ];

        // cURLによるHTTPS POST送信処理
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'api-key: ' . $apiKey,
            'Content-Type: application/json',
            'Accept: application/json'
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            error_log("Brevo API Connection Error: " . $curlError);
            return false;
        }

        // ステータスコードが2xxであれば送信成功
        if ($httpCode >= 200 && $httpCode < 300) {
            error_log("Brevo API経由でのメール送信に成功しました。");
            return true;
        } else {
            error_log("Brevo API Error (HTTP $httpCode): " . $response);
            return false;
        }
    }


    /**
     * ログイン処理（1ステップ目：パスワード検証と認証コード送信）
     */
    public function login() {
        try {
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
                        
                        // ★修正：既知の端末（Cookieあり）であるかチェック
                        if ($this->isKnownDevice($user)) {
                            // 認証コードをスキップしてログイン状態にする
                            $_SESSION['user_id'] = $user['user_id'];
                            $_SESSION['username'] = $user['username'];
                            if (function_exists('fastcgi_finish_request')) {
                                fastcgi_finish_request(); // ブラウザとの接続を先に切断し、メール送信は裏で実行する
                            }
                            header('Location: /mydecks');
                            exit;
                        }

                        // 未知の端末の場合は、これまで通り認証コードを生成して送信
                        $code = sprintf('%06d', mt_rand(0, 999999));
                        $expiresAt = time() + 600; // 10分有効

                        $_SESSION['temp_login'] = [
                            'user_id' => $user['user_id'],
                            'username' => $user['username'],
                            'email' => $user['email'],
                            'code' => $code,
                            'expires_at' => $expiresAt
                        ];

                        $subject = "【デュエマデッキメーカー】ログイン認証コード";
                        $body = "<p>ログインを完了するには、制限時間内に以下の認証コードを入力してください。</p>";
                        $body .= "<h2 style='color:#007bff; letter-spacing:2px;'>{$code}</h2>";
                        $body .= "<p>有効期限: 10分間</p>";
                        
                        $this->sendEmail($user['email'], $subject, $body);

                        $_SESSION['success'] = 'メールアドレスにログイン用の認証コードを送信しました。';
                        header('Location: /login/verify');
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
            echo "<h3>[デバッグ] ログイン処理中にエラーが発生しました</h3>";
            echo "<strong>エラーメッセージ:</strong> " . htmlspecialchars($e->getMessage()) . "<br>";
            echo "<strong>発生場所:</strong> " . htmlspecialchars($e->getFile()) . " の " . $e->getLine() . "行目<br>";
            exit;
        }
    }

    /**
     * ログイン認証コード入力画面の表示
     */
    public function showLoginVerifyForm() {
        if (!isset($_SESSION['temp_login'])) {
            $_SESSION['error'] = 'ログイン情報のセッションが切れました。最初からログインし直してください。';
            header('Location: /login');
            exit;
        }

        renderView('auth/verify.php', ['action_url' => '/login/verify']);
    }

    /**
     * ログイン認証コードの確認処理
     */
    public function verifyLogin() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /login');
            exit;
        }

        $inputCode = trim($_POST['code'] ?? '');

        if (!isset($_SESSION['temp_login'])) {
            $_SESSION['error'] = 'セッションが存在しません。ログインをやり直してください。';
            header('Location: /login');
            exit;
        }

        $tempLogin = $_SESSION['temp_login'];

        if (time() > $tempLogin['expires_at']) {
            unset($_SESSION['temp_login']);
            $_SESSION['error'] = '認証コードの期限が切れました。ログインを最初からやり直してください。';
            header('Location: /login');
            exit;
        }

        if ($inputCode !== $tempLogin['code']) {
            $_SESSION['error'] = '認証コードが正しくありません。';
            header('Location: /login/verify');
            exit;
        }

        // 正式ログイン完了
        $_SESSION['user_id'] = $tempLogin['user_id'];
        $_SESSION['username'] = $tempLogin['username'];

        // ★修正：認証に成功したため、この端末（ブラウザ）を30日間記憶する
        try {
            $pdo = \Models\Database::connect();
            $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE user_id = :user_id");
            $stmt->execute([':user_id' => $tempLogin['user_id']]);
            $user = $stmt->fetch();
            if ($user) {
                $this->saveDeviceCookie($tempLogin['user_id'], $user['password_hash']);
            }
        } catch (\Exception $e) {
            // Cookie保存時の軽微なエラーでログイン自体が失敗するのを防ぐため、エラーはログに留める
            error_log("Device Cookie Save Error: " . $e->getMessage());
        }
        
        unset($_SESSION['temp_login']);

        header('Location: /mydecks');
        exit;
    }

    public function logout() {
        // ★修正：自動ログイン用のCookie（known_device_から始まるCookie）をクリアする
        if (isset($_SESSION['user_id'])) {
            $cookieName = "known_device_" . $_SESSION['user_id'];
            if (isset($_COOKIE[$cookieName])) {
                setcookie($cookieName, '', [
                    'expires' => time() - 3600,
                    'path' => '/',
                    'secure' => true,
                    'httponly' => true,
                    'samesite' => 'Lax'
                ]);
            }
        }

        // すでに開始されているセッションをクリア・破棄します
        session_unset();
        session_destroy();
        header('Location: /login');
        exit;
    }

    /**
     * 新規登録処理（1ステップ目：入力検証と認証コード送信）
     */
    public function sendVerificationCode() {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $passwordConfirm = $_POST['password_confirm'] ?? '';

        if (empty($username) || empty($email) || empty($password)) {
            $_SESSION['error'] = 'すべての項目を入力してください。';
            header('Location: /register');
            exit;
        }

        if ($password !== $passwordConfirm) {
            $_SESSION['error'] = '確認用のパスワードが一致しません。';
            header('Location: /register');
            exit;
        }

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
                $_SESSION['error'] = 'すでに使用されているメールアドレスです。';
                header('Location: /register');
                exit;
            }

            // 6桁の認証コード生成
            $code = sprintf('%06d', mt_rand(0, 999999));
            $expiresAt = time() + 600; // 10分有効

            // 登録データをセッションに一時保存（パスワードはハッシュ化）
            $_SESSION['temp_register'] = [
                'username' => $username,
                'email' => $email,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'code' => $code,
                'expires_at' => $expiresAt
            ];

            // 新規登録用認証コードを送信
            $subject = "【デュエマデッキメーカー】アカウント新規作成 認証コード";
            $body = "<p>アカウント登録を完了するには、制限時間内に以下の認証コードを入力してください。</p>";
            $body .= "<h2 style='color:#007bff; letter-spacing:2px;'>{$code}</h2>";
            $body .= "<p>有効期限: 10分間</p>";

            $this->sendEmail($email, $subject, $body);

            $_SESSION['success'] = 'メールアドレスに新規登録用の認証コードを送信しました。';
            header('Location: /register/verify');
            exit;

        } catch (\Exception $e) {
            $_SESSION['error'] = '登録処理中にエラーが発生しました: ' . $e->getMessage();
            header('Location: /register');
            exit;
        }
    }

    /**
     * 新規登録用認証コード入力画面の表示
     */
    public function showVerifyForm() {
        if (!isset($_SESSION['temp_register'])) {
            $_SESSION['error'] = '登録の有効期限が切れたか、無効なアクセスです。';
            header('Location: /register');
            exit;
        }

        renderView('auth/verify.php', ['action_url' => '/register/verify']);
    }

    /**
     * 新規登録用認証コードの確認処理（本登録）
     */
    public function verifyRegister() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /register');
            exit;
        }

        $inputCode = trim($_POST['code'] ?? '');

        if (!isset($_SESSION['temp_register'])) {
            $_SESSION['error'] = '登録情報のセッションが切れました。最初から登録し直してください。';
            header('Location: /register');
            exit;
        }

        $tempUser = $_SESSION['temp_register'];

        // 有効期限検証
        if (time() > $tempUser['expires_at']) {
            unset($_SESSION['temp_register']);
            $_SESSION['error'] = '認証コードの期限（10分）が切れています。最初からやり直してください。';
            header('Location: /register');
            exit;
        }

        // コード一致検証
        if ($inputCode !== $tempUser['code']) {
            $_SESSION['error'] = '認証コードが正しくありません。';
            header('Location: /register/verify');
            exit;
        }

        try {
            $pdo = \Models\Database::connect();

            // 最終重複チェック（送信中に同一アドレスで登録された場合のケア）
            $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = :email");
            $stmt->execute([':email' => $tempUser['email']]);
            if ($stmt->fetch()) {
                unset($_SESSION['temp_register']);
                $_SESSION['error'] = 'すでに登録されているメールアドレスです。';
                header('Location: /register');
                exit;
            }

            // DBに正式登録
            $stmtInsert = $pdo->prepare("INSERT INTO users (username, email, password_hash, created_at, updated_at) VALUES (:name, :email, :pass, NOW(), NOW())");
            $stmtInsert->execute([
                ':name' => $tempUser['username'],
                ':email' => $tempUser['email'],
                ':pass' => $tempUser['password_hash']
            ]);

            $userId = $pdo->lastInsertId();

            // ログインセッションの確立
            $_SESSION['user_id'] = $userId;
            $_SESSION['username'] = $tempUser['username'];

            // ==========================================
            // ★【追加箇所】登録を行ったこの端末を「既知の端末」として保存します
            // ==========================================
            $this->saveDeviceCookie($userId, $tempUser['password_hash']);
            // ==========================================

            unset($_SESSION['temp_register']); // 一時データの消去

            $_SESSION['success'] = 'アカウント登録が完了しました！';
            header('Location: /mydecks');
            exit;

        } catch (\Exception $e) {
            $_SESSION['error'] = '登録完了処理中にエラーが発生しました: ' . $e->getMessage();
            header('Location: /register');
            exit;
        }
    }

    /**
     * アカウント設定画面の表示
     */
    public function showAccountForm() {
        if (!isset($_SESSION['user_id'])) {
            header('Location: /login');
            exit;
        }

        $pdo = \Models\Database::connect();
        $stmt = $pdo->prepare("SELECT username, email FROM users WHERE user_id = :user_id");
        $stmt->bindValue(':user_id', $_SESSION['user_id'], \PDO::PARAM_INT);
        $stmt->execute();
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$user) {
            header('Location: /login');
            exit;
        }

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

            $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = :email AND user_id != :user_id");
            $stmt->bindValue(':email', $email, \PDO::PARAM_STR);
            $stmt->bindValue(':user_id', $userId, \PDO::PARAM_INT);
            $stmt->execute();
            
            if ($stmt->fetch()) {
                $_SESSION['error'] = 'このメールアドレスは既に他のアカウントで使用されています。';
                header('Location: /account');
                exit;
            }

            if (!empty($password)) {
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
                
                $stmt = $pdo->prepare("UPDATE users SET username = :name, email = :email, password_hash = :pass, updated_at = NOW() WHERE user_id = :user_id");
                $stmt->bindValue(':name', $username, \PDO::PARAM_STR);
                $stmt->bindValue(':email', $email, \PDO::PARAM_STR);
                $stmt->bindValue(':pass', $hashedPassword, \PDO::PARAM_STR);
                $stmt->bindValue(':user_id', $userId, \PDO::PARAM_INT);
                $stmt->execute();
            } else {
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
     * ユーザーIDとパスワードハッシュからセキュアな端末識別用トークンを生成
     */
    private function generateDeviceToken($userId, $passwordHash) {
        $salt = "dm_deck_app_device_salt_9876"; // 任意のソルト文字列
        return hash_hmac('sha256', $userId . '_' . $salt, $passwordHash);
    }

    /**
     * 端末識別用Cookieをブラウザに保存する（30日間有効）
     */
    private function saveDeviceCookie($userId, $passwordHash) {
        $token = $this->generateDeviceToken($userId, $passwordHash);
        $cookieName = "known_device_" . $userId;
        
        // 30日間有効。HttpOnly属性によりJavaScriptからの盗み見を防止
        setcookie($cookieName, $token, [
            'expires' => time() + (30 * 24 * 60 * 60),
            'path' => '/',
            'secure' => true,      // HTTPS環境（Railwayなど）で機能
            'httponly' => true,    // セキュリティ対策（XSS防止）
            'samesite' => 'Lax'
        ]);
    }

    /**
     * この端末が「既知の端末」であるかチェックする
     */
    private function isKnownDevice($user) {
        $cookieName = "known_device_" . $user['user_id'];
        if (!isset($_COOKIE[$cookieName])) {
            return false;
        }
        
        $expectedToken = $this->generateDeviceToken($user['user_id'], $user['password_hash']);
        // タイミング攻撃を防ぐため hash_equals で比較
        return hash_equals($expectedToken, $_COOKIE[$cookieName]);
    }

    /**
     * Cookieからセッションを自動復元、またはアクセス時にCookieの有効期限を30日後に延長する
     * （共通のログインチェック処理や、認証が必要なページの遷移前に呼び出してください）
     */
    public function tryAutoLogin() {
        // 既にセッションがある場合は、アクセスがあったためCookieの寿命を30日に延長（スライド）する
        if (isset($_SESSION['user_id'])) {
            try {
                $pdo = \Models\Database::connect();
                $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE user_id = :user_id");
                $stmt->execute([':user_id' => $_SESSION['user_id']]);
                $user = $stmt->fetch();
                if ($user) {
                    $this->saveDeviceCookie($_SESSION['user_id'], $user['password_hash']);
                }
            } catch (\Exception $e) {
                error_log("Device Cookie Refresh Error: " . $e->getMessage());
            }
            return true;
        }

        // セッションがない場合、ブラウザのCookieから自動ログインを試みる
        foreach ($_COOKIE as $key => $value) {
            if (strpos($key, 'known_device_') === 0) {
                $userId = (int)str_replace('known_device_', '', $key);
                if ($userId > 0) {
                    try {
                        $pdo = \Models\Database::connect();
                        $stmt = $pdo->prepare("SELECT user_id, username, password_hash FROM users WHERE user_id = :user_id");
                        $stmt->execute([':user_id' => $userId]);
                        $user = $stmt->fetch();

                        if ($user && hash_equals($this->generateDeviceToken($user['user_id'], $user['password_hash']), $value)) {
                            // セッションを復元
                            $_SESSION['user_id'] = $user['user_id'];
                            $_SESSION['username'] = $user['username'];
                            
                            // Cookieの有効期限をさらに30日後に延長
                            $this->saveDeviceCookie($user['user_id'], $user['password_hash']);
                            return true;
                        }
                    } catch (\Exception $e) {
                        error_log("Auto Login Error: " . $e->getMessage());
                    }
                }
            }
        }
        return false;
    }
}