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

/**
     * 修正：特殊タイプ、カードタイプ、収録商品のマスターデータを取得するAPI
     * （プロジェクト共通の Database::connect() に接続を統一しました）
     */
    public function masterDataExtendedApi() {
        header('Content-Type: application/json; charset=utf-8');
        try {
            $pdo = Database::connect();

            // カードタイプ
            $cardtypes = $pdo->query("SELECT cardtype_id, cardtype_name FROM cardtype ORDER BY cardtype_id ASC")->fetchAll(PDO::FETCH_ASSOC);

            // 特殊タイプ
            $characteristics = $pdo->query("SELECT characteristics_id, characteristics_name FROM characteristics ORDER BY characteristics_id ASC")->fetchAll(PDO::FETCH_ASSOC);

            // 収録商品 (降順)
            $goods = $pdo->query("SELECT goods_id, goods_name FROM goods ORDER BY goods_id DESC")->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'cardtypes' => $cardtypes,
                'characteristics' => $characteristics,
                'goods' => $goods
            ], JSON_UNESCAPED_UNICODE);

        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * 新規追加：ヘルプ画面用の高度な絞り込みカード検索API（文明完全一致・全バージョン表示・新着順ソート完全版）
     */
    public function helpSearchApi() {
        header('Content-Type: application/json; charset=utf-8');
        
        $q = $_GET['q'] ?? '';
        $scopes = isset($_GET['scope']) ? explode(',', $_GET['scope']) : ['name'];
        $civType = $_GET['civ_type'] ?? '';
        $civs = isset($_GET['civs']) ? explode(',', $_GET['civs']) : [];
        $excludeCivs = isset($_GET['exclude_civs']) ? explode(',', $_GET['exclude_civs']) : [];
        $civMatchType = $_GET['civ_match_type'] ?? 'include';
        
        $races = isset($_GET['races']) ? explode(',', $_GET['races']) : [];
        $abilities = isset($_GET['abilities']) ? explode(',', $_GET['abilities']) : [];
        $characteristics = isset($_GET['characteristics']) ? explode(',', $_GET['characteristics']) : [];
        $cardtypes = isset($_GET['cardtypes']) ? explode(',', $_GET['cardtypes']) : [];
        $goods = isset($_GET['goods']) ? explode(',', $_GET['goods']) : [];

        // ページング用パラメータの取得（デフォルト値：100件、0オフセット）
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
        $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

        try {
            $pdo = Database::connect();
            $params = [];

            // ベースとなるSQLクエリ (is_primary_version と is_main_side の制限を撤廃、release_date をセレクトに追加)
            $sql = "
                SELECT DISTINCT c.card_id, c.card_name, cd.imagepath, cd.release_date
                FROM card c
                JOIN card_detail cd ON c.card_id = cd.card_id
                WHERE 1=1
            ";

            // キーワード検索（スコープ対応）
            if ($q !== '') {
                $conds = [];
                if (in_array('name', $scopes)) {
                    $conds[] = "(c.card_name LIKE :q_name OR c.reading LIKE :q_read)";
                    $params[':q_name'] = $params[':q_read'] = "%$q%";
                }
                if (in_array('text', $scopes)) {
                    $conds[] = "c.text LIKE :q_text";
                    $params[':q_text'] = "%$q%";
                }
                if (in_array('race', $scopes)) {
                    $conds[] = "EXISTS (SELECT 1 FROM card_race cr JOIN race r ON cr.race_id = r.race_id WHERE cr.card_id = c.card_id AND r.race_name LIKE :q_race)";
                    $params[':q_race'] = "%$q%";
                }
                if (!empty($conds)) {
                    $sql .= " AND (" . implode(' OR ', $conds) . ")";
                }
            }

            // 単色・多色による絞り込み
            if ($civType === 'single') {
                $sql .= " AND (SELECT COUNT(*) FROM card_civilization cc WHERE cc.card_id = c.card_id) = 1";
            } elseif ($civType === 'multi') {
                $sql .= " AND (SELECT COUNT(*) FROM card_civilization cc WHERE cc.card_id = c.card_id) >= 2";
            } elseif ($civType === 'none') {
                $sql .= " AND 1=0";
            }

            // 含む文明 / のみ持つ文明
            if (!empty($civs)) {
                $civList = implode(',', array_map('intval', $civs));
                
                if ($civMatchType === 'match') {
                    // 「のみ持つ」：
                    // 1. 選択したすべての文明を「過不足なくすべて持っている」 (AND結合)
                    foreach ($civs as $cId) {
                        $sql .= " AND EXISTS (SELECT 1 FROM card_civilization cc WHERE cc.card_id = c.card_id AND cc.civilization_id = " . (int)$cId . ")";
                    }
                    // 2. 選択した文明「以外」の文明を一切持たない
                    $sql .= " AND NOT EXISTS (SELECT 1 FROM card_civilization cc WHERE cc.card_id = c.card_id AND cc.civilization_id NOT IN ($civList))";
                } else {
                    // 「含む」：選択した文明のいずれかを持つ
                    $sql .= " AND EXISTS (SELECT 1 FROM card_civilization cc WHERE cc.card_id = c.card_id AND cc.civilization_id IN ($civList))";
                }
            }

            // 含まれない文明の除外
            if (!empty($excludeCivs)) {
                $excludeList = implode(',', array_map('intval', $excludeCivs));
                $sql .= " AND NOT EXISTS (SELECT 1 FROM card_civilization cc WHERE cc.card_id = c.card_id AND cc.civilization_id IN ($excludeList))";
            }

            // 各種絞り込み
            if (!empty($races)) {
                $idList = implode(',', array_map('intval', $races));
                $sql .= " AND EXISTS (SELECT 1 FROM card_race cr WHERE cr.card_id = c.card_id AND cr.race_id IN ($idList))";
            }
            if (!empty($abilities)) {
                $idList = implode(',', array_map('intval', $abilities));
                $sql .= " AND EXISTS (SELECT 1 FROM card_ability ca WHERE ca.card_id = c.card_id AND ca.ability_id IN ($idList))";
            }
            if (!empty($characteristics)) {
                $idList = implode(',', array_map('intval', $characteristics));
                $sql .= " AND EXISTS (SELECT 1 FROM card_characteristics c_char WHERE c_char.card_id = c.card_id AND c_char.characteristics_id IN ($idList))";
            }
            if (!empty($cardtypes)) {
                $idList = implode(',', array_map('intval', $cardtypes));
                $sql .= " AND EXISTS (SELECT 1 FROM card_cardtype c_type WHERE c_type.card_id = c.card_id AND c_type.cardtype_id IN ($idList))";
            }
            if (!empty($goods)) {
                $idList = implode(',', array_map('intval', $goods));
                $sql .= " AND cd.goods_id IN ($idList)";
            }

            // 発売日（release_date）が新しい順（DESC）にし、同日の場合はコスト・読みがな順で並び替えて制限します
            $sql .= " ORDER BY cd.release_date DESC, c.cost ASC, c.reading ASC LIMIT " . (int)$limit . " OFFSET " . (int)$offset;

            $stmt = $pdo->prepare($sql);
            
            foreach ($params as $key => $val) {
                $stmt->bindValue($key, $val, is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR);
            }

            $stmt->execute();
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));

        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * 新規追加：ヘルプ詳細カード情報表示用API
     */
    public function helpDetailApi() {
        header('Content-Type: application/json; charset=utf-8');
        
        $cardId = $_GET['card_id'] ?? null;
        if (!$cardId) {
            http_response_code(400);
            echo json_encode(['error' => 'Card ID is required']);
            exit;
        }

        try {
            $pdo = Database::connect();

            $sql = "
                SELECT 
                    c.card_id,
                    c.card_name,
                    c.reading,
                    c.pow,
                    c.cost,
                    c.text,
                    c.flavortext,
                    cd.imagepath,
                    g.goods_name,
                    (SELECT GROUP_CONCAT(cl.civilization_name SEPARATOR ' / ') 
                     FROM card_civilization cc 
                     JOIN civilization cl ON cc.civilization_id = cl.civilization_id 
                     WHERE cc.card_id = c.card_id) as civilizations,
                    (SELECT GROUP_CONCAT(r.race_name SEPARATOR ' / ') 
                     FROM card_race cr 
                     JOIN race r ON cr.race_id = r.race_id 
                     WHERE cr.card_id = c.card_id) as races,
                    (SELECT GROUP_CONCAT(ab.ability_name SEPARATOR ' / ') 
                     FROM card_ability ca 
                     JOIN ability ab ON ca.ability_id = ab.ability_id 
                     WHERE ca.card_id = c.card_id) as abilities
                FROM card c
                JOIN card_detail cd ON c.card_id = cd.card_id
                LEFT JOIN goods g ON cd.goods_id = g.goods_id
                WHERE c.card_id = :card_id
                LIMIT 1
            ";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([':card_id' => $cardId]);
            $cardInfo = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$cardInfo) {
                http_response_code(404);
                echo json_encode(['error' => 'Card not found']);
                exit;
            }

            echo json_encode($cardInfo, JSON_UNESCAPED_UNICODE);

        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
        exit;
    }
    
}
