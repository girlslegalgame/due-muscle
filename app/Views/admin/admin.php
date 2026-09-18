<div class="container" style="max-width: 1200px;">
    <h2>管理画面</h2>

    <!-- セクション1: 通報一覧 -->
    <h3 style="margin-top: 30px;">通報一覧</h3>
    <table style="width: 100%; border-collapse: collapse; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 40px;">
        <thead>
            <tr style="background: #333; color: #fff; font-size: 0.85rem;">
                <th style="padding: 10px;">ID</th>
                <th style="padding: 10px;">種別</th>
                <th style="padding: 10px;">対象デッキ</th>
                <th style="padding: 10px;">被通報者</th>
                <th style="padding: 10px;">通報者</th>
                <th style="padding: 10px;">理由</th>
                <th style="padding: 10px;">状態</th>
                <th style="padding: 10px; text-align: center;">操作・ペナルティ</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($reports)): ?>
                <tr><td colspan="8" style="padding: 15px; text-align: center; color: #777;">現在通報はありません。</td></tr>
            <?php else: ?>
                <?php foreach ($reports as $r): ?>
                    <tr style="border-bottom: 1px solid #eee; font-size: 0.85rem;">
                        <td style="padding: 8px 10px;"><?= $r['report_id'] ?></td>
                        <td style="padding: 8px 10px; font-weight: bold; color: <?= $r['report_type'] === 'user' ? '#d63384' : '#0d6efd' ?>;">
                            <?= $r['report_type'] === 'user' ? 'ユーザー' : 'デッキ' ?>
                        </td>
                        <td style="padding: 8px 10px;">
                            <?= htmlspecialchars($r['deck_name']) ?><br>
                            <small style="color: <?= $r['is_public'] ? '#28a745' : '#dc3545' ?>;"><?= $r['is_public'] ? '公開中' : '非公開' ?></small>
                        </td>
                        <td style="padding: 8px 10px;">
                            <strong><?= htmlspecialchars($r['creator_name']) ?></strong><br>
                            <button onclick="openUserDecksModal(<?= $r['reported_user_id'] ?>, '<?= htmlspecialchars($r['creator_name'], ENT_QUOTES) ?>')" style="font-size:0.75rem; padding: 2px 6px; cursor:pointer;">デッキ一覧</button>
                        </td>
                        <td style="padding: 8px 10px;"><?= htmlspecialchars($r['reporter_name'] ?? 'ゲスト') ?></td>
                        <td style="padding: 8px 10px; max-width: 200px;"><?= nl2br(htmlspecialchars($r['reason'])) ?></td>
                        <td style="padding: 8px 10px; font-weight: bold;">
                            <?= $r['status'] === 'pending' ? '<span style="color:#e67e22;">未対応</span>' : ($r['status'] === 'resolved' ? '<span style="color:#28a745;">対応済</span>' : '<span style="color:#6c757d;">却下</span>') ?>
                        </td>
                        <td style="padding: 8px 10px; text-align: center;">
                            <?php if ($r['status'] === 'pending'): ?>
                                <button onclick="handleReport(<?= $r['report_id'] ?>, <?= $r['deck_id'] ?>, 'make_private')" style="padding: 3px 6px; background:#dc3545; color:#fff; border:none; border-radius:3px; cursor:pointer;">非公開化</button>
                                <button onclick="handleReport(<?= $r['report_id'] ?>, <?= $r['deck_id'] ?>, 'dismiss')" style="padding: 3px 6px; background:#6c757d; color:#fff; border:none; border-radius:3px; cursor:pointer;">却下</button>
                                <br>
                                <button onclick="applyPenalty(<?= $r['reported_user_id'] ?>, 'public_ban', 'apply_1week')" style="margin-top:4px; padding: 2px 5px; font-size: 0.75rem; background:#ffc107; border:none; border-radius:3px; cursor:pointer;">作成者:公開禁止1週</button>
                                <?php if (!empty($r['user_id'])): ?>
                                    <button onclick="applyPenalty(<?= $r['user_id'] ?>, 'report_ban', 'apply_1week')" style="margin-top:4px; padding: 2px 5px; font-size: 0.75rem; background:#6f42c1; color:#fff; border:none; border-radius:3px; cursor:pointer;">通報者:通報禁止1週</button>
                                <?php endif; ?>
                            <?php else: ?>-<?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- セクション2: ユーザー管理一覧 -->
    <h3>ユーザー一覧</h3>
    <table style="width: 100%; border-collapse: collapse; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
        <thead>
            <tr style="background: #333; color: #fff; font-size: 0.85rem;">
                <th style="padding: 10px;">UID</th>
                <th style="padding: 10px;">ユーザー名</th>
                <th style="padding: 10px;">Email</th>
                <th style="padding: 10px;">ロール</th>
                <th style="padding: 10px;">公開ペナルティ</th>
                <th style="padding: 10px;">通報ペナルティ</th>
                <th style="padding: 10px; text-align: center;">操作</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $u): ?>
                <?php 
                    $isPublicBanned = !empty($u['public_ban_until']) && strtotime($u['public_ban_until']) > time();
                    $isReportBanned = !empty($u['report_ban_until']) && strtotime($u['report_ban_until']) > time();
                ?>
                <tr style="border-bottom: 1px solid #eee; font-size: 0.85rem;">
                    <td style="padding: 8px 10px;"><?= $u['user_id'] ?></td>
                    <td style="padding: 8px 10px;">
                        <input type="text" id="username_<?= $u['user_id'] ?>" value="<?= htmlspecialchars($u['username']) ?>" style="padding: 3px; font-size:0.85rem; width: 110px;">
                        <button onclick="updateUsername(<?= $u['user_id'] ?>)" style="padding: 3px 6px; font-size:0.75rem;">変更</button>
                    </td>
                    <td style="padding: 8px 10px;"><?= htmlspecialchars($u['email']) ?></td>
                    <td style="padding: 8px 10px;"><?= htmlspecialchars($u['role']) ?></td>
                    <td style="padding: 8px 10px;">
                        <?php if ($isPublicBanned): ?>
                            <span style="color:#dc3545; font-weight:bold;"><?= substr($u['public_ban_until'], 0, 16) ?> まで</span>
                            <button onclick="applyPenalty(<?= $u['user_id'] ?>, 'public_ban', 'clear')" style="padding: 2px 5px; font-size: 0.75rem; margin-left: 4px;">解除</button>
                        <?php else: ?>
                            <span style="color:#28a745;">なし</span>
                        <?php endif; ?>
                    </td>
                    <td style="padding: 8px 10px;">
                        <?php if ($isReportBanned): ?>
                            <span style="color:#dc3545; font-weight:bold;"><?= substr($u['report_ban_until'], 0, 16) ?> まで</span>
                            <button onclick="applyPenalty(<?= $u['user_id'] ?>, 'report_ban', 'clear')" style="padding: 2px 5px; font-size: 0.75rem; margin-left: 4px;">解除</button>
                        <?php else: ?>
                            <span style="color:#28a745;">なし</span>
                        <?php endif; ?>
                    </td>
                    <td style="padding: 8px 10px; text-align:center;">
                        <button onclick="openUserDecksModal(<?= $u['user_id'] ?>, '<?= htmlspecialchars($u['username'], ENT_QUOTES) ?>')" style="padding: 4px 8px; font-size: 0.8rem;">デッキ一覧</button>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- ユーザー保有デッキ一覧表示サブモーダル -->
