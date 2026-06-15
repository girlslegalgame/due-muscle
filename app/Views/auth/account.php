<!-- app/Views/auth/account.php -->
<style>
    .account-container {
        max-width: 500px;
        width: 100%;
        margin: 40px auto;
        padding: 30px;
        background: #fff;
        border: 1px solid #ddd;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        box-sizing: border-box;
    }

    .account-container h2 {
        margin-top: 0;
        margin-bottom: 25px;
        font-size: 1.5rem;
        border-bottom: 2px solid #007bff;
        padding-bottom: 8px;
        color: #333;
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

    .form-group input[type="text"],
    .form-group input[type="email"],
    .form-group input[type="password"] {
        width: 100%;
        padding: 10px;
        border: 1px solid #ccc;
        border-radius: 4px;
        box-sizing: border-box;
        font-size: 14px;
    }

    .btn-save {
        width: 100%;
        padding: 12px;
        background-color: #28a745;
        color: #fff;
        border: none;
        border-radius: 5px;
        font-weight: bold;
        cursor: pointer;
        font-size: 15px;
        margin-top: 10px;
    }
    .btn-save:hover {
        background-color: #218838;
    }

    /* 成功・エラーメッセージ */
    .alert {
        padding: 10px;
        border-radius: 4px;
        margin-bottom: 15px;
        font-size: 0.9rem;
        font-weight: bold;
    }
    .alert-success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
    .alert-danger { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

    /* ★右下のログアウトセクション */
    .logout-section {
        display: flex;
        justify-content: flex-end; /* 右寄せ */
        margin-top: 30px;          /* 「設定を保存する」ボタンとの間にしっかり30pxの余白を確保 */
    }
    
    .btn-logout {
        background: none;
        border: none;
        color: #dc3545;
        font-weight: bold;
        cursor: pointer;
        font-size: 0.95rem;
        padding: 5px 10px;
        text-decoration: underline;
    }
    .btn-logout:hover {
        color: #bd2130;
    }
</style>

<div class="account-container">
    <h2>アカウント設定</h2>

    <!-- 通知メッセージの表示（セッション等のエラー・成功メッセージ用） -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <!-- アカウント更新フォーム -->
    <form action="/account" method="POST">
        <!-- ハンドルネーム -->
        <div class="form-group">
            <label for="username">ハンドルネーム</label>
            <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($user['username'] ?? ''); ?>" required placeholder="新しいハンドルネーム">
        </div>

        <!-- メールアドレス -->
        <div class="form-group">
            <label for="email">メールアドレス</label>
            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required placeholder="example@example.com">
        </div>

        <hr style="border: 0; border-top: 1px solid #eee; margin: 25px 0;">

        <!-- パスワード（変更時のみ入力） -->
        <div class="form-group">
            <label for="password">新しいパスワード</label>
            <input type="password" id="password" name="password" placeholder="変更する場合のみ入力（8文字以上）">
        </div>

        <div class="form-group">
            <label for="password_confirm">新しいパスワード（確認用）</label>
            <input type="password" id="password_confirm" name="password_confirm" placeholder="新しいパスワードを再入力">
        </div>

        <button type="submit" class="btn-save">設定を保存する</button>
    </form>

    <!-- ★右下：ログアウト用フォーム (POST) -->
    <div class="logout-section">
        <form action="/logout" method="POST" onsubmit="return confirm('本当にログアウトしますか？');">
            <button type="submit" class="btn-logout">ログアウト</button>
        </form>
    </div>
</div>