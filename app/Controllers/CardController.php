<?php namespace Controllers;

use Models\Database;
use PDO;

class CardController {
/**
     * カード検索API (究極の高速化：UNIONマッピング方式)
     */
/**
     * カード検索API (安定・高速化版)
     */
/**
     * カード検索API (究極の高速化：フラット・マッピング方式)
     * 計算（EXISTS）を排除し、結合（JOIN）のみで構成
     */
/**
     * カード検索API (爆速フラグ・無限スクロール・詳細フィルタ・ゾーン振り分け対応)
     */
    public function cardsApi() {
        // パラメータ取得
        $q = $_GET['q'] ?? '';
        $scope = isset($_GET['scope']) ? explode(',', $_GET['scope']) : ['name'];
        $costMin = $_GET['cost_min'] ?? '';
        $costMax = $_GET['cost_max'] ?? '';
        $powMin = $_GET['pow_min'] ?? '';
        $powMax = $_GET['pow_max'] ?? '';
        $civs = isset($_GET['civs']) ? explode(',', $_GET['civs']) : [];
        $races = isset($_GET['races']) ? explode(',', $_GET['races']) : [];
        $abilities = isset($_GET['abilities']) ? explode(',', $_GET['abilities']) : [];
        $regulations = isset($_GET['reg']) ? explode(',', $_GET['reg']) : [];
        
        $raceLogic = $_GET['race_logic'] ?? 'OR';
        $abilityLogic = $_GET['ability_logic'] ?? 'OR';

        $limit = 50;
        $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

        try {
            $pdo = \Models\Database::connect();
            $params = [];

            // --- ステップ1: 検索条件に合致する「ベース名」のリストを抽出する一時的なクエリ ---
            // 裏面がヒットしても表面の名前に変換するロジックを維持
            $searchSql = "
                SELECT DISTINCT 
                    COALESCE(c_front.card_name, c_search.card_name) as target_name
                FROM card c_search
                JOIN card_detail cd_search ON c_search.card_id = cd_search.card_id
                LEFT JOIN card_combination ccb_search ON c_search.card_id = ccb_search.card_id
                LEFT JOIN card_combination ccb_front ON ccb_search.combination_id = ccb_front.combination_id AND ccb_front.is_main_side = 1
                LEFT JOIN card c_front ON ccb_front.card_id = c_front.card_id
                WHERE 1=1";

            if ($q !== '') {
                $conds = [];
                if (in_array('name', $scope)) {
                    $conds[] = "(c_search.card_name LIKE :q_name OR c_search.reading LIKE :q_read)";
                    $params[':q_name'] = $params[':q_read'] = "%$q%";
                }
                if (in_array('text', $scope)) {
                    $conds[] = "c_search.text LIKE :q_text"; // テキストカラムを検索
                    $params[':q_text'] = "%$q%";
                }
                if (!empty($conds)) {
                    $searchSql .= " AND (" . implode(' OR ', $conds) . ")";
                }
            }

            if ($costMin !== '') { $searchSql .= " AND c_search.cost >= :cMin"; $params[':cMin'] = (int)$costMin; }
            if ($costMax !== '') { $searchSql .= " AND c_search.cost <= :cMax"; $params[':cMax'] = (int)$costMax; }
            if ($powMin !== '') { $searchSql .= " AND c_search.pow >= :pMin"; $params[':pMin'] = (int)$powMin; }
            if ($powMax !== '') { $searchSql .= " AND c_search.pow <= :pMax"; $params[':pMax'] = (int)$powMax; }

            if (!empty($civs)) {
                $civList = implode(',', array_map('intval', $civs));
                $searchSql .= " AND EXISTS (SELECT 1 FROM card_civilization cc WHERE cc.card_id = c_search.card_id AND cc.civilization_id IN ($civList))";
            }

            if (!empty($races)) {
                $ids = array_map('intval', $races);
                if ($raceLogic === 'AND') {
                    foreach($ids as $i => $id) {
                        $searchSql .= " AND EXISTS (SELECT 1 FROM card_race cr WHERE cr.card_id = c_search.card_id AND cr.race_id = :r$i)";
                        $params[":r$i"] = $id;
                    }
                } else {
                    $idList = implode(',', $ids);
                    $searchSql .= " AND EXISTS (SELECT 1 FROM card_race cr WHERE cr.card_id = c_search.card_id AND cr.race_id IN ($idList))";
                }
            }

            if (!empty($abilities)) {
                $ids = array_map('intval', $abilities);
                if ($abilityLogic === 'AND') {
                    foreach($ids as $i => $id) {
                        $searchSql .= " AND EXISTS (SELECT 1 FROM card_ability ca WHERE ca.card_id = c_search.card_id AND ca.ability_id = :a$i)";
                        $params[":a$i"] = $id;
                    }
                } else {
                    $idList = implode(',', $ids);
                    $searchSql .= " AND EXISTS (SELECT 1 FROM card_ability ca WHERE ca.card_id = c_search.card_id AND ca.ability_id IN ($idList))";
                }
            }

            if (!empty($regulations)) {
                $regList = implode(',', array_map('intval', $regulations));
                $searchSql .= " AND cd_search.regulation IN ($regList)";
            }

            // --- ステップ2: メインクエリ ---
            // ゾーン振り分け用の characteristics_id (char_ids) を追加で取得
            $sql = "
                SELECT 
                    c.*, cd.modelnum, cd.imagepath,
                    (SELECT GROUP_CONCAT(characteristics_id) FROM card_characteristics WHERE card_id = c.card_id) as char_ids,
                    IF(ccb.card_id IS NOT NULL, 1, 0) as is_combo
                FROM card c
                JOIN card_detail cd ON c.card_id = cd.card_id
                LEFT JOIN card_combination ccb ON c.card_id = ccb.card_id
                JOIN ($searchSql) as matched_names ON c.card_name = matched_names.target_name
                WHERE cd.is_primary_version = 1
                AND (ccb.combination_id IS NULL OR ccb.is_main_side = 1)
                ORDER BY c.cost ASC, c.reading ASC
                LIMIT :limit OFFSET :offset";

            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            foreach ($params as $key => $val) {
                $stmt->bindValue($key, $val, is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR);
            }

            $stmt->execute();
            header('Content-Type: application/json');
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            
        } catch (\Exception $e) {
            header('Content-Type: application/json', true, 500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
        public function masterDataApi() {
        try {
            $pdo = Database::connect();
            $races = $pdo->query("SELECT race_id, race_name, reading FROM race ORDER BY (CASE WHEN reading = '' OR reading IS NULL THEN 0 ELSE 1 END) ASC, reading ASC, race_id ASC")->fetchAll(PDO::FETCH_ASSOC);
            $abilities = $pdo->query("SELECT ability_id, ability_name, reading FROM ability ORDER BY (CASE WHEN reading = '' OR reading IS NULL THEN 0 ELSE 1 END) ASC, reading ASC, ability_id ASC")->fetchAll(PDO::FETCH_ASSOC);
            header('Content-Type: application/json');
            echo json_encode(['races' => $races, 'abilities' => $abilities]);
        } catch (\Exception $e) {
            header('Content-Type: application/json', true, 500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

public function cardVersionsApi() {
        $cardId = $_GET['card_id'] ?? null;
        if (!$cardId) return;

        try {
            $pdo = \Models\Database::connect();
            $stmtName = $pdo->prepare("SELECT card_name FROM card WHERE card_id = :id");
            $stmtName->execute([':id' => $cardId]);
            $card = $stmtName->fetch();
            if (!$card) return;

            // ★修正：SELECT項目に char_ids を追加
            $sql = "SELECT c.card_id, c.card_name, c.text, c.pow, c.cost, cd.modelnum, cd.imagepath, cd.release_date,
                           (SELECT GROUP_CONCAT(characteristics_id) FROM card_characteristics WHERE card_id = c.card_id) as char_ids,
                           CASE WHEN ccb.card_id IS NOT NULL THEN 1 ELSE 0 END as is_combo
                    FROM card c
                    JOIN card_detail cd ON c.card_id = cd.card_id
                    LEFT JOIN card_combination ccb ON c.card_id = ccb.card_id
                    WHERE c.card_name = :name
                    ORDER BY cd.release_date ASC";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':name' => $card['card_name']]);
            header('Content-Type: application/json');
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch (\Exception $e) {
            header('Content-Type: application/json', true, 500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    // --- CardController.php 内（クラスの最後など）に追記するメソッド ---

    public function cardCombinationApi() {
        $cardId = $_GET['card_id'] ?? null;
        if (!$cardId) {
            header('Content-Type: application/json', true, 400);
            echo json_encode(['error' => 'Card ID is missing']);
            return;
        }

        try {
            $pdo = \Models\Database::connect();
            
            // 1. まず card_combination 経由で同じ combination_id を持つ両面（すべての面）カード情報を取得を試みる
            $sql = "SELECT 
                        c.card_id, 
                        c.card_name, 
                        c.text, 
                        cd.imagepath
                    FROM card_combination cc
                    JOIN card_combination cc_all ON cc.combination_id = cc_all.combination_id
                    JOIN card c ON cc_all.card_id = c.card_id
                    JOIN card_detail cd ON c.card_id = cd.card_id
                    WHERE cc.card_id = :card_id
                    ORDER BY cc_all.card_id ASC";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':card_id' => $cardId]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // 2. もし裏表（combinationデータ）が存在しない通常カードだった場合は、そのカード単体を取得して返す
            if (empty($results)) {
                $sqlFallback = "SELECT 
                                    c.card_id, 
                                    c.card_name, 
                                    c.text, 
                                    cd.imagepath
                                FROM card c
                                JOIN card_detail cd ON c.card_id = cd.card_id
                                WHERE c.card_id = :card_id
                                LIMIT 1";
                $stmtFallback = $pdo->prepare($sqlFallback);
                $stmtFallback->execute([':card_id' => $cardId]);
                $results = $stmtFallback->fetchAll(PDO::FETCH_ASSOC);
            }

            header('Content-Type: application/json');
            echo json_encode($results);
            
        } catch (\Exception $e) {
            header('Content-Type: application/json', true, 500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
}

