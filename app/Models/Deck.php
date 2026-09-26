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
     * 特定のデッキに含まれるカードリストを取得する（モーダル表示用・ZIP出力用）
     * 
     * @param int $deckId
     * @return array
     */
    public function getCardsByDeckId(int $deckId) {
        $sql = "SELECT 
                    c.card_id, 
                    c.card_name, 
                    c.cost, 
                    c.pow,
                    c.text,
                    cd.modelnum, 
                    cd.imagepath,
                    cd.twinpact,
                    cd.hypermode,
                    dc.quantity,
                    dc.card_type_in_deck,
                    cc.combination_id,
                    (SELECT GROUP_CONCAT(characteristics_id) FROM card_characteristics WHERE card_id = c.card_id) as char_ids,
                    (SELECT GROUP_CONCAT(civilization_id) FROM card_civilization WHERE card_id = c.card_id) as civ_ids,
                    -- カードタイプIDと typename を取得
                    (SELECT GROUP_CONCAT(cardtype_id) FROM card_cardtype WHERE card_id = c.card_id) as cardtype_ids,
                    (SELECT GROUP_CONCAT(typename SEPARATOR '/') FROM card_cardtype WHERE card_id = c.card_id) as typename,
                    -- 種族IDと種族名を取得
                    (SELECT GROUP_CONCAT(race_id) FROM card_race WHERE card_id = c.card_id) as race_ids,
                    (SELECT GROUP_CONCAT(r.race_name SEPARATOR '/') FROM card_race cr JOIN race r ON cr.race_id = r.race_id WHERE cr.card_id = c.card_id) as race_names,
                    
                    -- 相方の情報
                    (SELECT c_partner.card_id FROM card_combination cc_p JOIN card c_partner ON cc_p.card_id = c_partner.card_id WHERE cc_p.combination_id = cc.combination_id AND cc_p.card_id <> c.card_id LIMIT 1) as partner_card_id,
                    (SELECT cd_partner.hypermode FROM card_combination cc_p JOIN card_detail cd_partner ON cc_p.card_id = cd_partner.card_id WHERE cc_p.combination_id = cc.combination_id AND cc_p.card_id <> c.card_id LIMIT 1) as partner_hypermode,
                    (SELECT c_partner.card_name FROM card_combination cc_p JOIN card c_partner ON cc_p.card_id = c_partner.card_id WHERE cc_p.combination_id = cc.combination_id AND cc_p.card_id <> c.card_id LIMIT 1) as partner_card_name,
                    (SELECT c_partner.cost FROM card_combination cc_p JOIN card c_partner ON cc_p.card_id = c_partner.card_id WHERE cc_p.combination_id = cc.combination_id AND cc_p.card_id <> c.card_id LIMIT 1) as partner_cost,
                    (SELECT c_partner.pow FROM card_combination cc_p JOIN card c_partner ON cc_p.card_id = c_partner.card_id WHERE cc_p.combination_id = cc.combination_id AND cc_p.card_id <> c.card_id LIMIT 1) as partner_pow,
                    (SELECT c_partner.text FROM card_combination cc_p JOIN card c_partner ON cc_p.card_id = c_partner.card_id WHERE cc_p.combination_id = cc.combination_id AND cc_p.card_id <> c.card_id LIMIT 1) as partner_text,
                    (SELECT GROUP_CONCAT(civilization_id) FROM card_civilization WHERE card_id = (SELECT card_id FROM card_combination WHERE combination_id = cc.combination_id AND card_id <> c.card_id LIMIT 1)) as partner_civ_ids,
                    (SELECT GROUP_CONCAT(cardtype_id) FROM card_cardtype WHERE card_id = (SELECT card_id FROM card_combination WHERE combination_id = cc.combination_id AND card_id <> c.card_id LIMIT 1)) as partner_cardtype_ids,
                    (SELECT GROUP_CONCAT(typename SEPARATOR '/') FROM card_cardtype WHERE card_id = (SELECT card_id FROM card_combination WHERE combination_id = cc.combination_id AND card_id <> c.card_id LIMIT 1)) as partner_typename,
                    (SELECT GROUP_CONCAT(race_id) FROM card_race WHERE card_id = (SELECT card_id FROM card_combination WHERE combination_id = cc.combination_id AND card_id <> c.card_id LIMIT 1)) as partner_race_ids,
                    (SELECT GROUP_CONCAT(r.race_name SEPARATOR '/') FROM card_race cr JOIN race r ON cr.race_id = r.race_id WHERE cr.card_id = (SELECT card_id FROM card_combination WHERE combination_id = cc.combination_id AND card_id <> c.card_id LIMIT 1)) as partner_race_names,
                    (
                        SELECT JSON_ARRAYAGG(
                            JSON_OBJECT(
                                'card_id', c_sub.card_id,
                                'card_name', c_sub.card_name,
                                'cost', c_sub.cost,
                                'pow', c_sub.pow,
                                'text', c_sub.text,
                                'imagepath', cd_sub.imagepath,
                                'is_main_side', cc_sub.is_main_side,
                                'char_ids', (SELECT GROUP_CONCAT(characteristics_id) FROM card_characteristics WHERE card_id = c_sub.card_id),
                                'civ_ids', (SELECT GROUP_CONCAT(civilization_id) FROM card_civilization WHERE card_id = c_sub.card_id),
                                'cardtype_ids', (SELECT GROUP_CONCAT(cardtype_id) FROM card_cardtype WHERE card_id = c_sub.card_id),
                                'typename', (SELECT GROUP_CONCAT(typename SEPARATOR '/') FROM card_cardtype WHERE card_id = c_sub.card_id),
                                'race_ids', (SELECT GROUP_CONCAT(race_id) FROM card_race WHERE card_id = c_sub.card_id),
                                'race_names', (SELECT GROUP_CONCAT(r_sub.race_name SEPARATOR '/') FROM card_race cr_sub JOIN race r_sub ON cr_sub.race_id = r_sub.race_id WHERE cr_sub.card_id = c_sub.card_id)
                            )
                        )
                        FROM card_combination cc_sub
                        JOIN card c_sub ON cc_sub.card_id = c_sub.card_id
                        JOIN card_detail cd_sub ON c_sub.card_id = cd_sub.card_id
                        WHERE cc_sub.combination_id = cc.combination_id
                    ) as combination_members_json
                FROM deck_cards dc 
                JOIN card c ON dc.card_id = c.card_id 
                JOIN card_detail cd ON c.card_id = cd.card_id 
                LEFT JOIN card_combination cc ON c.card_id = cc.card_id
                WHERE dc.deck_id = :deck_id
                ORDER BY dc.sort_order ASC";
        
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