<div id="userDecksModal" class="sub-modal">
    <div class="sub-modal-content" style="max-width: 600px;">
        <div class="sub-modal-header">
            <span id="userDecksModalTitle">デッキ一覧</span>
            <span style="cursor:pointer;" onclick="closeUserDecksModal()">&times;</span>
        </div>
        <div class="sub-modal-body" style="padding: 15px;">
            <div id="userDecksList" style="display: flex; flex-direction: column; gap: 8px;"></div>
        </div>
    </div>
</div>

<script>
function handleReport(reportId, deckId, action) {
    if (!confirm('実行しますか？')) return;
    fetch('/api/admin/reports/action', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ report_id: reportId, deck_id: deckId, action: action })
    }).then(res => res.json()).then(data => {
        if (data.success) location.reload();
        else alert(data.error || 'エラー');
    });
}

function applyPenalty(userId, type, action) {
    const msg = action === 'apply_1week' ? '1週間のペナルティを付与しますか？' : 'ペナルティを手動解除しますか？';
    if (!confirm(msg)) return;
    fetch('/api/admin/penalty', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ user_id: userId, type: type, action: action })
    }).then(res => res.json()).then(data => {
        if (data.success) location.reload();
        else alert(data.error || 'エラー');
    });
}

function updateUsername(userId) {
    const newName = document.getElementById('username_' + userId).value.trim();
    if (!newName) return alert('ユーザー名を入力してください');
    fetch('/api/admin/users/update', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ user_id: userId, username: newName })
    }).then(res => res.json()).then(data => {
        if (data.success) alert('ユーザー名を変更しました');
        else alert(data.error || 'エラー');
    });
}

function openUserDecksModal(userId, username) {
    document.getElementById('userDecksModalTitle').innerText = username + ' さんのデッキ一覧';
    const list = document.getElementById('userDecksList');
    list.innerHTML = '読み込み中...';
    document.getElementById('userDecksModal').style.display = 'block';

    fetch('/api/admin/user-decks?user_id=' + userId)
        .then(res => res.json())
        .then(decks => {
            list.innerHTML = '';
            if (!decks.length) {
                list.innerHTML = '<p>デッキはありません。</p>';
                return;
            }
            decks.forEach(d => {
                const item = document.createElement('div');
                item.style = "display:flex; justify-content:space-between; align-items:center; padding:8px; border-bottom:1px solid #eee;";
                item.innerHTML = `
                    <span><strong>${d.deck_name}</strong> (${d.is_public ? '<span style="color:#28a745;">公開中</span>' : '<span style="color:#dc3545;">非公開</span>'})</span>
                    <div>
                        ${d.is_public ? `<button onclick="makeDeckPrivate(${d.deck_id})" style="background:#dc3545; color:#fff; border:none; padding:4px 8px; border-radius:3px; cursor:pointer;">非公開にする</button>` : ''}
                    </div>
                `;
                list.appendChild(item);
            });
        });
}

function makeDeckPrivate(deckId) {
    if (!confirm('このデッキを非公開にしますか？')) return;
    fetch('/api/admin/reports/action', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ report_id: 0, deck_id: deckId, action: 'make_private' })
    }).then(res => res.json()).then(() => location.reload());
}

function closeUserDecksModal() {
    document.getElementById('userDecksModal').style.display = 'none';
}
</script>