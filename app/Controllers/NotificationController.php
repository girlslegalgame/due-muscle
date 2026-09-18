<?php namespace Controllers;

use Models\Database;
use PDO;

require_once __DIR__ . '/../Views/view_helper.php';

class NotificationController {
    public function index() {
        $pdo = Database::connect();
        $userId = $_SESSION['user_id'] ?? null;

        // 1. 全体へのお知らせ
        $globalStmt = $pdo->query("SELECT * FROM notifications WHERE user_id IS NULL ORDER BY created_at DESC LIMIT 50");
        $globalNotifications = $globalStmt->fetchAll(PDO::FETCH_ASSOC);

        // 2. ログインユーザー宛てのお知らせ
        $userNotifications = [];
        if ($userId) {
            $userStmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = :uid ORDER BY created_at DESC LIMIT 50");
            $userStmt->execute([':uid' => $userId]);
            $userNotifications = $userStmt->fetchAll(PDO::FETCH_ASSOC);
        }

        renderView('notification/index.php', [
            'globalNotifications' => $globalNotifications,
            'userNotifications' => $userNotifications
        ]);
    }
}