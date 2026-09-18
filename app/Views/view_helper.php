<?php
/**
 * ビューをレンダリングするためのヘルパー関数
 * 
 * @param string $viewPath Viewsディレクトリからの相対パス
 * @param array $data ビューに渡すデータ（連想配列）
 */
function renderView($viewPath, $data =[]) {
    // $data のキーを変数として展開（例: ['errors' => []] -> $errors という変数になる）
    extract($data);

    // バッファリング開始：出力されるHTMLを一度変数に格納するため
    ob_start();
    
    // 指定されたビューファイルを読み込む
    require __DIR__ . '/' . $viewPath;
    
    // バッファの内容を取得してクリア
    $content = ob_get_clean();

    // 共通レイアウトを読み込む（$content 変数がこの中で echo される）
    require __DIR__ . '/layouts/app.php';
}
/**
 * ログインユーザーの未読お知らせ件数を取得
 */
function getUnreadNotificationCount() {
    if (empty($_SESSION['user_id'])) return 0;
    try {
        $pdo = \Models\Database::connect();
        $uid = (int)$_SESSION['user_id'];
        
        // 既読後1週間以上経過したものを自動削除済みに更新
        $pdo->prepare("
            UPDATE user_notification_status 
            SET is_deleted = 1 
            WHERE user_id = :uid AND read_at IS NOT NULL AND read_at < DATE_SUB(NOW(), INTERVAL 1 WEEK)
        ")->execute([':uid' => $uid]);

        $sql = "
            SELECT COUNT(*) 
            FROM notifications n
            LEFT JOIN user_notification_status s ON n.notification_id = s.notification_id AND s.user_id = :uid1
            WHERE (n.user_id IS NULL OR n.user_id = :uid2)
              AND (s.is_deleted IS NULL OR s.is_deleted = 0)
              AND (s.read_at IS NULL)
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':uid1' => $uid,
            ':uid2' => $uid
        ]);
        return (int)$stmt->fetchColumn();
    } catch (\Exception $e) {
        return 0;
    }
}