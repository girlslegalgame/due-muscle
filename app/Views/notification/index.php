<style>
    .notif-item {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        padding: 14px 18px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        cursor: pointer;
        transition: background 0.15s;
    }
    .notif-item:hover { background: #f8fafc; }
    .notif-item.unread {
        border-left: 4px solid #007bff;
        font-weight: bold;
    }
    .notif-item.read { opacity: 0.75; }
    .notif-badge-unread {
        background: #ef4444;
        color: #fff;
        font-size: 0.7rem;
        padding: 2px 6px;
        border-radius: 4px;
        margin-right: 8px;
    }
</style>

<div class="container" style="max-width: 900px;">
    <h2>お知らせ</h2>

    <!-- 操作ツールバー -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; flex-wrap: wrap; gap: 10px;">
        <div style="display: flex; gap: 10px; border-bottom: 2px solid #ddd;">
            <button type="button" id="tab-btn-global" onclick="switchNotifTab('global')" style="padding: 10px 16px; font-weight: bold; border: none; background: none; cursor: pointer; border-bottom: 3px solid #007bff; color: #007bff; font-size: 0.95rem;">全体へのお知らせ</button>
            <button type="button" id="tab-btn-personal" onclick="switchNotifTab('personal')" style="padding: 10px 16px; font-weight: bold; border: none; background: none; cursor: pointer; border-bottom: 3px solid transparent; color: #666; font-size: 0.95rem;">あなたへのお知らせ</button>
        </div>
        
        <?php if (isset($_SESSION['user_id'])): ?>
            <div style="display: flex; gap: 8px;">
                <button type="button" onclick="deleteNotifications('read')" style="padding: 6px 12px; font-size: 0.8rem; background: #fff; border: 1px solid #cbd5e1; border-radius: 4px; cursor: pointer;">既読を削除</button>
                <button type="button" onclick="deleteNotifications('all')" style="padding: 6px 12px; font-size: 0.8rem; background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; border-radius: 4px; cursor: pointer;">すべて削除</button>
            </div>
        <?php endif; ?>
    </div>

    <!-- 全体お知らせペイン -->
    <div id="notif-pane-global">
        <?php if (empty($globalNotifications)): ?>
            <p style="text-align: center; color: #888; padding: 40px 0;">現在お知らせはありません。</p>
        <?php else: ?>
            <div style="display: flex; flex-direction: column; gap: 8px;">
                <?php foreach ($globalNotifications as $n): ?>
                    <div class="notif-item <?= $n['is_read'] ? 'read' : 'unread' ?>" id="notif_item_<?= $n['notification_id'] ?>" onclick="openNotifDetail(<?= htmlspecialchars(json_encode($n), ENT_QUOTES, 'UTF-8') ?>)">
                        <div style="display: flex; align-items: center; min-width: 0; margin-right: 15px;">
                            <?php if (!$n['is_read']): ?>
                                <span class="notif-badge-unread" id="badge_unread_<?= $n['notification_id'] ?>">未読</span>
                            <?php endif; ?>
                            <span style="font-size: 0.95rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?= htmlspecialchars($n['title']) ?></span>
                        </div>
                        <span style="font-size: 0.8rem; color: #94a3b8; white-space: nowrap;"><?= substr($n['created_at'], 0, 10) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- 個別お知らせペイン -->
    <div id="notif-pane-personal" style="display: none;">
        <?php if (!isset($_SESSION['user_id'])): ?>
            <p style="text-align: center; color: #888; padding: 40px 0;">個別の通知を確認するにはログインしてください。</p>
        <?php elseif (empty($userNotifications)): ?>
            <p style="text-align: center; color: #888; padding: 40px 0;">あなた宛てのお知らせはありません。</p>
        <?php else: ?>
            <div style="display: flex; flex-direction: column; gap: 8px;">
                <?php foreach ($userNotifications as $n): ?>
                    <div class="notif-item <?= $n['is_read'] ? 'read' : 'unread' ?>" id="notif_item_<?= $n['notification_id'] ?>" onclick="openNotifDetail(<?= htmlspecialchars(json_encode($n), ENT_QUOTES, 'UTF-8') ?>)">
                        <div style="display: flex; align-items: center; min-width: 0; margin-right: 15px;">
                            <?php if (!$n['is_read']): ?>
                                <span class="notif-badge-unread" id="badge_unread_<?= $n['notification_id'] ?>">未読</span>
                            <?php endif; ?>
                            <span style="font-size: 0.95rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?= htmlspecialchars($n['title']) ?></span>
                        </div>
                        <span style="font-size: 0.8rem; color: #94a3b8; white-space: nowrap;"><?= substr($n['created_at'], 0, 10) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- お知らせ本文閲覧用モーダル -->
<div id="notifDetailModal" class="sub-modal">
    <div class="sub-modal-content" style="max-width: 550px;">
        <div class="sub-modal-header">
            <span id="modalNotifTitle" style="font-weight: bold; font-size: 1rem;">お知らせ詳細</span>
            <span style="cursor:pointer; font-size: 1.3rem;" onclick="closeNotifDetail()">&times;</span>
        </div>
        <div class="sub-modal-body" style="padding: 20px;">
            <div id="modalNotifDate" style="font-size: 0.8rem; color: #94a3b8; margin-bottom: 12px;"></div>
            <div id="modalNotifMessage" style="font-size: 0.95rem; color: #334155; line-height: 1.6; white-space: pre-wrap; margin-bottom: 20px;"></div>
            <div id="modalNotifActionArea" style="text-align: right; display: none;">
                <a id="modalNotifActionBtn" href="#" style="display: inline-block; padding: 8px 16px; background: #007bff; color: #fff; text-decoration: none; border-radius: 4px; font-weight: bold; font-size: 0.85rem;"></a>
            </div>
        </div>
    </div>
</div>

<script>
let currentTab = 'global';

function switchNotifTab(type) {
    currentTab = type;
    const isGlobal = type === 'global';
    document.getElementById('notif-pane-global').style.display = isGlobal ? 'block' : 'none';
    document.getElementById('notif-pane-personal').style.display = isGlobal ? 'none' : 'block';

    document.getElementById('tab-btn-global').style.borderBottomColor = isGlobal ? '#007bff' : 'transparent';
    document.getElementById('tab-btn-global').style.color = isGlobal ? '#007bff' : '#666';

    document.getElementById('tab-btn-personal').style.borderBottomColor = isGlobal ? 'transparent' : '#007bff';
    document.getElementById('tab-btn-personal').style.color = isGlobal ? '#666' : '#007bff';
}

function openNotifDetail(notif) {
    document.getElementById('modalNotifTitle').innerText = notif.title;
    document.getElementById('modalNotifDate').innerText = notif.created_at.substring(0, 16);
    document.getElementById('modalNotifMessage').innerText = notif.message;

    const actionArea = document.getElementById('modalNotifActionArea');
    const actionBtn = document.getElementById('modalNotifActionBtn');
    if (notif.action_url) {
        actionBtn.href = notif.action_url;
        actionBtn.innerText = notif.action_label || '確認する';
        actionArea.style.display = 'block';
    } else {
        actionArea.style.display = 'none';
    }

    document.getElementById('notifDetailModal').style.display = 'block';

    // 既読APIの送信と画面上のスタイル更新
    if (!notif.is_read) {
        fetch('/api/notifications/read', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ notification_id: notif.notification_id })
        }).then(() => {
            notif.is_read = 1;
            const item = document.getElementById('notif_item_' + notif.notification_id);
            if (item) {
                item.classList.remove('unread');
                item.classList.add('read');
            }
            const badge = document.getElementById('badge_unread_' + notif.notification_id);
            if (badge) badge.remove();
        });
    }
}

function closeNotifDetail() {
    document.getElementById('notifDetailModal').style.display = 'none';
}

function deleteNotifications(type) {
    const msg = type === 'read' ? '既読のお知らせをすべて削除しますか？' : '表示中のお知らせをすべて削除しますか？';
    if (!confirm(msg)) return;

    fetch('/api/notifications/delete', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ type: type, tab: currentTab })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert(data.error || 'エラー');
        }
    })
    .catch(err => alert('通信エラーが発生しました。'));
}
</script>