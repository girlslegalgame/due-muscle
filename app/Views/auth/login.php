<?php require_once __DIR__ . '/../layouts/app.php'; ?>

<?php $layoutContent = ob_get_clean(); // ob_start()で取得した内容をクリア ?>

<main>
    <h2>ログイン</h2>

    <?php if (!empty($errors)): ?>
        <div style="color: red;">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form action="/login" method="POST">
        <div>
            <label for="email">メールアドレス:</label>
            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
        </div>
        <div>
            <label for="password">パスワード:</label>
            <input type="password" id="password" name="password" required>
        </div>
        <button type="submit">ログイン</button>
    </form>

    <p>アカウントをお持ちでないですか？ <a href="/register">新規登録はこちら</a></p>
</main>

<?php
$mainContent = ob_get_contents();
ob_end_clean();
require_once __DIR__ . '/../layouts/app.php';
echo $mainContent;
?>
