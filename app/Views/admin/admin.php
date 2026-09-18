<style>
    /* 管理画面共通スタイル */
    .admin-title { font-size: 1.5rem; font-weight: bold; margin-bottom: 20px; color: #333; }
    .admin-section-title { font-size: 1.15rem; font-weight: bold; margin: 30px 0 12px; color: #444; border-left: 4px solid #007bff; padding-left: 10px; }
    
    /* はみ出し防止用テーブルラッパー */
    .admin-table-wrapper {
        width: 100%;
        overflow-x: auto;
        background: #fff;
        border-radius: 8px;
        box-shadow: 0 1px 4px rgba(0,0,0,0.08);
        border: 1px solid #e2e8f0;
        margin-bottom: 30px;
    }
    .admin-table {
        width: 100%;
        border-collapse: collapse;
        min-width: 900px; /* 横スクロールを担保 */
        font-size: 0.85rem;
    }
    .admin-table th {
        background: #2d3748;
        color: #fff;
        padding: 12px 10px;
        text-align: left;
        font-weight: 600;
        white-space: nowrap;
    }
    .admin-table td {
        padding: 10px;
        border-bottom: 1px solid #edf2f7;
        vertical-align: middle;
    }
    .admin-table tbody tr:hover { background-color: #f8fafc; }

    /* 各種バッジ */
    .badge {
        display: inline-block;
        padding: 3px 8px;
        border-radius: 4px;
        font-size: 0.75rem;
        font-weight: bold;
        line-height: 1.2;
    }
    .badge-user { background: #fce7f3; color: #be185d; }
    .badge-deck { background: #e0f2fe; color: #0369a1; }
    .badge-public { background: #dcfce7; color: #15803d; }
    .badge-private { background: #fee2e2; color: #b91c1c; }
    .badge-pending { background: #fef3c7; color: #b45309; }
    .badge-resolved { background: #dcfce7; color: #15803d; }
    .badge-dismissed { background: #f1f5f9; color: #475569; }

    /* 統一ボタンデザイン */
    .btn-adm {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 5px 10px;
        font-size: 0.75rem;
        font-weight: bold;
        border-radius: 4px;
        border: 1px solid transparent;
        cursor: pointer;
        transition: all 0.2s;
        text-decoration: none;
        white-space: nowrap;
        gap: 4px;
    }
    .btn-adm:hover { opacity: 0.85; }
    .btn-adm-primary { background: #007bff; color: #fff; }
    .btn-adm-danger { background: #dc3545; color: #fff; }
    .btn-adm-secondary { background: #6c757d; color: #fff; }
    .btn-adm-warning { background: #f59e0b; color: #fff; }
    .btn-adm-purple { background: #8b5cf6; color: #fff; }
    .btn-adm-outline { background: #fff; border-color: #cbd5e1; color: #334155; }
    .btn-adm-outline:hover { background: #f1f5f9; }

    /* 入力フォーム整流 */
    .input-adm-text {
        padding: 4px 8px;
        border: 1px solid #cbd5e1;
        border-radius: 4px;
        font-size: 0.8rem;
        box-sizing: border-box;
    }
    .input-adm-text:focus { border-color: #007bff; outline: none; }
</style>

<div class="container" style="max-width: 1200px;">
    <h2 class="admin-title">管理画面</h2>

    <!-- セクション1: 通報一覧 -->
    <div class="admin-section-title">通報一覧</div>
    <div class="admin-table-wrapper">
        <table class="admin-table">
            <thead>
                <tr>
                    <th style="width: 50px;">ID</th>
                    <th style="width: 80px;">種別</th>
                    <th>対象デッキ</th>
                    <th>被通報者</th>
                    <th>通報者</th>
                    <th>通報理由</th>
                    <th style="width: 80px;">状態</th>
                    <th style="width: 220px; text-align: center;">操作・ペナルティ</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($reports)): ?>
                    <tr><td colspan="8" style="padding: 25px; text-align: center; color: #94a3b8;">現在通報はありません。</td></tr>
                <?php else: ?>
                    <?php foreach ($reports as $r): ?>
                        <tr>
                            <td><?= $r['report_id'] ?></td>
                            <td>
                                <span class="badge <?= $r['report_type'] === 'user' ? 'badge-user' : 'badge-deck' ?>">
                                    <?= $r['report_type'] === 'user' ? 'ユーザー' : 'デッキ' ?>
                                </span>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($r['deck_name']) ?></strong><br>
                                <span class="badge <?= $r['is_public'] ? 'badge-public' : 'badge-private' ?>" style="margin-top: 2px;">
                                    <?= $r['is_public'] ? '公開中' : '非公開' ?>
                                </span>
                            </td>
                            <td>
                                <div style="font-weight: bold;"><?= htmlspecialchars($r['creator_name']) ?></div>
                                <button type="button" class="btn-adm btn-adm-outline" style="margin-top: 4px;" onclick="openUserDecksModal(<?= $r['reported_user_id'] ?>, '<?= htmlspecialchars($r['creator_name'], ENT_QUOTES) ?>')">デッキ一覧</button>
                            </td>
                            <td><?= htmlspecialchars($r['reporter_name'] ?? 'ゲスト') ?></td>
                            <td style="max-width: 220px; word-break: break-word;"><?= nl2br(htmlspecialchars($r['reason'])) ?></td>
                            <td>
                                <span class="badge badge-<?= $r['status'] ?>">
                                    <?= $r['status'] === 'pending' ? '未対応' : ($r['status'] === 'resolved' ? '対応済' : '却下') ?>
                                </span>
                            </td>
                            <td style="text-align: center;">
                                <?php if ($r['status'] === 'pending'): ?>
                                    <div style="display: flex; gap: 4px; justify-content: center; margin-bottom: 4px;">
                                        <button class="btn-adm btn-adm-danger" onclick="handleReport(<?= $r['report_id'] ?>, <?= $r['deck_id'] ?>, 'make_private')">非公開化</button>
                                        <button class="btn-adm btn-adm-secondary" onclick="handleReport(<?= $r['report_id'] ?>, <?= $r['deck_id'] ?>, 'dismiss')">却下</button>
                                    </div>
                                    <div style="display: flex; gap: 4px; justify-content: center; flex-direction: column;">
                                        <button class="btn-adm btn-adm-warning" onclick="applyPenalty(<?= $r['reported_user_id'] ?>, 'public_ban', 'apply_1week')">作成者: 公開禁止1週</button>
                                        <?php if (!empty($r['user_id'])): ?>
                                            <button class="btn-adm btn-adm-purple" onclick="applyPenalty(<?= $r['user_id'] ?>, 'report_ban', 'apply_1week')">通報者: 通報禁止1週</button>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <span style="color: #94a3b8;">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- セクション2: ユーザー管理一覧 -->
    <div class="admin-section-title">ユーザー一覧</div>
    <div class="admin-table-wrapper">
        <table class="admin-table">
            <thead>
                <tr>
                    <th style="width: 60px;">UID</th>
                    <th>ユーザー名</th>
                    <th>メールアドレス</th>
                    <th style="width: 80px;">ロール</th>
                    <th>公開ペナルティ</th>
                    <th>通報ペナルティ</th>
                    <th style="width: 110px; text-align: center;">デッキ確認</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                    <?php 
                        $isPublicBanned = !empty($u['public_ban_until']) && strtotime($u['public_ban_until']) > time();
                        $isReportBanned = !empty($u['report_ban_until']) && strtotime($u['report_ban_until']) > time();
                    ?>
                    <tr>
                        <td><?= $u['user_id'] ?></td>
                        <td>
                            <div style="display: flex; align-items: center; gap: 6px;">
                                <input type="text" class="input-adm-text" id="username_<?= $u['user_id'] ?>" value="<?= htmlspecialchars($u['username']) ?>" style="width: 130px;">
                                <button class="btn-adm btn-adm-primary" onclick="updateUsername(<?= $u['user_id'] ?>)">変更</button>
                            </div>
                        </td>
                        <td><?= htmlspecialchars($u['email']) ?></td>
                        <td><span class="badge badge-dismissed"><?= htmlspecialchars($u['role']) ?></span></td>
                        <td>
                            <?php if ($isPublicBanned): ?>
                                <span style="color:#dc3545; font-weight:bold;"><?= substr($u['public_ban_until'], 0, 16) ?> まで</span>
                                <button class="btn-adm btn-adm-outline" style="margin-left: 6px; padding: 2px 6px;" onclick="applyPenalty(<?= $u['user_id'] ?>, 'public_ban', 'clear')">解除</button>
                            <?php else: ?>
                                <span style="color:#10b981; font-weight: 500;">なし</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($isReportBanned): ?>
                                <span style="color:#dc3545; font-weight:bold;"><?= substr($u['report_ban_until'], 0, 16) ?> まで</span>
                                <button class="btn-adm btn-adm-outline" style="margin-left: 6px; padding: 2px 6px;" onclick="applyPenalty(<?= $u['user_id'] ?>, 'report_ban', 'clear')">解除</button>
                            <?php else: ?>
                                <span style="color:#10b981; font-weight: 500;">なし</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align: center;">
                            <button class="btn-adm btn-adm-outline" onclick="openUserDecksModal(<?= $u['user_id'] ?>, '<?= htmlspecialchars($u['username'], ENT_QUOTES) ?>')">デッキ一覧</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ユーザー保有デッキ一覧表示サブモーダル -->
<div id="userDecksModal" class="sub-modal">
    <div class="sub-modal-content" style="max-width: 600px; max-height: 80vh; display: flex; flex-direction: column;">
        <div class="sub-modal-header">
            <span id="userDecksModalTitle" style="font-weight: bold;">デッキ一覧</span>
            <span style="cursor:pointer; font-size: 1.3rem;" onclick="closeUserDecksModal()">&times;</span>
        </div>
        <!-- はみ出し防止：max-heightとoverflow-yを指定 -->
        <div class="sub-modal-body" style="padding: 15px; flex: 1; overflow-y: auto; max-height: 60vh;">
            <div id="userDecksList" style="display: flex; flex-direction: column; gap: 8px;"></div>
        </div>
    </div>
</div>

<!-- セクション3: お知らせ手動送信 -->
    <div class="admin-section-title">お知らせ送信</div>
    <div style="background: #fff; padding: 20px; border-radius: 8px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.08); margin-bottom: 40px;">
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 12px;">
            <div>
                <label style="font-size: 0.8rem; font-weight: bold; color: #475569; display: block; margin-bottom: 4px;">送信対象</label>
                <select id="notif_target" class="input-adm-text" style="width: 100%;" onchange="document.getElementById('notif_user_wrapper').style.display = (this.value === 'user' ? 'block' : 'none');">
                    <option value="all">全ユーザー（仕様追加・カード追加など）</option>
                    <option value="user">特定のユーザー</option>
                </select>
            </div>
            <div id="notif_user_wrapper" style="display: none;">
                <label style="font-size: 0.8rem; font-weight: bold; color: #475569; display: block; margin-bottom: 4px;">対象ユーザーID</label>
                <input type="number" id="notif_user_id" class="input-adm-text" style="width: 100%;" placeholder="UIDを入力">
            </div>
        </div>

        <div style="margin-bottom: 12px;">
            <label style="font-size: 0.8rem; font-weight: bold; color: #475569; display: block; margin-bottom: 4px;">タイトル</label>
            <input type="text" id="notif_title" class="input-adm-text" style="width: 100%;" placeholder="例: 新規弾のカードデータを追加しました">
        </div>

        <div style="margin-bottom: 12px;">
            <label style="font-size: 0.8rem; font-weight: bold; color: #475569; display: block; margin-bottom: 4px;">本文</label>
            <textarea id="notif_message" class="input-adm-text" rows="4" style="width: 100%;" placeholder="お知らせの詳細内容"></textarea>
        </div>

        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 15px; margin-bottom: 15px;">
            <div>
                <label style="font-size: 0.8rem; font-weight: bold; color: #475569; display: block; margin-bottom: 4px;">リンク先URL (任意)</label>
                <input type="text" id="notif_url" class="input-adm-text" style="width: 100%;" placeholder="/search などの内部リンク">
            </div>
            <div>
                <label style="font-size: 0.8rem; font-weight: bold; color: #475569; display: block; margin-bottom: 4px;">ボタンのラベル (任意)</label>
                <input type="text" id="notif_label" class="input-adm-text" style="width: 100%;" placeholder="確認する">
            </div>
        </div>

        <button type="button" class="btn-adm btn-adm-primary" style="padding: 8px 20px; font-size: 0.9rem;" onclick="sendManualNotification()">お知らせを送信する</button>
    </div>

<script>
function handleReport(reportId, deckId, action) {
    let reason = '';
    if (action === 'make_private') {
        reason = prompt('非公開にする理由を入力してください（ユーザーへの通知に記載されます）:', '利用規約違反が確認されたため');
        if (reason === null) return;
    } else {
        if (!confirm('この通報を却下しますか？')) return;
    }

    fetch('/api/admin/reports/action', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ report_id: reportId, deck_id: deckId, action: action, reason: reason })
    }).then(res => res.json()).then(data => {
        if (data.success) location.reload();
        else alert(data.error || 'エラー');
    });
}

function applyPenalty(userId, type, action, deckId = 0) {
    let reason = '';
    if (action === 'apply_1week') {
        reason = prompt('ペナルティ付与の理由を入力してください（ユーザー通知に記載されます）:', '利用規約違反のため');
        if (reason === null) return;
    } else {
        if (!confirm('ペナルティを手動解除しますか？')) return;
    }

    fetch('/api/admin/penalty', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ user_id: userId, deck_id: deckId, type: type, action: action, reason: reason })
    }).then(res => res.json()).then(data => {
        if (data.success) location.reload();
        else alert(data.error || 'エラー');
    });
}

function sendManualNotification() {
    const target = document.getElementById('notif_target').value;
    const userId = document.getElementById('notif_user_id').value;
    const title = document.getElementById('notif_title').value.trim();
    const message = document.getElementById('notif_message').value.trim();
    const url = document.getElementById('notif_url').value.trim();
    const label = document.getElementById('notif_label').value.trim();

    if (!title || !message) return alert('タイトルと本文を入力してください');
    if (target === 'user' && !userId) return alert('対象ユーザーIDを入力してください');

    fetch('/api/admin/notifications', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ target: target, user_id: userId, title: title, message: message, action_url: url, action_label: label })
    }).then(res => res.json()).then(data => {
        if (data.success) {
            alert('お知らせを送信しました');
            document.getElementById('notif_title').value = '';
            document.getElementById('notif_message').value = '';
            document.getElementById('notif_url').value = '';
            document.getElementById('notif_label').value = '';
        } else {
            alert(data.error || 'エラー');
        }
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
    list.innerHTML = '<div style="text-align:center; padding:20px; color:#64748b;">読み込み中...</div>';
    document.getElementById('userDecksModal').style.display = 'block';

    fetch('/api/admin/user-decks?user_id=' + userId)
        .then(res => res.json())
        .then(decks => {
            list.innerHTML = '';
            if (!decks.length) {
                list.innerHTML = '<div style="text-align:center; padding:20px; color:#94a3b8;">デッキが見つかりません。</div>';
                return;
            }
            decks.forEach(d => {
                const item = document.createElement('div');
                item.style = "display:flex; justify-content:space-between; align-items:center; padding:10px 12px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px;";
                item.innerHTML = `
                    <div style="min-width:0; margin-right:10px;">
                        <strong style="font-size:0.9rem; display:block; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${escapeHTML(d.deck_name)}</strong>
                        <span class="badge ${d.is_public ? 'badge-public' : 'badge-private'}" style="margin-top:2px;">
                            ${d.is_public ? '公開中' : '非公開'}
                        </span>
                    </div>
                    <div>
                        ${d.is_public ? `<button class="btn-adm btn-adm-danger" onclick="makeDeckPrivate(${d.deck_id})">非公開にする</button>` : '<span style="color:#94a3b8; font-size:0.8rem;">非公開設定済</span>'}
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

function escapeHTML(str) {
    if (!str) return '';
    return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}
</script>