<!-- app/Views/auth/verify.php -->
<style>
    .verify-container {
        max-width: 400px;
        width: 100%;
        margin: 60px auto;
        padding: 30px;
        background: #fff;
        border: 1px solid #ddd;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        box-sizing: border-box;
        text-align: center;
    }

    .verify-container h2 {
        margin-top: 0;
        margin-bottom: 15px;
        font-size: 1.4rem;
        color: #333;
    }

    .verify-container p {
        font-size: 0.9rem;
        color: #666;
        line-height: 1.5;
        margin-bottom: 25px;
    }

    .form-group {
        margin-bottom: 20px;
    }

    .input-code {
        width: 100%;
        padding: 12px;
        border: 2px solid #007bff;
        border-radius: 6px;
        font-size: 20px;
        font-weight: bold;
        letter-spacing: 4px; /* コードの間隔を広げて見やすくする */
        text-align: center;
        box-sizing: border-box;
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
    }
    .btn-submit:hover {
        background-color: #0056b3;
    }

    /* 通知メッセージ */
    .alert {
        padding: 10px;
        border-radius: 4px;
        margin-bottom: 20px;
        font-size: 0.85rem;
        font-weight: bold;
        text-align: left;
    }
    .alert-success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
    .alert-danger { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

    .back-link {
        display: inline-block;
        margin-top: 20px;
        font-size: 0.85rem;
        color: #007bff;
        text-decoration: none;
    }
    .back-link:hover {
        text-decoration: underline;
    }
</style>

<div class="verify-container">
    <h2>認証コードの入力</h2>
    <p>送信された6桁の認証コードを制限時間（10分）以内に入力してください。</p>

    <!-- メッセージ通知 -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <form action="<?php echo htmlspecialchars($action_url ?? '/register/verify'); ?>" method="POST">
        <div class="form-group">
            <!-- 認証コード入力フィールド -->
            <input type="text" id="code" name="code" class="input-code" required placeholder="123456" maxlength="6" pattern="[0-9]{6}" autocomplete="off">
        </div>
        <button type="submit" class="btn-submit">アカウントを作成する</button>
    </form>

    <a href="/register" class="back-link">← 最初からやり直す</a>
</div>