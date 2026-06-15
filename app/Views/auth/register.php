<h2>ユーザー登録</h2>

<?php if (!empty($errors)): ?>
    <div style="color: red;">
        <ul>
            <?php foreach ($errors as $error): ?>
                <li><?php echo htmlspecialchars($error); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form action="/register" method="POST">
    <div>
        <label>ユーザー名:<br><input type="text" name="username" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required></label>
    </div>
    <div>
        <label>メールアドレス:<br><input type="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required></label>
    </div>
    <div>
        <label>パスワード:<br><input type="password" name="password" required></label>
    </div>
    <div>
        <label>パスワード確認:<br><input type="password" name="password_confirm" required></label>
    </div>
    <button type="submit">登録</button>
</form>

<p>既にアカウントをお持ちですか？ <a href="/login">ログインはこちら</a></p>
