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
        // ★ 1. 後から追加された同名カードの自動置換 ＆ limit 超過分の自動カット調整
        try {
            // ダミーカードの形式（通常／ツインパクト／両面）および相方名との組み合わせ一致を検証して抽出
            $stmtDummies = $this->pdo->prepare("
                SELECT 
                    dc.dummy_name, 
                    dc.dummy_card_type, 
                    dc.dummy_partner_name,
                    COALESCE(c_twin.card_id, c_double.card_id, c_norm.card_id) as card_id, 
                    COALESCE(cd.limit, 4) as card_limit
                FROM deck_cards dc
                -- ツインパクト: 上面・下面名が一致し、twinpact=1
                LEFT JOIN card c_top ON dc.dummy_name = c_top.card_name AND dc.dummy_card_type = 'twinpact'
                LEFT JOIN card_combination cc_top ON c_top.card_id = cc_top.card_id AND cc_top.is_main_side = 1
                LEFT JOIN card_combination cc_btm ON cc_top.combination_id = cc_btm.combination_id AND cc_top.card_id <> cc_btm.card_id
                LEFT JOIN card c_btm ON cc_btm.card_id = c_btm.card_id AND dc.dummy_partner_name = c_btm.card_name
                LEFT JOIN card_detail cd_twin ON c_top.card_id = cd_twin.card_id AND cd_twin.twinpact = 1
                LEFT JOIN card c_twin ON (c_top.card_id = c_twin.card_id AND c_btm.card_id IS NOT NULL AND cd_twin.card_id IS NOT NULL)

                -- 両面カード: 表面・裏面名が一致し、twinpact=0 または NULL
                LEFT JOIN card c_front ON dc.dummy_name = c_front.card_name AND dc.dummy_card_type = 'double'
                LEFT JOIN card_combination cc_front ON c_front.card_id = cc_front.card_id AND cc_front.is_main_side = 1
                LEFT JOIN card_combination cc_back ON cc_front.combination_id = cc_back.combination_id AND cc_front.card_id <> cc_back.card_id
                LEFT JOIN card c_back ON cc_back.card_id = c_back.card_id AND dc.dummy_partner_name = c_back.card_name
                LEFT JOIN card_detail cd_double ON c_front.card_id = cd_double.card_id AND (cd_double.twinpact = 0 OR cd_double.twinpact IS NULL)
                LEFT JOIN card c_double ON (c_front.card_id = c_double.card_id AND c_back.card_id IS NOT NULL AND cd_double.card_id IS NOT NULL)

                -- 通常カード: 単面（cc_norm.combination_id IS NULL）
                LEFT JOIN card c_norm ON dc.dummy_name = c_norm.card_name AND (dc.dummy_card_type = 'normal' OR dc.dummy_card_type IS NULL)
                LEFT JOIN card_combination cc_norm ON c_norm.card_id = cc_norm.card_id AND cc_norm.combination_id IS NULL

                LEFT JOIN card_detail cd ON COALESCE(c_twin.card_id, c_double.card_id, c_norm.card_id) = cd.card_id
                WHERE dc.deck_id = :deck_id 
                  AND dc.dummy_name IS NOT NULL
                  AND COALESCE(c_twin.card_id, c_double.card_id, c_norm.card_id) IS NOT NULL
                GROUP BY dc.dummy_name, dc.dummy_card_type, dc.dummy_partner_name, card_id, cd.limit
            ");
            $stmtDummies->execute([':deck_id' => $deckId]);
            $matchedDummies = $stmtDummies->fetchAll(PDO::FETCH_ASSOC);

            foreach ($matchedDummies as $m) {
                $dName = $m['dummy_name'];
                $dType = $m['dummy_card_type'] ?? 'normal';
                $dpName = $m['dummy_partner_name'];
                $realCardId = (int)$m['card_id'];
                $cardLimit = (int)$m['card_limit'];

                $stmtOrders = $this->pdo->prepare("
                    SELECT sort_order FROM deck_cards 
                    WHERE deck_id = :deck_id 
                      AND dummy_name = :dname 
                      AND (dummy_card_type = :dtype OR (:dtype = 'normal' AND dummy_card_type IS NULL))
                      AND (dummy_partner_name = :dpname OR (:dpname IS NULL AND dummy_partner_name IS NULL))
                    ORDER BY sort_order ASC
                ");
                $stmtOrders->execute([
                    ':deck_id' => $deckId, 
                    ':dname'   => $dName,
                    ':dtype'   => $dType,
                    ':dpname'  => $dpName
                ]);
                $orders = $stmtOrders->fetchAll(PDO::FETCH_COLUMN);

                if (count($orders) > $cardLimit) {
                    $excessOrders = array_slice($orders, $cardLimit);
                    if (!empty($excessOrders)) {
                        $inOrders = implode(',', array_map('intval', $excessOrders));
                        $this->pdo->exec("
                            DELETE FROM deck_cards 
                            WHERE deck_id = {$deckId} AND sort_order IN ({$inOrders})
                        ");
                    }
                }

                if ($cardLimit > 0) {
                    $stmtUpdate = $this->pdo->prepare("
                        UPDATE deck_cards 
                        SET card_id = :cid, 
                            dummy_name = NULL, 
                            dummy_cost = NULL, 
                            dummy_civ_ids = NULL,
                            dummy_card_type = 'normal',
                            dummy_partner_name = NULL,
                            dummy_partner_cost = NULL,
                            dummy_partner_civ_ids = NULL
                        WHERE deck_id = :deck_id 
                          AND dummy_name = :dname
                          AND (dummy_card_type = :dtype OR (:dtype = 'normal' AND dummy_card_type IS NULL))
                          AND (dummy_partner_name = :dpname OR (:dpname IS NULL AND dummy_partner_name IS NULL))
                    ");
                    $stmtUpdate->execute([
                        ':cid'     => $realCardId,
                        ':deck_id' => $deckId,
                        ':dname'   => $dName,
                        ':dtype'   => $dType,
                        ':dpname'  => $dpName
                    ]);
                }
            }
        } catch (\Throwable $e) {}

        // ★ 2. カード一覧取得クエリ（存在しない未追加カラムを参照しない安全版）
        $sql = "SELECT 
                    COALESCE(c.card_id, CONCAT('dummy_', dc.sort_order)) as card_id, 
                    COALESCE(c.card_name, dc.dummy_name) as card_name, 
                    c.reading,
                    COALESCE(c.cost, dc.dummy_cost) as cost, 
                    c.pow,
                    c.text,
                    cd.modelnum, 
                    cd.imagepath,
                    cd.twinpact,
                    cd.hypermode,
                    dc.quantity,
                    dc.card_type_in_deck,
                    CASE WHEN dc.dummy_name IS NOT NULL THEN 1 ELSE 0 END as is_dummy,
                    'normal' as dummy_card_type,
                    NULL as dummy_partner_name,
                    NULL as dummy_partner_cost,
                    NULL as dummy_partner_civ_ids,
                    cc.combination_id,
                    (SELECT GROUP_CONCAT(characteristics_id) FROM card_characteristics WHERE card_id = c.card_id) as char_ids,
                    COALESCE((SELECT GROUP_CONCAT(civilization_id) FROM card_civilization WHERE card_id = c.card_id), dc.dummy_civ_ids) as civ_ids,
                    (SELECT GROUP_CONCAT(cardtype_id) FROM card_cardtype WHERE card_id = c.card_id) as cardtype_ids,
                    (SELECT GROUP_CONCAT(typename SEPARATOR '/') FROM card_cardtype WHERE card_id = c.card_id) as typename,
                    (SELECT GROUP_CONCAT(race_id) FROM card_race WHERE card_id = c.card_id) as race_ids,
                    (SELECT GROUP_CONCAT(r.race_name SEPARATOR '/') FROM card_race cr JOIN race r ON cr.race_id = r.race_id WHERE cr.card_id = c.card_id) as race_names,
                    
                    -- 相方（下面・裏面）情報
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
                LEFT JOIN card c ON dc.card_id = c.card_id 
                LEFT JOIN card_detail cd ON c.card_id = cd.card_id 
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
        $sql = "SELECT d.*, cd.imagepath AS thumbnail_imagepath 
                FROM decks d 
                LEFT JOIN card_detail cd ON d.thumbnail_card_id = cd.card_id
                WHERE d.deck_id = :did AND d.user_id = :uid";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':did' => $deckId, ':uid' => $userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}