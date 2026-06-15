<?php namespace Models;

use PDO;

class Deck {
    private $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * 特定のユーザーが作成したデッキ一覧を取得する（フォーマット名、サムネイル画像付き）
     * 
     * @param int $userId
     * @return array
     */
    public function getByUserId(int $userId) {
        // card_detail テーブルを LEFT JOIN してサムネイル用画像パスを引っ張る
        $sql = "SELECT d.*, f.format_name, cd.imagepath AS thumbnail_imagepath
                FROM decks d 
                JOIN formats f ON d.format_id = f.format_id 
                LEFT JOIN card_detail cd ON d.thumbnail_card_id = cd.card_id
                WHERE d.user_id = :user_id 
                ORDER BY d.updated_at DESC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':user_id' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 特定のデッキに含まれるカードリストを取得する（モーダル表示用）
     * 
     * @param int $deckId
     * @return array
     */
    public function getCardsByDeckId(int $deckId) {
        $sql = "SELECT 
                    c.card_id, 
                    c.card_name, 
                    c.cost, 
                    cd.modelnum, 
                    cd.imagepath,
                    dc.quantity,
                    dc.card_type_in_deck,
                    -- 特殊タイプIDの取得
                    (SELECT GROUP_CONCAT(characteristics_id) FROM card_characteristics WHERE card_id = c.card_id) as char_ids,
                    -- 文明IDをカンマ区切りで取得
                    (SELECT GROUP_CONCAT(civilization_id) FROM card_civilization WHERE card_id = c.card_id) as civ_ids
                FROM deck_cards dc 
                JOIN card c ON dc.card_id = c.card_id 
                JOIN card_detail cd ON c.card_id = cd.card_id 
                WHERE dc.deck_id = :deck_id
                ORDER BY dc.sort_order ASC";
        
        // コンストラクタで注入された $this->pdo を使用するように統一しています
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':deck_id' => $deckId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 特定のデッキの基本情報を取得する（再編集・上書き時、サムネイルパス付き）
     * 
     * @param int $deckId
     * @param int $userId
     * @return array|false
     */
    public function getByIdAndUser(int $deckId, int $userId) {
        // 編集画面を開く際にもサムネイル情報を復元できるよう、card_detail を LEFT JOIN します
        $sql = "SELECT d.*, cd.imagepath AS thumbnail_imagepath 
                FROM decks d 
                LEFT JOIN card_detail cd ON d.thumbnail_card_id = cd.card_id
                WHERE d.deck_id = :did AND d.user_id = :uid";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':did' => $deckId, ':uid' => $userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}