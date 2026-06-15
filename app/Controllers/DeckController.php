<?php namespace Controllers;

use Models\Database;
use Models\Deck;
use PDO;

require_once __DIR__ . '/../Views/view_helper.php';

class DeckController {
    public function myDecks() {
        if (!isset($_SESSION['user_id'])) {
            header('Location: /login');
            exit;
        }

        $pdo = Database::connect();
        $deckModel = new Deck($pdo);
        
        // ログインユーザーのデッキを取得
        $myDecks = $deckModel->getByUserId($_SESSION['user_id']);

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
            // 指定されたカード名が採用されているデッキを絞り込み
            $sql .= " AND EXISTS (
                SELECT 1 FROM deck_cards dc 
                JOIN card c ON dc.card_id = c.card_id 
                WHERE dc.deck_id = d.deck_id AND c.card_name LIKE :card_name
            )";
            $params[':card_name'] = "%$cardName%";
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

            // 3. コピー元デッキから採用カードリストを抽出
            $stmtCards = $pdo->prepare("SELECT card_id, quantity, card_type_in_deck FROM deck_cards WHERE deck_id = :did");
            $stmtCards->execute([':did' => $sourceDeckId]);
            $cards = $stmtCards->fetchAll(PDO::FETCH_ASSOC);

            // 4. 新しく作成したデッキにカードを丸ごとインサート
            $stmtCardInsert = $pdo->prepare("INSERT INTO deck_cards (deck_id, card_id, quantity, card_type_in_deck) VALUES (:did, :cid, :qty, :type)");
            foreach ($cards as $c) {
                $stmtCardInsert->execute([
                    ':did' => $newDeckId,
                    ':cid' => $c['card_id'],
                    ':qty' => $c['quantity'],
                    ':type' => $c['card_type_in_deck']
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
        if (!isset($_SESSION['user_id'])) {
            header('Location: /login');
            exit;
        }

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
        $input = json_decode(file_get_contents('php://input'), true);
        if (!isset($_SESSION['user_id'])) { echo json_encode(['success' => false, 'error' => 'ログインが必要']); return; }

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
   
}
