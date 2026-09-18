<?php namespace Controllers;

use Models\Database;
use PDO;

require_once __DIR__ . '/../Views/view_helper.php';

class NotificationController {

    public function index() {
        $pdo = Database::connect();
        $userId = $_SESSION['user_id'] ?? null;

        if ($userId) {
            // 1週間以上経過した既読お知らせを自動論理削除
            $pdo->prepare("
                UPDATE user_notification_status 
                SET is_deleted = 1 
                WHERE user_id = :uid AND read_at IS NOT NULL AND read_at < DATE_SUB(NOW(), INTERVAL 1 WEEK)
            ")->execute([':uid' => $userId]);
        }

        // 全体お知らせ取得
        $sqlGlobal = "
            SELECT n.*, (s.read_at IS NOT NULL) as is_read
            FROM notifications n
            LEFT JOIN user_notification_status s ON n.notification_id = s.notification_id AND s.user_id = :uid
            WHERE n.user_id IS NULL
              AND (s.is_deleted IS NULL OR s.is_deleted = 0)
            ORDER BY n.created_at DESC
        ";
        $stmtG = $pdo->prepare($sqlGlobal);
        $stmtG->execute([':uid' => $userId ?: 0]);
        $globalNotifications = $stmtG->fetchAll(PDO::FETCH_ASSOC);

        // 個別お知らせ取得
        $userNotifications = [];
        if ($userId) {
            $sqlUser = "
                SELECT n.*, (s.read_at IS NOT NULL) as is_read
                FROM notifications n
                LEFT JOIN user_notification_status s ON n.notification_id = s.notification_id AND s.user_id = :uid
                WHERE n.user_id = :uid
                  AND (s.is_deleted IS NULL OR s.is_deleted = 0)
                ORDER BY n.created_at DESC
            ";
            $stmtU = $pdo->prepare($sqlUser);
            $stmtU->execute([':uid' => $userId]);
            $userNotifications = $stmtU->fetchAll(PDO::FETCH_ASSOC);
        }

        renderView('notification/index.php', [
            'globalNotifications' => $globalNotifications,
            'userNotifications' => $userNotifications
        ]);
    }

    /**
     * 既読化API
     */
    public function markAsReadApi() {
        header('Content-Type: application/json; charset=utf-8');
        if (empty($_SESSION['user_id'])) {
            echo json_encode(['success' => true]);
            exit;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $notifId = (int)($input['notification_id'] ?? 0);
        $userId = (int)$_SESSION['user_id'];

        if (!$notifId) {
            http_response_code(400);
            echo json_encode(['error' => 'ID不足']);
            exit;
        }

        $pdo = Database::connect();
        $stmt = $pdo->prepare("
            INSERT INTO user_notification_status (notification_id, user_id, read_at) 
            VALUES (:nid, :uid, NOW())
            ON DUPLICATE KEY UPDATE read_at = COALESCE(read_at, NOW())
        ");
        $stmt->execute([':nid' => $notifId, ':uid' => $userId]);

        echo json_encode(['success' => true]);
        exit;
    }

    /**
     * 一括削除API（既読のみ / すべて）
     */
    public function deleteNotificationsApi() {
        header('Content-Type: application/json; charset=utf-8');
        if (empty($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['error' => 'ログインが必要です']);
            exit;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $type = $input['type'] ?? 'read'; // 'read' または 'all'
        $tab = $input['tab'] ?? 'all';    // 'global' または 'personal' または 'all'
        $userId = (int)$_SESSION['user_id'];

        $pdo = Database::connect();

        // 対象のお知らせIDを抽出
        $whereTab = "";
        if ($tab === 'global') $whereTab = "AND n.user_id IS NULL";
        if ($tab === 'personal') $whereTab = "AND n.user_id = $userId";

        $sql = "
            SELECT n.notification_id 
            FROM notifications n
            LEFT JOIN user_notification_status s ON n.notification_id = s.notification_id AND s.user_id = :uid
            WHERE (n.user_id IS NULL OR n.user_id = :uid)
              $whereTab
        ";
        if ($type === 'read') {
            $sql .= " AND s.read_at IS NOT NULL";
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute([':uid' => $userId]);
        $targetIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (!empty($targetIds)) {
            $stmtInsert = $pdo->prepare("
                INSERT INTO user_notification_status (notification_id, user_id, read_at, is_deleted)
                VALUES (:nid, :uid, NOW(), 1)
                ON DUPLICATE KEY UPDATE is_deleted = 1
            ");
            foreach ($targetIds as $nid) {
                $stmtInsert->execute([':nid' => $nid, ':uid' => $userId]);
            }
        }

        echo json_encode(['success' => true]);
        exit;
    }
}