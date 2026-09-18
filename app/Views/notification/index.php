<div class="container" style="max-width: 900px;">
    <h2>お知らせ</h2>

    <div style="display: flex; gap: 10px; border-bottom: 2px solid #ddd; margin-bottom: 20px;">
        <button type="button" id="tab-btn-global" onclick="switchNotifTab('global')" style="padding: 10px 20px; font-weight: bold; border: none; background: none; cursor: pointer; border-bottom: 3px solid #007bff; color: #007bff; font-size: 1rem;">全体へのお知らせ</button>
        <button type="button" id="tab-btn-personal" onclick="switchNotifTab('personal')" style="padding: 10px 20px; font-weight: bold; border: none; background: none; cursor: pointer; border-bottom: 3px solid transparent; color: #666; font-size: 1rem;">あなたへのお知らせ</button>
    </div>

    <!-- 全体お知らせ -->
    <div id="notif-pane-global">
        <?php if (empty($globalNotifications)): ?>
            <p style="text-align: center; color: #888; padding: 40px 0;">現在お知らせはありません。</p>
        <?php else: ?>
            <div style="display: flex; flex-direction: column; gap: 15px;">
                <?php foreach ($globalNotifications as $n): ?>
                    <div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 18px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                        <div style="display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 8px;">
                            <h3 style="margin: 0; font-size: 1.1rem; color: #1e293b;"><?= htmlspecialchars($n['title']) ?></h3>
                            <span style="font-size: 0.8rem; color: #94a3b8;"><?= substr($n['created_at'], 0, 16) ?></span>
                        </div>
                        <p style="margin: 0 0 10px; font-size: 0.95rem; color: #475569; white-space: pre-wrap; line-height: 1.6;"><?= htmlspecialchars($n['message']) ?></p>
                        <?php if (!empty($n['action_url'])): ?>
                            <a href="<?= htmlspecialchars($n['action_url']) ?>" style="display: inline-block; padding: 6px 14px; background: #007bff; color: #fff; text-decoration: none; border-radius: 4px; font-size: 0.85rem; font-weight: bold;"><?= htmlspecialchars($n['action_label'] ?: '確認する') ?></a>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- あなたへのお知らせ -->
    <div id="notif-pane-personal" style="display: none;">
        <?php if (!isset($_SESSION['user_id'])): ?>
            <p style="text-align: center; color: #888; padding: 40px 0;">個別の通知を確認するにはログインしてください。</p>
        <?php elseif (empty($userNotifications)): ?>
            <p style="text-align: center; color: #888; padding: 40px 0;">あなた宛てのお知らせはありません。</p>
        <?php else: ?>
            <div style="display: flex; flex-direction: column; gap: 15px;">
                <?php foreach ($userNotifications as $n): ?>
                    <div style="background: #fff; border: 1px solid #e2e8f0; border-left: 4px solid #ef4444; border-radius: 8px; padding: 18px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                        <div style="display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 8px;">
                            <h3 style="margin: 0; font-size: 1.1rem; color: #1e293b;"><?= htmlspecialchars($n['title']) ?></h3>
                            <span style="font-size: 0.8rem; color: #94a3b8;"><?= substr($n['created_at'], 0, 16) ?></span>
                        </div>
                        <p style="margin: 0 0 10px; font-size: 0.95rem; color: #475569; white-space: pre-wrap; line-height: 1.6;"><?= htmlspecialchars($n['message']) ?></p>
                        <?php if (!empty($n['action_url'])): ?>
                            <a href="<?= htmlspecialchars($n['action_url']) ?>" style="display: inline-block; padding: 6px 14px; background: #007bff; color: #fff; text-decoration: none; border-radius: 4px; font-size: 0.85rem; font-weight: bold;"><?= htmlspecialchars($n['action_label'] ?: 'デッキを編集する') ?></a>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function switchNotifTab(type) {
    const isGlobal = type === 'global';
    document.getElementById('notif-pane-global').style.display = isGlobal ? 'block' : 'none';
    document.getElementById('notif-pane-personal').style.display = isGlobal ? 'none' : 'block';

    document.getElementById('tab-btn-global').style.borderBottomColor = isGlobal ? '#007bff' : 'transparent';
    document.getElementById('tab-btn-global').style.color = isGlobal ? '#007bff' : '#666';

    document.getElementById('tab-btn-personal').style.borderBottomColor = isGlobal ? 'transparent' : '#007bff';
    document.getElementById('tab-btn-personal').style.color = isGlobal ? '#666' : '#007bff';
}
</script>