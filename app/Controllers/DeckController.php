<?php namespace Controllers;

use Models\Database;
use Models\Deck;
use PDO;

require_once __DIR__ . '/../Views/view_helper.php';

class DeckController {
public function myDecks() {
        // ★修正: 未ログインでもアクセスできるようにリダイレクトを解除し、セッションにuser_idがなければ空の配列を渡す
        $myDecks = [];
        
        if (isset($_SESSION['user_id'])) {
            $pdo = Database::connect();
            $deckModel = new Deck($pdo);
            // ログインユーザーのデッキを取得
            $myDecks = $deckModel->getByUserId($_SESSION['user_id']);
        }

        // 取得したデータを 'decks' という名前でビューに渡す
        renderView('deck/index.php', ['decks' => $myDecks]);
    }
    /**
     * 公開デッキ検索画面
     */
    public function search() {
        $pdo = Database::connect();
        
        // 検索パラメータの取得
        $deckName = trim($_GET['deck_name'] ?? '');
        $formatId = $_GET['format_id'] ?? '';
        $cardName = trim($_GET['card_name'] ?? '');
        $civIds = $_GET['civ_ids'] ?? [];
        $civLogic = $_GET['civ_logic'] ?? 'include';

        // 基本SQL：is_public = 1 (公開) のみ
        $sql = "SELECT d.*, f.format_name, u.username as creator_name, cd.imagepath as thumbnail_imagepath
                FROM decks d
                JOIN formats f ON d.format_id = f.format_id
                JOIN users u ON d.user_id = u.user_id
                LEFT JOIN card_detail cd ON d.thumbnail_card_id = cd.card_id
                WHERE d.is_public = 1";
        
        $params = [];

        // 絞り込み条件の追加
        if ($deckName !== '') {
            $sql .= " AND d.deck_name LIKE :deck_name";
            $params[':deck_name'] = "%$deckName%";
        }
        if ($formatId !== '') {
            $sql .= " AND d.format_id = :format_id";
            $params[':format_id'] = (int)$formatId;
        }
        if ($cardName !== '') {
            // カンマ区切りで送られてきた複数のカード名を配列に分解（空要素は除外）
            $cardNames = array_filter(array_map('trim', explode(',', $cardName)), 'strlen');
            
            // 分解したカード名ごとに EXISTS 句を追加し、選択されたカードがすべて採用されているデッキを検索 (AND条件)
            foreach ($cardNames as $i => $name) {
                $paramName = ":card_name_$i";
                $sql .= " AND EXISTS (
                    SELECT 1 FROM deck_cards dc 
                    JOIN card c ON dc.card_id = c.card_id 
                    WHERE dc.deck_id = d.deck_id AND c.card_name LIKE $paramName
                )";
                $params[$paramName] = "%$name%";
            }
        }
        
