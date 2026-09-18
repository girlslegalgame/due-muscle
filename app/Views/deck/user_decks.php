<div class="container">
    <!-- ユーザーヘッダーエリア -->
    <div style="display: flex; justify-content: space-between; align-items: center; background: #fff; padding: 15px 20px; border-radius: 8px; border: 1px solid #ddd; margin-bottom: 25px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
        <div>
            <h2 style="margin: 0; font-size: 1.3rem;"><?= htmlspecialchars($targetUser['username']) ?> さんの公開デッキ</h2>
            <span style="font-size: 0.85rem; color: #666;">公開デッキ数: <?= count($decks) ?> 件</span>
        </div>
        <div>
            <button type="button" class="btn-delete" style="background-color: #dc3545; font-size: 0.85rem; padding: 8px 14px; border-radius: 4px;" onclick="openUserReportModal()">ユーザーを通報</button>
        </div>
    </div>

    <!-- デッキ一覧 -->
    <div class="deck-list">
        <?php if (!empty($decks)): ?>
            <?php 
            $context = 'search'; 
            foreach ($decks as $deck): 
                include __DIR__ . '/deck_item.php'; 
            endforeach; 
            ?>
        <?php else: ?>
            <p style="grid-column: 1 / -1; text-align: center; color: #666; padding: 40px 0;">現在公開されているデッキはありません。</p>
        <?php endif; ?>
    </div>
</div>

<!-- デッキ詳細モーダル＆カード詳細モーダル -->
<?php include __DIR__ . '/deck_detail_modal.php'; ?>
<?php include __DIR__ . '/card_detail_modal.php'; ?>

<!-- ユーザー通報モーダル -->
<div id="userReportModal" class="sub-modal">
    <div class="sub-modal-content" style="max-width: 480px;">
        <div class="sub-modal-header">
            <span>ユーザーの通報</span>
            <span style="cursor:pointer;" onclick="closeUserReportModal()">&times;</span>
        </div>
        <div class="sub-modal-body" style="padding: 20px;">
            <p style="font-weight: bold; margin-top: 0;">対象ユーザー: <?= htmlspecialchars($targetUser['username']) ?></p>

            <label style="font-size: 0.85rem; font-weight: bold; color: #555; display: block; margin-bottom: 6px;">違反内容</label>
            <div style="display: flex; gap: 15px; margin-bottom: 12px; font-size: 0.85rem;">
                <label><input type="radio" name="modal_user_category" value="spam" checked> 連投</label>
                <label><input type="radio" name="modal_user_category" value="inappropriate_name"> 不適切なユーザー名</label>
                <label><input type="radio" name="modal_user_category" value="other"> その他</label>
            </div>

            <label style="font-size: 0.85rem; font-weight: bold; color: #555; display: block; margin-bottom: 6px;">通報理由 (必須)</label>
            <textarea id="modal_user_reason" rows="4" style="width: 100%; box-sizing: border-box; padding: 8px; border: 1px solid #ccc; border-radius: 4px;" placeholder="通報の理由を入力してください"></textarea>
            
            <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 15px;">
                <button type="button" class="btn-modal-cancel" onclick="closeUserReportModal()">キャンセル</button>
                <button type="button" class="btn-modal-confirm" style="background:#dc3545;" onclick="submitUserReport()">送信する</button>
            </div>
        </div>
    </div>
</div>

<!-- デッキ通報用モーダル -->
<div id="deckReportModal" class="sub-modal">
    <div class="sub-modal-content" style="max-width: 480px;">
        <div class="sub-modal-header">
            <span>デッキの通報</span>
            <span style="cursor:pointer;" onclick="closeDeckReportModal()">&times;</span>
        </div>
        <div class="sub-modal-body" style="padding: 20px;">
            <input type="hidden" id="report_deck_id">
            <p id="report_deck_title" style="font-weight: bold; margin-top: 0;"></p>

            <label style="font-size: 0.85rem; font-weight: bold; color: #555; display: block; margin-bottom: 6px;">通報理由 (必須)</label>
            <textarea id="report_deck_reason" rows="4" style="width: 100%; box-sizing: border-box; padding: 8px; border: 1px solid #ccc; border-radius: 4px;" placeholder="不適切なデッキ名、利用規約違反など"></textarea>
            
            <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 15px;">
                <button type="button" class="btn-modal-cancel" onclick="closeDeckReportModal()">キャンセル</button>
                <button type="button" class="btn-modal-confirm" style="background:#dc3545;" onclick="submitDeckReportFromUserDecks()">送信する</button>
            </div>
        </div>
    </div>
</div>

<script>
const IS_LOGGED_IN = <?= isset($_SESSION['user_id']) ? 'true' : 'false' ?>;
const TARGET_USER_ID = <?= (int)$targetUser['user_id'] ?>;

function copyDeck(deckId) {
    if (!confirm("このデッキをコピーしてマイデッキに登録しますか？")) return;
    fetch('/api/decks/copy', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ deck_id: deckId })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert("コピーに成功しました！マイデッキ一覧に移動します。");
            window.location.href = '/mydecks';
        } else {
            alert("コピーに失敗しました: " + (data.error || '不明なエラー'));
        }
    });
}

function openUserReportModal() {
    if (!IS_LOGGED_IN) {
        alert('通報機能を利用するにはログインが必要です。');
        window.location.href = '/login';
        return;
    }
    document.getElementById('modal_user_reason').value = '';
    document.getElementById('userReportModal').style.display = 'block';
}

function closeUserReportModal() {
    document.getElementById('userReportModal').style.display = 'none';
}

function submitUserReport() {
    const category = document.querySelector('input[name="modal_user_category"]:checked').value;
    const reason = document.getElementById('modal_user_reason').value.trim();

    if (!reason) {
        alert('通報理由を入力してください。');
        return;
    }

    fetch('/api/decks/report', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            reported_user_id: TARGET_USER_ID,
            report_type: 'user',
            user_report_category: category,
            reason: reason
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert('ユーザーの報告を受け付けました。ご協力ありがとうございます。');
            closeUserReportModal();
        } else {
            alert(data.error || '送信に失敗しました。');
        }
    })
    .catch(err => alert('通信エラーが発生しました。'));
}
function openReportModal(deckId, deckName, creatorName) {
    if (!IS_LOGGED_IN) {
        alert('通報機能を利用するにはログインが必要です。');
        window.location.href = '/login';
        return;
    }
    document.getElementById('report_deck_id').value = deckId;
    document.getElementById('report_deck_title').innerText = `デッキ: ${deckName}`;
    document.getElementById('report_deck_reason').value = '';
    document.getElementById('deckReportModal').style.display = 'block';
}

function closeDeckReportModal() {
    document.getElementById('deckReportModal').style.display = 'none';
}

function submitDeckReportFromUserDecks() {
    const deckId = document.getElementById('report_deck_id').value;
    const reason = document.getElementById('report_deck_reason').value.trim();

    if (!reason) {
        alert('通報理由を入力してください。');
        return;
    }

    fetch('/api/decks/report', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ 
            deck_id: deckId, 
            report_type: 'deck', 
            reason: reason 
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert('デッキの報告を受け付けました。ご協力ありがとうございます。');
            closeDeckReportModal();
        } else {
            alert(data.error || '送信に失敗しました。');
        }
    })
    .catch(err => alert('通信エラーが発生しました。'));
}
</script>