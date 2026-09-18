<div class="container" style="max-width: 1100px;">
    <h2>デッキ通報管理一覧</h2>

    <table style="width: 100%; border-collapse: collapse; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <thead>
            <tr style="background: #333; color: #fff; text-align: left; font-size: 0.9rem;">
                <th style="padding: 12px 10px;">ID</th>
                <th style="padding: 12px 10px;">対象デッキ</th>
                <th style="padding: 12px 10px;">作成者</th>
                <th style="padding: 12px 10px;">通報者</th>
                <th style="padding: 12px 10px;">通報理由</th>
                <th style="padding: 12px 10px;">通報日時</th>
                <th style="padding: 12px 10px;">状態</th>
                <th style="padding: 12px 10px; text-align: center;">操作</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($reports)): ?>
                <tr><td colspan="8" style="padding: 20px; text-align: center; color: #777;">現在通報はありません。</td></tr>
            <?php else: ?>
                <?php foreach ($reports as $r): ?>
                    <tr style="border-bottom: 1px solid #eee; font-size: 0.85rem;">
                        <td style="padding: 10px;"><?= $r['report_id'] ?></td>
                        <td style="padding: 10px;">
                            <strong><?= htmlspecialchars($r['deck_name']) ?></strong><br>
                            <span style="font-size: 0.75rem; color: <?= $r['is_public'] ? '#28a745' : '#dc3545' ?>;">
                                <?= $r['is_public'] ? '● 公開中' : '▲ 非公開' ?>
                            </span>
                        </td>
                        <td style="padding: 10px;"><?= htmlspecialchars($r['creator_name']) ?></td>
                        <td style="padding: 10px;"><?= htmlspecialchars($r['reporter_name'] ?? 'ゲスト') ?></td>
                        <td style="padding: 10px; max-width: 250px; word-break: break-word;"><?= nl2br(htmlspecialchars($r['reason'])) ?></td>
                        <td style="padding: 10px; white-space: nowrap;"><?= htmlspecialchars(substr($r['created_at'], 0, 16)) ?></td>
                        <td style="padding: 10px;">
                            <?php if ($r['status'] === 'pending'): ?>
                                <span style="background: #ffeeba; color: #856404; padding: 2px 6px; border-radius: 4px; font-weight: bold;">未対応</span>
                            <?php elseif ($r['status'] === 'resolved'): ?>
                                <span style="background: #d4edda; color: #155724; padding: 2px 6px; border-radius: 4px;">対応済</span>
                            <?php else: ?>
                                <span style="background: #e2e3e5; color: #383d41; padding: 2px 6px; border-radius: 4px;">却下</span>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 10px; text-align: center; white-space: nowrap;">
                            <?php if ($r['status'] === 'pending'): ?>
                                <button onclick="handleReport(<?= $r['report_id'] ?>, <?= $r['deck_id'] ?>, 'make_private')" style="padding: 4px 8px; background: #dc3545; color: #fff; border: none; border-radius: 4px; cursor: pointer;">非公開化</button>
                                <button onclick="handleReport(<?= $r['report_id'] ?>, <?= $r['deck_id'] ?>, 'dismiss')" style="padding: 4px 8px; background: #6c757d; color: #fff; border: none; border-radius: 4px; cursor: pointer;">却下</button>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
function handleReport(reportId, deckId, action) {
    const msg = action === 'make_private' ? 'このデッキを非公開にして対応完了としますか？' : 'この通報を却下しますか？';
    if (!confirm(msg)) return;

    fetch('/api/admin/reports/action', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ report_id: reportId, deck_id: deckId, action: action })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('処理に失敗しました: ' + (data.error || '不明なエラー'));
        }
    })
    .catch(err => alert('通信エラーが発生しました。'));
}
</script>