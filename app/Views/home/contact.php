<div class="container" style="max-width: 600px;">
    <h2>お問い合わせ</h2>

    <?php if (!empty($success)): ?>
        <div style="background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; padding: 12px; border-radius: 6px; margin-bottom: 20px;">
            <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <div style="background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; padding: 12px; border-radius: 6px; margin-bottom: 20px;">
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="/contact" style="display: flex; flex-direction: column; gap: 15px;">
        <div>
            <label for="email" style="display: block; font-weight: bold; margin-bottom: 5px;">メールアドレス <span style="color: #dc3545;">*</span></label>
            <input type="email" id="email" name="email" required style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box;">
        </div>

        <div>
            <label for="subject" style="display: block; font-weight: bold; margin-bottom: 5px;">件名 <span style="color: #dc3545;">*</span></label>
            <input type="text" id="subject" name="subject" required style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box;">
        </div>

        <div>
            <label for="message" style="display: block; font-weight: bold; margin-bottom: 5px;">お問い合わせ内容 <span style="color: #dc3545;">*</span></label>
            <textarea id="message" name="message" rows="6" required style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; resize: vertical;"></textarea>
        </div>

        <button type="submit" class="create-btn" style="width: 100%;">送信する</button>
    </form>
</div>