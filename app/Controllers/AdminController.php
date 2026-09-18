<?php namespace Controllers;

use Models\Database;
use PDO;

require_once __DIR__ . '/../Views/view_helper.php';

class AdminController {

    private function ensureAdmin() {
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
            header('Location: /login');
            exit;
        }
    }

    public function index() {
        $this->ensureAdmin();
        $pdo = Database::connect();

        // 1. 通報一覧
        $sqlReports = "
            SELECT 
                r.*, 
                d.deck_name, d.is_public,
                reporter.username as reporter_name,
                creator.username as creator_name
            FROM deck_reports r
            JOIN decks d ON r.deck_id = d.deck_id
            JOIN users creator ON r.reported_user_id = creator.user_id
            LEFT JOIN users reporter ON r.user_id = reporter.user_id
            ORDER BY r.status = 'pending' DESC, r.created_at DESC
        ";
        $reports = $pdo->query($sqlReports)->fetchAll(PDO::FETCH_ASSOC);

        // 2. ユーザー一覧
        $users = $pdo->query("SELECT user_id, username, email, role, public_ban_until, report_ban_until, created_at FROM users ORDER BY user_id DESC")->fetchAll(PDO::FETCH_ASSOC);

        renderView('admin/admin.php', ['reports' => $reports, 'users' => $users]);
    }

    /**
     * 特定ユーザーのデッキ一覧取得API
     */
    public function userDecksApi() {
        $this->ensureAdmin();
        header('Content-Type: application/json; charset=utf-8');
        $userId = (int)($_GET['user_id'] ?? 0);

        $pdo = Database::connect();
        $stmt = $pdo->prepare("SELECT deck_id, deck_name, is_public, updated_at FROM decks WHERE user_id = :uid ORDER BY updated_at DESC");
        $stmt->execute([':uid' => $userId]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }

    /**
     * ペナルティ操作API (付与 / 解除)
     */
    public function applyPenaltyApi() {
        $this->ensureAdmin();
        header('Content-Type: application/json; charset=utf-8');

        $input = json_decode(file_get_contents('php://input'), true);
        $userId = (int)($input['user_id'] ?? 0);
        $deckId = (int)($input['deck_id'] ?? 0);
        $type = $input['type'] ?? '';
        $action = $input['action'] ?? '';
        $reason = trim($input['reason'] ?? '利用規約違反のため');

        if (!$userId || !in_array($type, ['public_ban', 'report_ban'])) {
            http_response_code(400);
            echo json_encode(['error' => '不正なパラメータです']);
            exit;
        }

        $col = ($type === 'public_ban') ? 'public_ban_until' : 'report_ban_until';
        $val = ($action === 'apply_1week') ? date('Y-m-d H:i:s', strtotime('+1 week')) : null;

        $pdo = Database::connect();
        $stmt = $pdo->prepare("UPDATE users SET $col = :val WHERE user_id = :uid");
        $stmt->execute([':val' => $val, ':uid' => $userId]);

        // ペナルティ付与時の自動通知
        if ($action === 'apply_1week') {
            if ($type === 'public_ban') {
                $deckName = $deckId ? $pdo->query("SELECT deck_name FROM decks WHERE deck_id = $deckId")->fetchColumn() : '対象のデッキ';
                $msg = "デッキ「{$deckName}」における規約違反に伴い、デッキを非公開としたうえで【1週間のデッキ公開禁止ペナルティ】を適用いたしました。\n\n理由: {$reason}\n期間: " . date('Y年m月d日 H:i', strtotime('+1 week')) . " まで";
                $this->sendNotification($pdo, $userId, "【重要】デッキ公開制限ペナルティのお知らせ", $msg);
            } elseif ($type === 'report_ban') {
                $msg = "度重なる過度な通報や不正な通報利用が確認されたため、【1週間の通報機能利用禁止ペナルティ】を適用いたしました。\n\n理由: {$reason}\n期間: " . date('Y年m月d日 H:i', strtotime('+1 week')) . " まで";
                $this->sendNotification($pdo, $userId, "【重要】通報機能利用制限のお知らせ", $msg);
            }
        }

        echo json_encode(['success' => true, 'new_value' => $val]);
        exit;
    }

    /**
     * ユーザー情報更新API (ユーザー名変更)
     */
    public function updateUserApi() {
        $this->ensureAdmin();
        header('Content-Type: application/json; charset=utf-8');

        $input = json_decode(file_get_contents('php://input'), true);
        $userId = (int)($input['user_id'] ?? 0);
        $username = trim($input['username'] ?? '');

        if (!$userId || empty($username)) {
            http_response_code(400);
            echo json_encode(['error' => 'ユーザー名を入力してください']);
            exit;
        }

        $pdo = Database::connect();
        $stmt = $pdo->prepare("UPDATE users SET username = :name, updated_at = NOW() WHERE user_id = :uid");
        $stmt->execute([':name' => $username, ':uid' => $userId]);

        echo json_encode(['success' => true]);
        exit;
    }

    /**
     * 通報の処理API
     */
    public function handleReportApi() {
        $this->ensureAdmin();
        header('Content-Type: application/json; charset=utf-8');

        $input = json_decode(file_get_contents('php://input'), true);
        $reportId = (int)($input['report_id'] ?? 0);
        $deckId = (int)($input['deck_id'] ?? 0);
        $action = $input['action'] ?? '';
        $reason = trim($input['reason'] ?? '利用規約違反が確認されたため');

        $pdo = Database::connect();

        // 対象通報とデッキ・ユーザー情報の取得
        $stmtInfo = $pdo->prepare("
            SELECT r.*, d.deck_name, d.user_id as creator_id, u.username as creator_name
            FROM deck_reports r
            JOIN decks d ON r.deck_id = d.deck_id
            JOIN users u ON d.user_id = u.user_id
            WHERE r.report_id = :rid
        ");
        $stmtInfo->execute([':rid' => $reportId]);
        $report = $stmtInfo->fetch(PDO::FETCH_ASSOC);

        if ($action === 'make_private') {
            // デッキ非公開化
            $pdo->prepare("UPDATE decks SET is_public = 0 WHERE deck_id = :did")->execute([':did' => $deckId]);
            if ($reportId) {
                $pdo->prepare("UPDATE deck_reports SET status = 'resolved' WHERE report_id = :rid")->execute([':rid' => $reportId]);
            }

            // デッキ作成者へ自動通知
            $creatorId = $report ? $report['creator_id'] : (int)$pdo->query("SELECT user_id FROM decks WHERE deck_id = $deckId")->fetchColumn();
            $deckName = $report ? $report['deck_name'] : $pdo->query("SELECT deck_name FROM decks WHERE deck_id = $deckId")->fetchColumn();

            $msg = "作成されたデッキ「{$deckName}」は、以下の理由により非公開に設定されました。\n\n理由: {$reason}\n\n内容をご確認・修正のうえ、再度公開設定を行ってください。";
            $this->sendNotification($pdo, $creatorId, "【重要】デッキ非公開のお知らせ", $msg, "/decks/edit?deck_id=" . $deckId, "デッキを編集する");

        } elseif ($action === 'dismiss') {
            // 通報却下
            $pdo->prepare("UPDATE deck_reports SET status = 'dismissed' WHERE report_id = :rid")->execute([':rid' => $reportId]);

            // 通報者へ通知
            if ($report && !empty($report['user_id'])) {
                $targetName = $report['report_type'] === 'user' ? "ユーザー「{$report['creator_name']}」" : "デッキ「{$report['deck_name']}」";
                $msg = "ご報告いただきました{$targetName}につきまして、確認を行いましたが問題は見られなかったため、対応を見送らせていただきました。\nご協力ありがとうございました。";
                $this->sendNotification($pdo, $report['user_id'], "通報いただいた件についてのご案内", $msg);
            }
        }

        echo json_encode(['success' => true]);
        exit;
    }
    // --- AdminController 内へ追加する共通通知関数 ---
    private function sendNotification($pdo, $userId, $title, $message, $actionUrl = null, $actionLabel = null) {
        $stmt = $pdo->prepare("
            INSERT INTO notifications (user_id, title, message, action_url, action_label, created_at) 
            VALUES (:uid, :title, :msg, :url, :label, NOW())
        ");
        $stmt->execute([
            ':uid' => $userId,
            ':title' => $title,
            ':msg' => $message,
            ':url' => $actionUrl,
            ':label' => $actionLabel
        ]);
    }

    /**
     * お知らせ手動作成API
     */
    public function sendNotificationApi() {
        $this->ensureAdmin();
        header('Content-Type: application/json; charset=utf-8');

        $input = json_decode(file_get_contents('php://input'), true);
        $target = $input['target'] ?? 'all'; // 'all' または 'user'
        $targetUserId = ($target === 'user' && !empty($input['user_id'])) ? (int)$input['user_id'] : null;
        $title = trim($input['title'] ?? '');
        $message = trim($input['message'] ?? '');
        $url = trim($input['action_url'] ?? '') ?: null;
        $label = trim($input['action_label'] ?? '') ?: null;

        if (empty($title) || empty($message)) {
            http_response_code(400);
            echo json_encode(['error' => 'タイトルと本文は必須です']);
            exit;
        }

        try {
            $pdo = Database::connect();
            $this->sendNotification($pdo, $targetUserId, $title, $message, $url, $label);
            echo json_encode(['success' => true]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
        exit;
    }

}