        if (!empty($civIds)) {
            if ($civLogic === 'only') {
                // 【のみ検索】選択されていない（デッキに含めてはいけない）文明を算出
                $allCivs = [1, 2, 3, 4, 5, 6]; // 1:光 2:水 3:闇 4:火 5:自然 6:ゼロ
                $excludedCivs = array_diff($allCivs, array_map('intval', $civIds));

                if (!empty($excludedCivs)) {
                    $excludedList = implode(',', $excludedCivs);
                    // 「選択されていない文明」を持つカードが1枚もデッキに採用されていないこと（NOT EXISTS）
                    $sql .= " AND NOT EXISTS (
                        SELECT 1 FROM deck_cards dc 
                        JOIN card_civilization cc ON dc.card_id = cc.card_id 
                        WHERE dc.deck_id = d.deck_id AND cc.civilization_id IN ($excludedList)
                    )";
                }
            } else {
                // 【含む検索（従来のAND条件）】
                foreach ($civIds as $i => $id) {
                    $paramName = ":civ_id_$i";
                    $sql .= " AND EXISTS (
                        SELECT 1 FROM deck_cards dc 
                        JOIN card_civilization cc ON dc.card_id = cc.card_id 
                        WHERE dc.deck_id = d.deck_id AND cc.civilization_id = $paramName
                    )";
                    $params[$paramName] = (int)$id;
                }
            }
        }
        
        // デフォルト：最終更新日が新しい順
        $sql .= " ORDER BY d.updated_at DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $decks = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // 検索セレクトボックス用のフォーマット一覧（昇順）
        $stmtFormats = $pdo->query("SELECT format_id, format_name FROM formats ORDER BY format_id ASC");
        $formats = $stmtFormats->fetchAll(\PDO::FETCH_ASSOC);

        // 文明マスターデータ（DB依存を避けるための安全なハードコーディング）
        $civilizations = [
            ['civilization_id' => 1, 'civilization_name' => '光'],
            ['civilization_id' => 2, 'civilization_name' => '水'],
            ['civilization_id' => 3, 'civilization_name' => '闇'],
            ['civilization_id' => 4, 'civilization_name' => '火'],
            ['civilization_id' => 5, 'civilization_name' => '自然'],
            ['civilization_id' => 6, 'civilization_name' => 'ゼロ'],
        ];

        renderView('deck/search.php', [
            'decks' => $decks,
            'formats' => $formats,
            'civilizations' => $civilizations,
            'searchValues' => [
                'deck_name' => $deckName,
                'format_id' => $formatId,
                'card_name' => $cardName,
                'civ_ids' => $civIds,
                'civ_logic' => $civLogic
            ]
        ]);
    }

    /**
     * 公開デッキをマイデッキとして丸ごと複製保存するAPI
     */
    public function copyDeckApi() {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!isset($_SESSION['user_id'])) {
            header('Content-Type: application/json', true, 401);
            echo json_encode(['success' => false, 'error' => 'ログインが必要です。']);
            return;
        }

        $sourceDeckId = $input['deck_id'] ?? null;
        if (!$sourceDeckId) {
            echo json_encode(['success' => false, 'error' => 'コピー元デッキIDが指定されていません。']);
            return;
        }

        try {
            $pdo = Database::connect();
            $pdo->beginTransaction();

            // 1. コピー元デッキの情報を取得
            $stmt = $pdo->prepare("SELECT deck_name, format_id, thumbnail_card_id FROM decks WHERE deck_id = :did");
            $stmt->execute([':did' => $sourceDeckId]);
            $source = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$source) {
                throw new \Exception("コピー元のデッキが見つかりません。");
            }

            // 2. 自分の新規デッキとして複製登録（末尾に「 のコピー」を追加）
            $newDeckName = $source['deck_name'] . ' のコピー';
            
            $stmtInsert = $pdo->prepare("INSERT INTO decks (user_id, deck_name, format_id, thumbnail_card_id, is_public, created_at, updated_at) VALUES (:uid, :name, :fid, :tcid, 0, NOW(), NOW())");
            $stmtInsert->execute([
                ':uid' => $_SESSION['user_id'],
                ':name' => $newDeckName,
                ':fid' => $source['format_id'],
                ':tcid' => $source['thumbnail_card_id']
            ]);
            $newDeckId = $pdo->lastInsertId();

            // 3. コピー元デッキから採用カードリストを抽出（並び順 sort_order を考慮してソート取得）
            $stmtCards = $pdo->prepare("SELECT card_id, quantity, card_type_in_deck, sort_order FROM deck_cards WHERE deck_id = :did ORDER BY sort_order ASC");
            $stmtCards->execute([':did' => $sourceDeckId]);
            $cards = $stmtCards->fetchAll(PDO::FETCH_ASSOC);

            // 4. 新しく作成したデッキにカードを丸ごとインサート（sort_orderも完全に引き継ぐ）
            $stmtCardInsert = $pdo->prepare("INSERT INTO deck_cards (deck_id, card_id, quantity, card_type_in_deck, sort_order) VALUES (:did, :cid, :qty, :type, :order)");
            foreach ($cards as $c) {
                $stmtCardInsert->execute([
                    ':did' => $newDeckId,
                    ':cid' => $c['card_id'],
                    ':qty' => $c['quantity'],
                    ':type' => $c['card_type_in_deck'],
                    ':order' => $c['sort_order'] // 元の並び順を正確に継承
                ]);
            }

            $pdo->commit();
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);

        } catch (\Throwable $e) {
            if (isset($pdo)) $pdo->rollBack();
            header('Content-Type: application/json', true, 500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function create() {

        // formatテーブルから昇順で取得
        $pdo = Database::connect();
        $stmt = $pdo->query("SELECT format_id, format_name FROM formats ORDER BY format_id ASC");
        $formats = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        renderView('deck/create.php', [
            'hideFooter' => true,
            'formats' => $formats // ビューにフォーマットリストを渡す
        ]);
    }

// --- storeDeckApi (新規保存) の修正箇所 ---
    public function storeDeckApi() {
        // 公開ペナルティチェック
            if (!empty($input['is_public'])) {
                $chk = $pdo->prepare("SELECT public_ban_until FROM users WHERE user_id = :uid");
                $chk->execute([':uid' => $_SESSION['user_id']]);
                $u = $chk->fetch(PDO::FETCH_ASSOC);
                if ($u && !empty($u['public_ban_until']) && strtotime($u['public_ban_until']) > time()) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => '現在デッキの公開が制限されています（期限: ' . $u['public_ban_until'] . ' まで）。非公開で保存してください。']);
                    return;
                }
            }
        $input = json_decode(file_get_contents('php://input'), true);
        if (!isset($_SESSION['user_id'])) { 
            header('Content-Type: application/json', true, 401); // ★ステータス401を明示
            echo json_encode(['success' => false, 'error' => 'ログインが必要です']); 
            return; 
        }
        try {
            $pdo = \Models\Database::connect();
            $pdo->beginTransaction();

            // ★ is_public をインサートカラムに追加
            $stmt = $pdo->prepare("INSERT INTO decks (user_id, deck_name, format_id, thumbnail_card_id, is_public) VALUES (:uid, :name, :fid, :tcid, :is_pub)");
            $stmt->execute([
                ':uid' => $_SESSION['user_id'], 
                ':name' => $input['deck_name'], 
                ':fid' => $input['format_id'],
                ':tcid' => !empty($input['thumbnail_card_id']) ? $input['thumbnail_card_id'] : null,
                ':is_pub' => isset($input['is_public']) ? (int)$input['is_public'] : 0 // ★バインド追加
            ]);
            $deckId = $pdo->lastInsertId();

            $this->saveDeckCards($pdo, $deckId, $input['cards']);

            $pdo->commit();
            echo json_encode(['success' => true]);
        } catch (\Exception $e) {
            if (isset($pdo)) $pdo->rollBack();
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }
    
    public function viewDeckApi() {
        $deckId = $_GET['deck_id'] ?? null;
        if (!$deckId) {
            echo json_encode(['error' => 'Deck ID missing']);
            return;
        }

        $pdo = Database::connect();
        $deckModel = new Deck($pdo);
        $cards = $deckModel->getCardsByDeckId((int)$deckId);

        header('Content-Type: application/json');
        echo json_encode($cards);
    }
    public function deleteDeckApi() {
        $deckId = $_GET['deck_id'] ?? null;
        
        if (!$deckId || !isset($_SESSION['user_id'])) {
            echo json_encode(['success' => false, 'error' => '権限がありません']);
            return;
        }

        try {
            $pdo = Database::connect();
            // 自分のデッキであること、かつIDが一致することを条件に削除
            $stmt = $pdo->prepare("DELETE FROM decks WHERE deck_id = :did AND user_id = :uid");
            $result = $stmt->execute([
                ':did' => (int)$deckId,
                ':uid' => (int)$_SESSION['user_id']
            ]);

            // deck_cardsは外部キーの ON DELETE CASCADE で自動削除される設定であればこれでOK
            // もし設定していなければ個別に削除が必要です

            echo json_encode(['success' => $result]);
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }
    // 編集画面の表示
    public function edit() {
        $deckId = $_GET['deck_id'] ?? null;
        if (!$deckId || !isset($_SESSION['user_id'])) {
            header('Location: /mydecks');
            exit;
        }

        $pdo = Database::connect();
        $deckModel = new \Models\Deck($pdo);
        
        // デッキの基本情報とカードリストを取得
        $deck = $deckModel->getByIdAndUser((int)$deckId, (int)$_SESSION['user_id']);
        if (!$deck) {
            header('Location: /mydecks');
            exit;
        }
        $cards = $deckModel->getCardsByDeckId((int)$deckId);

        // formatテーブルから昇順で取得
        $stmt = $pdo->query("SELECT format_id, format_name FROM formats ORDER BY format_id ASC");
        $formats = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // 作成画面と同じテンプレートを使い、データを渡す
        renderView('deck/create.php', [
            'hideFooter' => true,
            'isEdit' => true,
            'deck' => $deck,
            'initialCards' => $cards,
            'formats' => $formats // ビューにフォーマットリストを渡す
        ]);
    }

    // デッキの上書き保存API
    public function updateDeckApi() {
        // 公開ペナルティチェック
        if (!empty($input['is_public'])) {
                $chk = $pdo->prepare("SELECT public_ban_until FROM users WHERE user_id = :uid");
                $chk->execute([':uid' => $_SESSION['user_id']]);
                $u = $chk->fetch(PDO::FETCH_ASSOC);
                if ($u && !empty($u['public_ban_until']) && strtotime($u['public_ban_until']) > time()) {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'error' => '現在デッキの公開が制限されています（期限: ' . $u['public_ban_until'] . ' まで）。非公開で保存してください。']);
                    return;
                }
            }
        $input = json_decode(file_get_contents('php://input'), true);
        if (!isset($_SESSION['user_id'])) { echo json_encode(['success' => false, 'error' => '権限不足']); return; }

        try {
            $pdo = \Models\Database::connect();
            $pdo->beginTransaction();

            // ★ is_public を更新対象に追加
            $stmt = $pdo->prepare("UPDATE decks SET deck_name = :name, format_id = :fid, thumbnail_card_id = :tcid, is_public = :is_pub, updated_at = NOW() WHERE deck_id = :did AND user_id = :uid");
            $stmt->execute([
                ':name' => $input['deck_name'], 
                ':fid' => $input['format_id'],
                ':tcid' => !empty($input['thumbnail_card_id']) ? $input['thumbnail_card_id'] : null,
                ':is_pub' => isset($input['is_public']) ? (int)$input['is_public'] : 0, // ★バインド追加
                ':did' => $input['deck_id'], 
                ':uid' => $_SESSION['user_id']
            ]);
            
            $stmtDel = $pdo->prepare("DELETE FROM deck_cards WHERE deck_id = :did");
            $stmtDel->execute([':did' => $input['deck_id']]);

            $this->saveDeckCards($pdo, $input['deck_id'], $input['cards']);

            $pdo->commit();
            echo json_encode(['success' => true]);
        } catch (\Exception $e) {
            if (isset($pdo)) $pdo->rollBack();
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    // --- 【追加】カード保存用の共通メソッド ---
    private function saveDeckCards($pdo, $deckId, $cards) {
        // quantity は常に 1 固定とし、sort_order をインサートします
        $stmtCard = $pdo->prepare("INSERT INTO deck_cards (deck_id, card_id, quantity, card_type_in_deck, sort_order) VALUES (:did, :cid, 1, :type, :order)");
        
        $order = 1;
        foreach ($cards as $c) {
            $stmtCard->execute([
                ':did' => $deckId,
                ':cid' => $c['id'],
                ':type' => $c['type'],
                ':order' => $order++ // 1から順に連番を設定
            ]);
        }
    }

    /**
     * ヘルプカード検索画面の表示
     */
    public function helpSearch() {
        // プロジェクトの「app」ディレクトリの絶対パスを取得
        $appPath = dirname(__DIR__);

        // views（小文字）と Views（大文字）の両方のフォルダに対応
        $viewDir = is_dir($appPath . '/Views') ? $appPath . '/Views' : $appPath . '/views';

        // 1. サブビュー（help_search.php）を読み込みます
        ob_start();
        $viewPath = $viewDir . '/deck/help_search.php';
        if (file_exists($viewPath)) {
            include $viewPath;
        } else {
            // deck フォルダの直下にない場合のフォールバック
            include $viewDir . '/help_search.php';
        }
        $content = ob_get_clean();

        // 2. 全体レイアウト（app.php）を読み込みます
        $layoutPath = $viewDir . '/app.php';
        if (file_exists($layoutPath)) {
            include $layoutPath;
        } else {
            // layouts フォルダなどに入っている場合のフォールバック
            $fallbackLayout = $viewDir . '/layouts/app.php';
            if (file_exists($fallbackLayout)) {
                include $fallbackLayout;
            } else {
                die("テンプレートファイル (app.php) が見つかりません。配置場所を確認してください。");
            }
        }
    }
/**
     * ヘルプ・FAQ画面の表示
     */
    public function help() {
        // ヘルプ用ビューの内容をバッファリングして取得
        ob_start();
        include __DIR__ . '/../Views/help/index.php'; // Viewsフォルダへの相対パスを環境に合わせて調整してください
        $content = ob_get_clean();

        // 共通レイアウトに埋め込んで表示
        include __DIR__ . '/../Views/layouts/app.php';
    }
    
    public function playtest() {
        $deckId = $_GET['deck_id'] ?? null;
        if (!$deckId) {
            header('Location: /mydecks');
            exit;
        }

        $db = \Models\Database::connect();
        
        // デッキ基本情報
        $stmt = $db->prepare("SELECT * FROM decks WHERE deck_id = :deck_id");
        $stmt->execute(['deck_id' => $deckId]);
        $deck = $stmt->fetch(\PDO::FETCH_ASSOC);

        // デッキ内カード情報 (サブクエリによる重複排除集計方式)
// デッキ内カード情報 (サブクエリによる重複排除集計方式)
        $stmt_cards = $db->prepare("
            SELECT 
                dc.*, 
                c.card_name, 
                c.cost, 
                cd.imagepath, 
                cd.twinpact,
                cd_partner.imagepath as combination_imagepath, 
                cc_self.combination_id,
                
                -- 各中間テーブルのデータをサブクエリで競合なく確実に取得
                (SELECT GROUP_CONCAT(cardtype_id) FROM card_cardtype WHERE card_id = dc.card_id) as cardtype_ids,
                (SELECT GROUP_CONCAT(CONCAT(ct.cardtype_id, ':', ct.cardtype_name)) FROM card_cardtype cct JOIN cardtype ct ON cct.cardtype_id = ct.cardtype_id WHERE cct.card_id = dc.card_id) as cardtype_data,
                (SELECT GROUP_CONCAT(characteristics_id) FROM card_characteristics WHERE card_id = dc.card_id) as characteristics_ids,
                (SELECT GROUP_CONCAT(ability_id) FROM card_ability WHERE card_id = dc.card_id) as ability_ids,
                (SELECT GROUP_CONCAT(CONCAT(r.race_id, ':', r.race_name)) FROM card_race cr JOIN race r ON cr.race_id = r.race_id WHERE cr.card_id = dc.card_id) as race_data,
                
                -- ツインパクト上面・下面を考慮した文明総数をサブクエリで安全に算出
                (
                    SELECT COUNT(DISTINCT cc_civ.civilization_id)
                    FROM card_combination cc_group
                    JOIN card_civilization cc_civ ON cc_group.card_id = cc_civ.card_id
                    WHERE cc_group.combination_id = cc_self.combination_id
                ) as twin_civ_count,
                
                (
                    SELECT COUNT(DISTINCT cc_self_civ.civilization_id)
                    FROM card_civilization cc_self_civ
                    WHERE cc_self_civ.card_id = dc.card_id
                ) as self_civ_count
                
            FROM deck_cards dc
            JOIN card c ON dc.card_id = c.card_id
            LEFT JOIN card_detail cd ON dc.card_id = cd.card_id
            
            -- 自カードのコンビネーション情報を取得
            LEFT JOIN card_combination cc_self ON dc.card_id = cc_self.card_id
            
            -- 【修正】相方カードの特定と画像結合（相方が複数ある場合は card_id が最小の1枚に制限して重複を防ぐ）
            LEFT JOIN card_combination cc_partner ON cc_self.combination_id = cc_partner.combination_id 
                AND cc_self.card_id <> cc_partner.card_id
                AND cc_partner.card_id = (
                    SELECT MIN(cc_sub.card_id)
                    FROM card_combination cc_sub
                    WHERE cc_sub.combination_id = cc_self.combination_id
                      AND cc_sub.card_id <> cc_self.card_id
                )
            LEFT JOIN card_detail cd_partner ON cc_partner.card_id = cd_partner.card_id
            
            WHERE dc.deck_id = :deck_id
            ORDER BY dc.sort_order ASC
        ");
        
        $stmt_cards->execute(['deck_id' => $deckId]);
        $rawCards = $stmt_cards->fetchAll(\PDO::FETCH_ASSOC);

        // 文明カウントの調整（ツインパクト等の一括カウント、または単体カードの文明数に振り分け）
        $cards = array_map(function($card) {
            $card['civ_count'] = !empty($card['combination_id']) ? $card['twin_civ_count'] : $card['self_civ_count'];
            unset($card['twin_civ_count'], $card['self_civ_count']);
            return $card;
        }, $rawCards);

        // ビューをロード
        include __DIR__ . '/../Views/deck/playtest.php';
    }
    /**
     * 通報API（ログイン必須・通報ペナルティチェック・ユーザー通報対応）
     */
    public function reportDeckApi() {
        header('Content-Type: application/json; charset=utf-8');

        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => '通報にはログインが必要です。']);
            exit;
        }

        $userId = (int)$_SESSION['user_id'];
        $pdo = Database::connect();

        // 1. 通報ペナルティ（通報禁止期間）の確認
        $stmtCheck = $pdo->prepare("SELECT report_ban_until FROM users WHERE user_id = :uid");
        $stmtCheck->execute([':uid' => $userId]);
        $user = $stmtCheck->fetch(PDO::FETCH_ASSOC);
        if ($user && !empty($user['report_ban_until']) && strtotime($user['report_ban_until']) > time()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => '現在通報機能が制限されています（期限: ' . $user['report_ban_until'] . ' まで）。']);
            exit;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $deckId = !empty($input['deck_id']) ? (int)$input['deck_id'] : null;
        $reportedUserId = !empty($input['reported_user_id']) ? (int)$input['reported_user_id'] : null;
        $reportType = ($input['report_type'] ?? 'deck') === 'user' ? 'user' : 'deck';
        $userCategory = ($reportType === 'user' && in_array($input['user_report_category'] ?? '', ['spam', 'inappropriate_name', 'other'])) 
                        ? $input['user_report_category'] : null;
        $reason = trim($input['reason'] ?? '');

        // ★追加: カテゴリーに応じた理由の自動補完
        if (empty($reason) && $reportType === 'user') {
            if ($userCategory === 'spam') {
                $reason = 'デッキの連投';
            } elseif ($userCategory === 'inappropriate_name') {
                $reason = '不適切なユーザー名';
            }
        }

        if (empty($reason) || ($reportType === 'deck' && !$deckId) || ($reportType === 'user' && !$reportedUserId)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => '入力情報が不足しています。']);
            exit;
        }

        try {
            // デッキ通報の場合は作成者を取得
            if ($reportType === 'deck') {
                $stmtDeck = $pdo->prepare("SELECT user_id FROM decks WHERE deck_id = :did");
                $stmtDeck->execute([':did' => $deckId]);
                $deck = $stmtDeck->fetch(PDO::FETCH_ASSOC);
                if (!$deck) {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'error' => '対象のデッキが見つかりません。']);
                    exit;
                }
                $reportedUserId = (int)$deck['user_id'];
            }

            $stmt = $pdo->prepare("
                INSERT INTO deck_reports (deck_id, reported_user_id, user_id, report_type, user_report_category, reason, created_at, updated_at) 
                VALUES (:deck_id, :reported_user_id, :user_id, :report_type, :user_report_category, :reason, NOW(), NOW())
            ");
            $stmt->execute([
                ':deck_id' => $deckId,
                ':reported_user_id' => $reportedUserId,
                ':user_id' => $userId,
                ':report_type' => $reportType,
                ':user_report_category' => $userCategory,
                ':reason' => $reason
            ]);

            echo json_encode(['success' => true]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    /**
     * 特定ユーザーの公開デッキ一覧画面
     */
    public function userPublicDecks() {
        $userId = (int)($_GET['user_id'] ?? 0);
        if (!$userId) {
            header('Location: /search');
            exit;
        }

        $pdo = Database::connect();

        // ユーザー情報の取得
        $stmtUser = $pdo->prepare("SELECT user_id, username FROM users WHERE user_id = :uid");
        $stmtUser->execute([':uid' => $userId]);
        $targetUser = $stmtUser->fetch(PDO::FETCH_ASSOC);

        if (!$targetUser) {
            header('Location: /search');
            exit;
        }

        // 公開デッキの取得
        $stmtDecks = $pdo->prepare("
            SELECT d.*, f.format_name, u.username as creator_name, cd.imagepath as thumbnail_imagepath
            FROM decks d
            JOIN formats f ON d.format_id = f.format_id
            JOIN users u ON d.user_id = u.user_id
            LEFT JOIN card_detail cd ON d.thumbnail_card_id = cd.card_id
            WHERE d.user_id = :uid AND d.is_public = 1
            ORDER BY d.updated_at DESC
        ");
        $stmtDecks->execute([':uid' => $userId]);
        $decks = $stmtDecks->fetchAll(PDO::FETCH_ASSOC);

        renderView('deck/user_decks.php', [
            'targetUser' => $targetUser,
            'decks' => $decks
        ]);
    }
    /**
     * デッキの公開 / 非公開切り替えAPI
     */
    public function setDeckPublicApi() {
        header('Content-Type: application/json; charset=utf-8');

        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'ログインが必要です。']);
            exit;
        }

        $userId = (int)$_SESSION['user_id'];
        $input = json_decode(file_get_contents('php://input'), true);
        $deckId = (int)($input['deck_id'] ?? 0);
        $isPublic = !empty($input['is_public']) ? 1 : 0;

        if (!$deckId) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'デッキIDが不足しています。']);
            exit;
        }

        try {
            $pdo = Database::connect();

            // 公開に設定する場合、ペナルティ（公開禁止期間）をチェック
            if ($isPublic === 1) {
                $stmtCheck = $pdo->prepare("SELECT public_ban_until FROM users WHERE user_id = :uid");
                $stmtCheck->execute([':uid' => $userId]);
                $user = $stmtCheck->fetch(PDO::FETCH_ASSOC);

                if ($user && !empty($user['public_ban_until']) && strtotime($user['public_ban_until']) > time()) {
                    http_response_code(403);
                    echo json_encode([
                        'success' => false, 
                        'error' => "現在ペナルティが科されているため、デッキを公開できません。\n(期限: " . date('Y/m/d H:i', strtotime($user['public_ban_until'])) . " まで)"
                    ]);
                    exit;
                }
            }

            // 自分のデッキの公開ステータスを更新
            $stmt = $pdo->prepare("UPDATE decks SET is_public = :is_pub, updated_at = NOW() WHERE deck_id = :did AND user_id = :uid");
            $stmt->execute([
                ':is_pub' => $isPublic,
                ':did' => $deckId,
                ':uid' => $userId
            ]);

            if ($stmt->rowCount() === 0) {
                // デッキが存在しないか所有者が異なる場合
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => '対象のデッキが見つかりません。']);
                exit;
            }

            echo json_encode(['success' => true, 'is_public' => $isPublic]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * 楽天市場APIを用いたデッキ価格査定API
     */
    public function estimatePriceApi() {
        // ★ タイムアウトを120秒に延長（60枚デッキ等の長時間のAPI呼び出しに対応）
        set_time_limit(120);

        header('Content-Type: application/json; charset=utf-8');

        $deckId = $_GET['deck_id'] ?? null;
        if (!$deckId) {
            http_response_code(400);
            echo json_encode(['error' => 'Deck ID is required']);
            exit;
        }

        $appId = trim($_ENV['RAKUTEN_APP_ID'] ?? $_SERVER['RAKUTEN_APP_ID'] ?? getenv('RAKUTEN_APP_ID') ?: '5e43be83-582b-4e0c-aca5-a2a2cdab185d');
        $accessKey = trim($_ENV['RAKUTEN_ACCESS_KEY'] ?? $_SERVER['RAKUTEN_ACCESS_KEY'] ?? getenv('RAKUTEN_ACCESS_KEY') ?: 'pk_6YPrWKh1sowRK0R3SSZQDvsmzoPzPZtgIytCQoajcwj');
        $affiliateId = trim($_ENV['RAKUTEN_AFFILIATE_ID'] ?? $_SERVER['RAKUTEN_AFFILIATE_ID'] ?? getenv('RAKUTEN_AFFILIATE_ID') ?: '');

        // アフィリエイトIDが未設定・ダミーの場合は除外
        if (empty($affiliateId) || str_contains($affiliateId, 'YOUR_')) {
            $affiliateId = null;
        }
        // アプリIDが取得できていない場合は即座に分かりやすいエラーを返す
        if (empty($appId)) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Railwayの環境変数に「RAKUTEN_APP_ID」が設定されていません。RailwayダッシュボードのVariablesを確認してください。'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        try {
            $pdo = Database::connect();
            $deckModel = new Deck($pdo);
            $cards = $deckModel->getCardsByDeckId((int)$deckId);

            $mainCards = array_filter($cards, function($c) {
                $isSpecial = ($c['card_type_in_deck'] ?? '') === 'special' 
                    || (isset($c['card_name']) && (str_contains($c['card_name'], 'ドルマゲドン') || str_contains($c['card_name'], '零龍')));
                if ($isSpecial) return false;
                $type = $c['card_type_in_deck'] ?? 'main';
                return empty($type) || $type === 'main';
            });

            $cardMap = [];
            foreach ($mainCards as $c) {
                $name = trim($c['card_name']);
                $qty = (int)($c['quantity'] ?? 1);
                $isTwinpact = !empty($c['twinpact']) && !empty($c['partner_card_id']);

                $topName = $name;
                $bottomName = null;

                if ($isTwinpact) {
                    $selfId = (int)$c['card_id'];
                    $partnerId = (int)$c['partner_card_id'];
                    // card_idが小さい方を上面、大きい方を下面とする
                    if ($selfId < $partnerId) {
                        $topName = $c['card_name'];
                        $bottomName = $c['partner_card_name'];
                    } else {
                        $topName = $c['partner_card_name'];
                        $bottomName = $c['card_name'];
                    }
                }

                $key = $isTwinpact ? "{$topName} / {$bottomName}" : $name;

                if (!isset($cardMap[$key])) {
                    $cardMap[$key] = [
                        'display_name' => $key,
                        'is_twinpact'  => $isTwinpact,
                        'top_name'     => $topName,
                        'bottom_name'  => $bottomName,
                        'quantity'     => 0
                    ];
                }
                $cardMap[$key]['quantity'] += $qty;
            }
            $items = [];
            $totalPrice = 0;
            $notFoundCount = 0;
            $debugLog = []; // ★ デバッグ情報収集用

            foreach ($cardMap as $cardInfo) {
                $qty = $cardInfo['quantity'];
                $isTwinpact = $cardInfo['is_twinpact'];

                // 記号除去用ヘルパー
                $cleaner = function($str) {
                    $s = explode('/', $str)[0];
                    $s = preg_replace('/[・～〜「」『』【】“”"\'()（）]/u', ' ', $s);
                    return preg_replace('/\s+/', ' ', trim($s));
                };

                $cleanTop = $cleaner($cardInfo['top_name']);
                $cleanBottom = $isTwinpact ? $cleaner($cardInfo['bottom_name']) : '';

                // ★ ツインパクトなら上面と下面の両方をキーワードに含める
                if ($isTwinpact && !empty($cleanBottom)) {
                    $keyword = "デュエルマスターズ {$cleanTop} {$cleanBottom}";
                } else {
                    $keyword = "デュエルマスターズ {$cleanTop}";
                }

                $ngKeywords = 'スリーブ プレイマット デッキケース ケース マット オリパ くじ BOX パック 箱 ファイル バインダー スリーブセット';

                $apiBaseUrl = 'https://openapi.rakuten.co.jp/ichibams/api/IchibaItem/Search/20260701';

                $norm = function($str) {
                    return mb_strtolower(preg_replace('/[・～〜「」『』【】“”"\'()（）\s\/]/u', '', $str));
                };
                $normTop = $norm($cardInfo['top_name']);
                $normBottom = $isTwinpact ? $norm($cardInfo['bottom_name']) : '';

                // 検索キーワード（ツインパクトも検索時は最も確実な上面名＋下面名）
                $cleanTop = trim(preg_replace('/[・～〜「」『』【】“”"\'()（）\/]/u', ' ', $cardInfo['top_name']));
                $cleanBottom = $isTwinpact ? trim(preg_replace('/[・～〜「」『』【】“”"\'()（）\/]/u', ' ', $cardInfo['bottom_name'])) : '';

                if ($isTwinpact && !empty($cleanBottom)) {
                    $keyword = "デュエマ {$cleanTop} {$cleanBottom}";
                } else {
                    $keyword = "デュエマ {$cleanTop}";
                }
                
                $queryParams = [
                    'applicationId' => $appId,
                    'accessKey'     => $accessKey,
                    'keyword'       => $keyword,
                    'NGKeyword'     => $ngKeywords, // 楽天API公式の除外キーワード機能
                    'sort'          => '+itemPrice',
                    'hits'          => 20,           // ★ 上位10件取得して精査
                    'minPrice'      => 10,
                ];

                if (!empty($affiliateId)) {
                    $queryParams['affiliateId'] = $affiliateId;
                }

                $url = $apiBaseUrl . '?' . http_build_query($queryParams);
                
                // cURLで通信
                $ch = curl_init();
                curl_setopt_array($ch, [
                    CURLOPT_URL            => $url,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT        => 8,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
                    CURLOPT_HTTPHEADER     => [
                        'Origin: https://due-muscle.up.railway.app',
                        'Referer: https://due-muscle.up.railway.app/',
                        'Authorization: Bearer ' . $accessKey
                    ]
                ]);
                $responseBody = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curlErr  = curl_error($ch);
                curl_close($ch);

                $data = $responseBody ? json_decode($responseBody, true) : null;

                $minPrice = null;
                $affiliateUrl = '';
                $itemName = '';

                $itemList = $data['Items'] ?? $data['items'] ?? [];
                $ngTitlePattern = '/(スリーブ|プレイマット|デッキケース|ラバーマット|ストレージボックス|デッキシールド|カードファイル|バインダー|未開封BOX|未開封パック)/u';

                foreach ($itemList as $rawItem) {
                    $candidate = $rawItem['Item'] ?? $rawItem;
                    $title = $candidate['itemName'] ?? $candidate['title'] ?? '';

                    // サプライ系ワードが含まれていたらスキップ
                    if (preg_match($ngTitlePattern, $title)) {
                        continue;
                    }

                    // ツインパクトなら上面・下面両方の一致、通常なら上面の一致を確認
                    if ($isTwinpact && !empty($normBottom)) {
                        if (str_contains($normTitle, $normTop) && str_contains($normTitle, $normBottom)) {
                            $minPrice = (int)($candidate['itemPrice'] ?? $candidate['price'] ?? 0);
                            $affiliateUrl = $candidate['affiliateUrl'] ?? $candidate['itemUrl'] ?? '';
                            $itemName = $title;
                            $totalPrice += ($minPrice * $qty);
                            break;
                        }
                    } else {
                        // 通常カードは上面名の一致を確認
                        if (str_contains($normTitle, $normTop)) {
                            $minPrice = (int)($candidate['itemPrice'] ?? $candidate['price'] ?? 0);
                            $affiliateUrl = $candidate['affiliateUrl'] ?? $candidate['itemUrl'] ?? '';
                            $itemName = $title;
                            $totalPrice += ($minPrice * $qty);
                            break;
                        }
                    }

                    // 条件を満たした最安商品を採択
                    $minPrice = (int)($candidate['itemPrice'] ?? $candidate['price'] ?? 0);
                    $affiliateUrl = $candidate['affiliateUrl'] ?? $candidate['itemUrl'] ?? '';
                    $itemName = $title;
                    $totalPrice += ($minPrice * $qty);
                    break;
                }

                if ($minPrice === null) {
                    $notFoundCount++;
                }

                // デバッグログ
                $debugLog[] = [
                    'card_name'       => $cardInfo['display_name'],
                    'search_keyword'  => $keyword,
                    'http_code'       => $httpCode,
                    'hit_count'       => count($itemList),
                    'picked_item'     => $itemName ?: null
                ];

                // 査定結果アイテム
                $items[] = [
                    'card_name'     => $cardInfo['display_name'],
                    'quantity'      => $qty,
                    'price'         => $minPrice,
                    'subtotal'      => $minPrice !== null ? ($minPrice * $qty) : null,
                    'affiliate_url' => $affiliateUrl,
                    'item_title'    => $itemName,
                ];

                usleep(250000);
            }

            echo json_encode([
                'success'        => true,
                'total_price'    => $totalPrice,
                'cards'          => $items,
                'not_found_cards'=> $notFoundCount,
                'debug'          => [
                    'used_app_id'     => substr($appId, 0, 6) . '******', // 安全のため伏字表示
                    'logs'            => $debugLog
                ]
            ], JSON_UNESCAPED_UNICODE);


        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
        exit;
    }
}
