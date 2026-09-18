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
        $type = $input['type'] ?? '';     // 'public_ban' または 'report_ban'
        $action = $input['action'] ?? ''; // 'apply_1week' または 'clear'

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

        $pdo = Database::connect();
        if ($action === 'make_private') {
            $pdo->prepare("UPDATE decks SET is_public = 0 WHERE deck_id = :did")->execute([':did' => $deckId]);
            $pdo->prepare("UPDATE deck_reports SET status = 'resolved' WHERE report_id = :rid")->execute([':rid' => $reportId]);
        } elseif ($action === 'dismiss') {
            $pdo->prepare("UPDATE deck_reports SET status = 'dismissed' WHERE report_id = :rid")->execute([':rid' => $reportId]);
        }

        echo json_encode(['success' => true]);
        exit;
    }
}