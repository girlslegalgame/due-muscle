<?php namespace Controllers;

use Models\Database;
use PDO;

require_once __DIR__ . '/../Views/view_helper.php';

class AdminController {

    private function ensureAdmin() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
            header('Location: /login');
            exit;
        }
    }

    public function index() {
        $this->ensureAdmin();

        $pdo = Database::connect();
        $sql = "
            SELECT 
                r.*, 
                d.deck_name, 
                d.is_public,
                reporter.username as reporter_name,
                creator.username as creator_name
            FROM deck_reports r
            JOIN decks d ON r.deck_id = d.deck_id
            JOIN users creator ON d.user_id = creator.user_id
            LEFT JOIN users reporter ON r.user_id = reporter.user_id
            ORDER BY r.status = 'pending' DESC, r.created_at DESC
        ";
        $reports = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

        renderView('admin/admin.php', ['reports' => $reports]);
    }

    public function handleReportApi() {
        $this->ensureAdmin();
        header('Content-Type: application/json; charset=utf-8');

        $input = json_decode(file_get_contents('php://input'), true);
        $reportId = (int)($input['report_id'] ?? 0);
        $deckId = (int)($input['deck_id'] ?? 0);
        $action = $input['action'] ?? ''; // 'make_private', 'dismiss'

        if (!$reportId || !$deckId) {
            http_response_code(400);
            echo json_encode(['error' => '不正なパラメータです']);
            exit;
        }

        try {
            $pdo = Database::connect();
            $pdo->beginTransaction();

            if ($action === 'make_private') {
                $pdo->prepare("UPDATE decks SET is_public = 0 WHERE deck_id = :did")->execute([':did' => $deckId]);
                $pdo->prepare("UPDATE deck_reports SET status = 'resolved' WHERE report_id = :rid")->execute([':rid' => $reportId]);
            } elseif ($action === 'dismiss') {
                $pdo->prepare("UPDATE deck_reports SET status = 'dismissed' WHERE report_id = :rid")->execute([':rid' => $reportId]);
            }

            $pdo->commit();
            echo json_encode(['success' => true]);
        } catch (\Exception $e) {
            if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
        exit;
    }
}