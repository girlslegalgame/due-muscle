<?php
// 1. 最初に出力バッファリングを明示的に開始します
ob_start();
?>

<style>
    /* ★共通のフッター（footerタグ、.footerクラス、#footerのID）を非表示にする */
    footer, .footer, #footer {
        display: none !important;
    }

    .auth-container {
        max-width: 420px;
        width: 100%;
        margin: 60px auto;
        padding: 35px;
        background: #fff;
        border: 1px solid #ddd;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        box-sizing: border-box;
    }

    .auth-container h2 {
        margin-top: 0;
        margin-bottom: 25px;
        font-size: 1.5rem;
        border-bottom: 2px solid #007bff;
        padding-bottom: 8px;
        color: #333;
        text-align: center;
    }

    .form-group {
        margin-bottom: 20px;
    }

    .form-group label {
        display: block;
        font-weight: bold;
        font-size: 0.9rem;
        margin-bottom: 6px;
        color: #555;
    }

    .form-group input[type="email"],
    .form-group input[type="password"] {
        width: 100%;
        padding: 10px 12px;
        border: 1px solid #ccc;
        border-radius: 4px;
        box-sizing: border-box;
        font-size: 14px;
        transition: border-color 0.2s, box-shadow 0.2s;
    }

    .form-group input:focus {
        border-color: #007bff;
        outline: none;
        box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.15);
    }

    .btn-submit {
        width: 100%;
        padding: 12px;
        background-color: #007bff;
        color: #fff;
        border: none;
        border-radius: 5px;
        font-weight: bold;
        cursor: pointer;
        font-size: 15px;
        margin-top: 10px;
        transition: background-color 0.2s;
    }

    .btn-submit:hover {
        background-color: #0056b3;
    }

    /* エラーメッセージ */
    .alert {
        padding: 12px;
        border-radius: 4px;
        margin-bottom: 20px;
        font-size: 0.85rem;
        font-weight: bold;
    }
    
    .alert-danger {
        background-color: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }

    .auth-footer {
        text-align: center;
        margin-top: 25px;
        font-size: 0.9rem;
        color: #666;
    }

    .auth-footer a {
        color: #007bff;
        text-decoration: none;
        font-weight: bold;
    }

    .auth-footer a:hover {
        text-decoration: underline;
    }
</style>

<main class="auth-container">
    <h2>ログイン</h2>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul style="margin: 0; padding-left: 15px;">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form action="/login" method="POST">
        <div class="form-group">
            <label for="email">メールアドレス</label>
            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" placeholder="example@example.com" required>
        </div>
        <div class="form-group">
            <label for="password">パスワード</label>
            <input type="password" id="password" name="password" placeholder="パスワードを入力してください" required>
        </div>
        <button type="submit" class="btn-submit">ログイン</button>
    </form>

    <div class="auth-footer">
        <p>アカウントをお持ちでないですか？ <br><a href="/register">新規登録はこちら</a></p>
    </div>
</main>

<?php
// 2. これまでにバッファされたHTML（styleタグやmainタグなど）を変数 $content に代入し、バッファを終了します
$content = ob_get_clean();
require_once __DIR__ . '/../layouts/app.php';
?>